<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class PdfCompilerControl
{
    private const MAX_LOG_BYTES = 1_048_576;

    private const LOG_TAIL_BYTES = 65_536;

    private const EVENTS = [
        'compiler_failed' => ['LuaLaTeX-Kompilierung fehlgeschlagen.', 'error'],
        'compiler_process_failed' => ['LuaLaTeX konnte nicht ausgeführt werden.', 'error'],
        'compiler_runtime_failed' => ['PDF-Kompilierung konnte nicht vorbereitet werden.', 'error'],
        'application_export_failed' => ['Bewerbungs-PDF fehlgeschlagen.', 'error'],
        'public_recompile_started' => ['Neukompilierung des öffentlichen Lebenslaufs gestartet.', 'info'],
        'public_recompile_finished' => ['Öffentlicher Lebenslauf wurde neu kompiliert.', 'info'],
        'public_recompile_failed' => ['Neukompilierung des öffentlichen Lebenslaufs fehlgeschlagen.', 'error'],
        'application_recompile_started' => ['Neukompilierung einer Bewerbungs-PDF gestartet.', 'info'],
        'application_recompile_finished' => ['Bewerbungs-PDF wurde neu kompiliert.', 'info'],
        'application_recompile_failed' => ['Neukompilierung einer Bewerbungs-PDF fehlgeschlagen.', 'error'],
        'cache_cleared' => ['PDF-Cache wurde geleert.', 'info'],
        'cache_clear_failed' => ['PDF-Cache konnte nicht geleert werden.', 'error'],
    ];

    private const OPERATIONS = [
        'public_cv', 'private_cv', 'application_cv', 'application_letter', 'application_export', 'pdf',
    ];

    private const STAGES = [
        'Datenprüfung', 'Anschreiben', 'Lebenslauf', 'Zusammenstellung', 'Nachbearbeitung', 'export', 'Compiler',
    ];

    public function record(string $event, array $context = []): void
    {
        if (! isset(self::EVENTS[$event])) {
            throw new RuntimeException('Unknown PDF compiler log event.');
        }

        [$message, $level] = self::EVENTS[$event];
        $safeContext = $this->sanitizeContext($context);
        $timestamp = now()->toIso8601String();
        $line = '['.$timestamp.'] '.$message;
        if ($safeContext !== []) {
            $line .= ' '.json_encode($safeContext, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $line .= PHP_EOL;
        $containerMessage = '[pdf-compiler] '.$message;
        if ($safeContext !== []) {
            $containerMessage .= ' '.json_encode($safeContext, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        try {
            $this->append($line);
        } catch (\Throwable) {
            // Keep the container log useful even when the private control log is unavailable.
        }

        try {
            $channel = Log::channel('stderr');
            $channel->{$level}($containerMessage, $safeContext);
        } catch (\Throwable) {
            @error_log($containerMessage);
        }
    }

    public function status(): array
    {
        $running = $this->isBusy();

        return $this->withCacheReadLock(fn (): array => [
            'running' => $running,
            'caches' => [
                'public_cv' => $this->cacheStatistics((string) config('pdf-compiler.public_cv_cache_path')),
                'applications' => $this->cacheStatistics((string) config('applications.cache_path')),
            ],
            'logs' => $this->logs(),
        ]);
    }

    public function withCacheReadLock(callable $callback): mixed
    {
        $lock = $this->openCacheLock();
        if (! flock($lock, LOCK_SH)) {
            fclose($lock);
            throw new RuntimeException('Could not acquire the PDF cache read lock.');
        }

        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{busy: bool, pdfs_removed: int, bytes_removed: int} */
    public function clearCaches(): array
    {
        $lock = $this->openCacheLock();
        if (! flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);

            return ['busy' => true, 'pdfs_removed' => 0, 'bytes_removed' => 0];
        }

        $pdfsRemoved = 0;
        $bytesRemoved = 0;
        try {
            foreach ([
                (string) config('pdf-compiler.public_cv_cache_path'),
                (string) config('applications.cache_path'),
            ] as $path) {
                [$files, $bytes] = $this->clearCacheDirectory($path);
                $pdfsRemoved += $files;
                $bytesRemoved += $bytes;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return ['busy' => false, 'pdfs_removed' => $pdfsRemoved, 'bytes_removed' => $bytesRemoved];
    }

    private function isBusy(): bool
    {
        $lock = $this->openCacheLock();
        $busy = ! flock($lock, LOCK_EX | LOCK_NB);
        if (! $busy) {
            flock($lock, LOCK_UN);
        }
        fclose($lock);

        return $busy;
    }

    /** @return array{files: int, bytes: int} */
    private function cacheStatistics(string $path): array
    {
        $directory = $this->privateCachePath($path);
        if (! is_dir($directory)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $files = 0;
        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || $file->isLink() || ! $file->isFile() || strtolower($file->getExtension()) !== 'pdf') {
                continue;
            }
            $files++;
            $bytes += $file->getSize();
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    /** @return array{int, int} */
    private function clearCacheDirectory(string $path): array
    {
        $directory = $this->privateCachePath($path);
        if (! is_dir($directory)) {
            return [0, 0];
        }

        $files = 0;
        $bytes = 0;
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
            $pathname = $entry->getPathname();
            if ($entry->isLink() || ! $entry->isDir()) {
                if (strtolower(pathinfo($pathname, PATHINFO_EXTENSION)) === 'pdf' && is_file($pathname)) {
                    $files++;
                    $bytes += (int) (filesize($pathname) ?: 0);
                }
                if (! @unlink($pathname)) {
                    throw new RuntimeException('Could not remove a PDF cache item.');
                }
                continue;
            }

            [$nestedFiles, $nestedBytes] = $this->clearCacheDirectoryContents($pathname);
            $files += $nestedFiles;
            $bytes += $nestedBytes;
            if (! @rmdir($pathname)) {
                throw new RuntimeException('Could not remove an empty PDF cache directory.');
            }
        }

        return [$files, $bytes];
    }

    /** @return array{int, int} */
    private function clearCacheDirectoryContents(string $directory): array
    {
        $files = 0;
        $bytes = 0;
        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
            $pathname = $entry->getPathname();
            if ($entry->isLink() || ! $entry->isDir()) {
                if (strtolower(pathinfo($pathname, PATHINFO_EXTENSION)) === 'pdf' && is_file($pathname)) {
                    $files++;
                    $bytes += (int) (filesize($pathname) ?: 0);
                }
                if (! @unlink($pathname)) {
                    throw new RuntimeException('Could not remove a PDF cache item.');
                }
                continue;
            }

            [$nestedFiles, $nestedBytes] = $this->clearCacheDirectoryContents($pathname);
            $files += $nestedFiles;
            $bytes += $nestedBytes;
            if (! @rmdir($pathname)) {
                throw new RuntimeException('Could not remove an empty PDF cache directory.');
            }
        }

        return [$files, $bytes];
    }

    private function privateCachePath(string $path): string
    {
        $privateRoot = realpath(storage_path('app/private'));
        if (! is_string($privateRoot) || $path === '') {
            throw new RuntimeException('PDF cache path is unavailable.');
        }
        $privatePrefix = rtrim($privateRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($path, $privatePrefix)) {
            throw new RuntimeException('PDF cache path is outside private storage.');
        }

        $resolved = $privateRoot;
        $segments = explode(DIRECTORY_SEPARATOR, substr($path, strlen($privatePrefix)));
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('PDF cache path is unavailable.');
            }
            $candidate = $resolved.DIRECTORY_SEPARATOR.$segment;
            if (is_link($candidate)) {
                throw new RuntimeException('PDF cache path contains a symbolic link.');
            }
            $existing = realpath($candidate);
            if ($existing !== false) {
                if (! str_starts_with($existing, $privatePrefix)) {
                    throw new RuntimeException('PDF cache path is outside private storage.');
                }
                $resolved = $existing;
            } else {
                $resolved = $candidate;
            }
        }

        return $resolved;
    }

    /** @return resource */
    private function openCacheLock()
    {
        $path = $this->privateCachePath((string) config('pdf-compiler.cache_lock_path'));
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the PDF cache lock directory.');
        }
        chmod($directory, 0700);
        $lock = fopen($path, 'c');
        if (! is_resource($lock)) {
            throw new RuntimeException('Could not open the PDF cache lock.');
        }
        chmod($path, 0600);

        return $lock;
    }

    private function logs(): string
    {
        $path = $this->privateCachePath((string) config('pdf-compiler.log_path'));
        if (! is_file($path)) {
            return '';
        }
        $handle = @fopen($path, 'rb');
        if (! is_resource($handle)) {
            return '';
        }
        if (! flock($handle, LOCK_SH)) {
            fclose($handle);
            return '';
        }
        try {
            $size = fstat($handle)['size'] ?? 0;
            if ($size > self::LOG_TAIL_BYTES) {
                fseek($handle, $size - self::LOG_TAIL_BYTES);
                fgets($handle);
            }
            $tail = stream_get_contents($handle, self::LOG_TAIL_BYTES);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return implode("\n", array_slice(preg_split('/\R/', (string) $tail) ?: [], -120));
    }

    private function append(string $line): void
    {
        $path = $this->privateCachePath((string) config('pdf-compiler.log_path'));
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the PDF compiler log directory.');
        }
        chmod($directory, 0700);
        $handle = @fopen($path, 'c+');
        if (! is_resource($handle)) {
            throw new RuntimeException('Could not open the PDF compiler log.');
        }
        chmod($path, 0600);
        flock($handle, LOCK_EX);
        try {
            fseek($handle, 0, SEEK_END);
            fwrite($handle, $line);
            fflush($handle);
            $size = fstat($handle)['size'] ?? 0;
            if ($size > self::MAX_LOG_BYTES) {
                fseek($handle, $size - self::MAX_LOG_BYTES);
                fgets($handle);
                $tail = stream_get_contents($handle, self::MAX_LOG_BYTES);
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, (string) $tail);
                fflush($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return array<string, int|string|null> */
    private function sanitizeContext(array $context): array
    {
        $safe = [];
        if (isset($context['reference']) && is_string($context['reference']) && preg_match('/^[a-f0-9]{12}$/D', $context['reference'])) {
            $safe['reference'] = $context['reference'];
        }
        if (isset($context['operation']) && in_array($context['operation'], self::OPERATIONS, true)) {
            $safe['operation'] = $context['operation'];
        }
        if (isset($context['stage']) && in_array($context['stage'], self::STAGES, true)) {
            $safe['stage'] = $context['stage'];
        }
        if (isset($context['diagnostic']) && in_array($context['diagnostic'], LatexCompilationFailure::diagnosticCodes(), true)) {
            $safe['diagnostic'] = $context['diagnostic'];
        }
        if (isset($context['failure_class']) && is_string($context['failure_class'])) {
            $class = basename(str_replace('\\', '/', $context['failure_class']));
            if (preg_match('/^[A-Za-z0-9_]{1,80}$/D', $class)) {
                $safe['failure_class'] = $class;
            }
        }
        foreach (['source_line', 'pass', 'exit_code', 'pdfs_removed', 'bytes_removed'] as $key) {
            if (isset($context[$key]) && is_int($context[$key])) {
                $safe[$key] = $context[$key];
            }
        }

        return $safe;
    }
}
