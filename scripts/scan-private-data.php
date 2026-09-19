<?php

declare(strict_types=1);

use App\Support\CvPrivateData;
use Illuminate\Contracts\Console\Kernel;
use Statamic\Facades\GlobalSet;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$variables = GlobalSet::find('cv')?->inDefaultSite();
$private = $variables ? CvPrivateData::reveal($variables) : [];
$address = implode(' ', array_filter([
    $private['street'] ?? null,
    $private['house_number'] ?? null,
    $private['postal_code'] ?? null,
    $private['city'] ?? null,
    $private['country'] ?? null,
]));
$needles = array_values(array_filter(array_merge($private, [$address], [
    'PRIVATE-CV-EMAIL-SENTINEL@example.invalid',
    'PRIVATE-CV-PHONE-SENTINEL',
    'PRIVATE-CV-ADDRESS-SENTINEL',
]), static fn ($value) => is_string($value) && mb_strlen($value) >= 8));

$roots = array_filter([
    storage_path('app/static'),
    public_path('build'),
    public_path('documents'),
], 'is_dir');
$extensions = ['html', 'htm', 'css', 'js', 'json', 'map', 'txt', 'xml', 'svg'];
$files = [];
$leaks = [];

foreach ($roots as $root) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), $extensions, true)) {
            continue;
        }
        $files[] = $file->getPathname();
        $contents = file_get_contents($file->getPathname());
        foreach ($needles as $needle) {
            if ($contents !== false && str_contains($contents, $needle)) {
                $leaks[] = [$needle, $file->getPathname()];
            }
        }
    }
}

printf("Scanned %d generated files for %d protected values.\n", count($files), count($needles));
foreach ($leaks as [$needle, $path]) {
    fwrite(STDERR, "LEAK: {$needle} in {$path}\n");
}

exit($leaks === [] ? 0 : 1);
