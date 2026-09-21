<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

class CvPdfRenderer
{
    public function render(string $path, ?string $capability = null): string
    {
        $directory = storage_path('app/private/cv-pdf');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the private CV PDF directory.');
        }

        $output = $directory.'/'.bin2hex(random_bytes(16)).'.pdf';
        $url = rtrim((string) config('cv.pdf_base_url'), '/').$path;
        $process = new Process([
            (string) config('cv.bun', 'bun'),
            base_path('scripts/render-cv-pdf.mjs'),
            '--url='.$url,
            '--output='.$output,
        ], base_path(), ['CV_PDF_TOKEN' => $capability ?? '']);
        $process->setTimeout((float) config('cv.pdf_timeout', 60));
        $process->mustRun();

        if (! is_file($output) || file_get_contents($output, false, null, 0, 5) !== '%PDF-') {
            @unlink($output);
            throw new RuntimeException('Chromium did not produce a valid PDF.');
        }

        return $output;
    }
}
