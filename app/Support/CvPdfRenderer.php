<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

class CvPdfRenderer
{
    public function __construct(private readonly CvViewModel $viewModel) {}

    public function render(?string $profileSlug = null, bool $authorized = false): string
    {
        $root = storage_path('app/private/cv-latex/'.bin2hex(random_bytes(12)));
        if (! mkdir($root, 0700, true) && ! is_dir($root)) throw new RuntimeException('Could not create the private CV build directory.');
        try {
            $cv = $this->viewModel->make($profileSlug, $authorized);
            $tex = $root.'/cv.tex';
            file_put_contents($tex, view('latex.cv', compact('cv'))->render(), LOCK_EX);
            chmod($tex, 0600);
            $binary = $this->lualatex();
            $userHome = str_contains($binary, '/.local/bin/') ? dirname($binary, 3) : (getenv('HOME') ?: null);
            $environment = is_array(getenv()) ? getenv() : [];
            $environment['HOME'] = $userHome;
            $environment['USER'] ??= $userHome ? basename($userHome) : null;
            $process = new Process(
                [$binary, '--interaction=nonstopmode', '--halt-on-error', '--output-directory='.$root, $tex],
                base_path(),
                $environment
            );
            $process->setTimeout((float) config('cv.pdf_timeout', 60));
            $process->mustRun();
            $output = $root.'/cv.pdf';
            if (! is_file($output) || file_get_contents($output, false, null, 0, 5) !== '%PDF-') throw new RuntimeException('LuaLaTeX did not produce a valid PDF.');
            $directory = storage_path('app/private/cv-pdf');
            if (! is_dir($directory)) mkdir($directory, 0700, true);
            $final = $directory.'/'.bin2hex(random_bytes(16)).'.pdf';
            rename($output, $final);
            return $final;
        } finally {
            foreach (glob($root.'/*') ?: [] as $file) @unlink($file);
            @rmdir($root);
        }
    }

    private function lualatex(): string
    {
        $configured = (string) config('cv.lualatex', 'lualatex');
        if (str_contains($configured, '/') && is_executable($configured)) return $configured;
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            $candidate = rtrim($directory, '/').'/'.$configured;
            if (is_executable($candidate)) return $candidate;
        }
        return $configured;
    }
}
