<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$contentPath = base_path('content/globals/jacks-portfolio/cv.yaml');
$original = file_get_contents($contentPath);
if ($original === false) {
    fwrite(STDERR, "Could not read {$contentPath}.\n");
    exit(1);
}

$fixtures = [
    'private_email' => 'PRIVATE-CV-EMAIL-SENTINEL@example.invalid',
    'phone' => 'PRIVATE-CV-PHONE-SENTINEL',
    'street' => 'PRIVATE-CV-ADDRESS-SENTINEL',
    'house_number' => '1',
    'postal_code' => '21600',
    'city' => 'Teststadt',
];

$fixtureContent = $original;
foreach ($fixtures as $handle => $value) {
    $line = $handle.': '.json_encode(Crypt::encryptString($value), JSON_THROW_ON_ERROR);
    $count = 0;
    $fixtureContent = preg_replace('/^'.preg_quote($handle, '/').':.*$/m', $line, $fixtureContent, 1, $count);
    if ($count === 0) {
        $fixtureContent = rtrim($fixtureContent)."\n".$line."\n";
    }
}

$run = static function (string $command): int {
    passthru($command, $exitCode);

    return $exitCode;
};
$php = PHP_BINDIR.'/php';

$exitCode = 1;
try {
    if (file_put_contents($contentPath, $fixtureContent) === false) {
        throw new RuntimeException('Could not write the encrypted leakage fixture.');
    }
    $stored = (string) file_get_contents($contentPath);
    foreach (array_filter($fixtures, static fn ($value) => mb_strlen($value) >= 8) as $value) {
        if (str_contains($stored, $value)) {
            throw new RuntimeException('A private fixture was stored as plaintext.');
        }
    }

    $artisan = escapeshellarg($php).' '.escapeshellarg(base_path('artisan'));
    if ($run($artisan.' statamic:stache:clear') !== 0
        || $run($artisan.' statamic:ssg:generate') !== 0
        || $run(escapeshellarg($php).' '.escapeshellarg(base_path('scripts/scan-private-data.php'))) !== 0) {
        throw new RuntimeException('The static leakage test failed.');
    }

    $exitCode = 0;
} finally {
    file_put_contents($contentPath, $original);
    $run(escapeshellarg($php).' '.escapeshellarg(base_path('artisan')).' statamic:stache:clear');
}

exit($exitCode);
