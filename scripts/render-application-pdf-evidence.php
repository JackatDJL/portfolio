<?php

use App\Support\ApplicationPdfExporter;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;
use Tests\Support\ApplicationPdfFixture;

// Synthetic review evidence only. Keep output outside the checkout and out of Git.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = realpath($argv[1] ?? '');
$root = realpath(__DIR__.'/..');
if (! $directory || ! is_dir($directory) || $directory === $root || str_starts_with($directory, $root.DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, "Pass an existing evidence directory outside the checkout.\n");
    exit(1);
}
$fixture = new ApplicationPdfFixture($directory.'/runtime');
try {
    $fixture->documents();
    $pdf = app(ApplicationPdfExporter::class)->render($fixture->entry);
    copy($pdf, $directory.'/bewerbung.pdf');
    chmod($directory.'/bewerbung.pdf', 0600);
    $process = new Process(['pdftoppm', '-scale-to', '1400', '-png', $directory.'/bewerbung.pdf', $directory.'/page']);
    $process->setTimeout(60)->mustRun();
    $inspect = new Process([config('applications.python'), base_path('tests/Support/inspect_application_pdf.py'), $directory.'/bewerbung.pdf']);
    $inspect->mustRun();
    $data = json_decode($inspect->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    unset($data['texts'], $data['first_words']); // Validation summary contains dimensions/links, no extracted personal data.
    file_put_contents($directory.'/validation.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Rendered synthetic application PDF and all pages.\n";
} finally {
    $fixture->cleanup();
}
