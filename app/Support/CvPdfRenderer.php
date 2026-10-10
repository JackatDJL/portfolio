<?php

namespace App\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;

class CvPdfRenderer
{
    private const CACHE_MAX_AGE = 2592000;

    /** @var array<string, string> */
    private static array $engineVersions = [];

    public function __construct(private readonly CvViewModel $viewModel) {}

    public function render(?string $profileSlug = null, bool $authorized = false, ?string $capabilityToken = null): string
    {
        $cv = $this->viewModel->make($profileSlug, $authorized, $capabilityToken);
        $tex = view('latex.cv', compact('cv'))->render();

        if ($authorized) {
            return $this->compile($tex);
        }

        return $this->cachedPublicPdf($cv, $tex);
    }

    private function cachedPublicPdf(array $cv, string $tex): string
    {
        $directory = storage_path('app/private/cv-cache/public');
        $this->ensurePrivateDirectory($directory);
        $key = $this->publicCacheKey($cv, $tex);
        $cached = $directory.'/'.$key.'.pdf';
        $lockPath = $directory.'/'.$key.'.lock';
        $lock = fopen($lockPath, 'c');

        if ($lock === false) {
            throw new RuntimeException('Could not open the public CV PDF cache lock.');
        }

        chmod($lockPath, 0600);

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Could not acquire the public CV PDF cache lock.');
            }

            if ($this->isPdf($cached)) {
                $this->removeExpiredPublicPdfs($directory, $cached);

                return $cached;
            }

            if (is_file($cached)) {
                @unlink($cached);
            }

            $compiled = $this->compile($tex);
            $temporary = $directory.'/'.$key.'.'.bin2hex(random_bytes(8)).'.tmp';

            try {
                chmod($compiled, 0600);
                if (! rename($compiled, $temporary)) {
                    throw new RuntimeException('Could not stage the public CV PDF cache file.');
                }
                chmod($temporary, 0600);
                if (! rename($temporary, $cached)) {
                    throw new RuntimeException('Could not atomically publish the public CV PDF cache file.');
                }
            } finally {
                @unlink($compiled);
                @unlink($temporary);
            }

            $this->removeExpiredPublicPdfs($directory, $cached);

            return $cached;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function compile(string $tex): string
    {
        $root = storage_path('app/private/cv-latex/'.bin2hex(random_bytes(12)));
        $this->ensurePrivateDirectory($root);

        try {
            $texFile = $root.'/cv.tex';
            if (file_put_contents($texFile, $tex, LOCK_EX) === false) {
                throw new RuntimeException('Could not write the private CV source file.');
            }
            chmod($texFile, 0600);

            $binary = $this->lualatex();
            $userHome = str_contains($binary, '/.local/bin/') ? dirname($binary, 3) : (getenv('HOME') ?: null);
            $environment = is_array(getenv()) ? getenv() : [];
            $environment['HOME'] = $userHome;
            $environment['USER'] ??= $userHome ? basename($userHome) : null;
            $process = new Process(
                [$binary, '--interaction=nonstopmode', '--halt-on-error', '--output-directory='.$root, $texFile],
                base_path(),
                $environment,
            );
            $process->setTimeout((float) config('cv.pdf_timeout', 60));
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('CV PDF compilation failed. Compiler output is suppressed to protect private fields.');
            }

            $output = $root.'/cv.pdf';
            if (! $this->isPdf($output)) {
                throw new RuntimeException('LuaLaTeX did not produce a valid PDF.');
            }

            $directory = storage_path('app/private/cv-pdf');
            $this->ensurePrivateDirectory($directory);
            $final = $directory.'/'.bin2hex(random_bytes(16)).'.pdf';
            chmod($output, 0600);
            if (! rename($output, $final)) {
                throw new RuntimeException('Could not save the compiled private CV PDF.');
            }

            return $final;
        } finally {
            $this->removeTree($root);
        }
    }

    private function publicCacheKey(array $cv, string $tex): string
    {
        $files = [
            base_path('app/Support/CvPdfRenderer.php'),
            base_path('app/Support/CvProfileContentResolver.php'),
            base_path('app/Support/CvViewModel.php'),
            base_path('app/Support/Latex.php'),
            base_path('Dockerfile'),
            base_path('composer.lock'),
            base_path('config/cv.php'),
            base_path('resources/views/latex/cv.blade.php'),
        ];

        $fonts = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            base_path('resources/fonts'),
            RecursiveDirectoryIterator::SKIP_DOTS,
        ));
        foreach ($fonts as $font) {
            if ($font->isFile()) {
                $files[] = $font->getPathname();
            }
        }

        if (is_string($cv['photo'] ?? null) && $cv['photo'] !== '') {
            $files[] = $cv['photo'];
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);
        $fingerprints = [];
        foreach ($files as $file) {
            $fingerprints[base_path() === $file ? '.' : ltrim(str_replace(base_path(), '', $file), '/')] = is_file($file)
                ? hash_file('sha256', $file)
                : null;
        }

        $binary = $this->lualatex();
        $fingerprint = [
            'schema' => 1,
            'profile' => $cv['profile']['slug'] ?? 'public',
            'accent' => $cv['accent'] ?? null,
            'content' => $cv,
            'latex' => $tex,
            'files' => $fingerprints,
            'lualatex' => $binary,
            'lualatex_version' => $this->engineVersion($binary),
            'pdf_timeout' => (int) config('cv.pdf_timeout', 60),
        ];

        return hash('sha256', json_encode(
            $fingerprint,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        ));
    }

    private function engineVersion(string $binary): string
    {
        if (isset(self::$engineVersions[$binary])) {
            return self::$engineVersions[$binary];
        }

        $process = new Process([$binary, '--version']);
        $process->setTimeout(5);
        $process->run();
        self::$engineVersions[$binary] = $process->isSuccessful()
            ? trim($process->getOutput())
            : 'unknown';

        return self::$engineVersions[$binary];
    }

    private function removeExpiredPublicPdfs(string $directory, string $current): void
    {
        $expiredBefore = time() - self::CACHE_MAX_AGE;

        foreach (glob($directory.'/*.pdf') ?: [] as $pdf) {
            if ($pdf === $current || (filemtime($pdf) ?: time()) >= $expiredBefore) {
                continue;
            }

            $lockPath = substr($pdf, 0, -4).'.lock';
            $lock = fopen($lockPath, 'c');
            if ($lock === false) {
                continue;
            }

            if (flock($lock, LOCK_EX | LOCK_NB)) {
                if ((filemtime($pdf) ?: time()) < $expiredBefore) {
                    @unlink($pdf);
                }
                flock($lock, LOCK_UN);
            }

            fclose($lock);
        }
    }

    private function isPdf(string $path): bool
    {
        return is_file($path) && file_get_contents($path, false, null, 0, 5) === '%PDF-';
    }

    private function ensurePrivateDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('Could not create a private CV PDF directory.');
        }

        chmod($path, 0700);
    }

    private function removeTree(string $path): void
    {
        if (is_link($path)) {
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            @unlink($path);

            return;
        }

        foreach (glob($path.'/*') ?: [] as $child) {
            $this->removeTree($child);
        }

        @rmdir($path);
    }

    private function lualatex(): string
    {
        $configured = (string) config('cv.lualatex', 'lualatex');
        if (str_contains($configured, '/') && is_executable($configured)) {
            return $configured;
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            $candidate = rtrim($directory, '/').'/'.$configured;
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return $configured;
    }
}
