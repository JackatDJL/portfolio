<?php

namespace App\Support;

use Symfony\Component\Process\Process;

use function Illuminate\Support\php_binary;

class ContentSyncCandidateValidator
{
    /** @param list<string> $conflictingPaths */
    public function validate(string $candidatePath, array $conflictingPaths): void
    {
        $directory = storage_path('app/private/content-sync-validation/'.bin2hex(random_bytes(16)));
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new ContentSyncFailure('Could not prepare an isolated candidate validation directory. The candidate was left untouched.');
        }
        @chmod($directory, 0700);

        try {
            $this->copyTree($candidatePath.'/content', $directory.'/content');
            $this->copyTree($candidatePath.'/public/assets', $directory.'/public/assets');

            $process = new Process(
                [php_binary(), base_path('scripts/validate-content.php')],
                base_path(),
                [
                    'APP_KEY' => (string) config('app.key'),
                    'CONTENT_SYNC_VALIDATION_ROOT' => $directory,
                    'CONTENT_SYNC_VALIDATION_PATHS' => json_encode($conflictingPaths, JSON_THROW_ON_ERROR),
                ],
                null,
                300,
            );
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ContentSyncFailure('Candidate validation failed. The unresolved candidate was restored and no live content changed.');
            }
        } catch (ContentSyncFailure $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw new ContentSyncFailure('Candidate validation failed. The unresolved candidate was restored and no live content changed.');
        } finally {
            $this->removeTree($directory);
            if (file_exists($directory) || is_link($directory)) {
                throw new ContentSyncFailure('The private candidate validation copy could not be removed. The unresolved candidate was left untouched.');
            }
        }
    }

    private function copyTree(string $source, string $target): void
    {
        if (! is_dir($target) && ! mkdir($target, 0700, true) && ! is_dir($target)) {
            throw new ContentSyncFailure('Could not prepare an isolated candidate validation directory. The candidate was left untouched.');
        }
        if (! is_dir($source)) {
            return;
        }
        if (is_link($source)) {
            throw new ContentSyncFailure('The candidate contains an unsupported symbolic link. It was left untouched.');
        }
        $sourceRoot = realpath($source);
        if (! is_string($sourceRoot)) {
            throw new ContentSyncFailure('Could not read the candidate for isolated validation. It was left untouched.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            $from = $item->getPathname();
            $relative = substr($from, strlen($source) + 1);
            $to = $target.DIRECTORY_SEPARATOR.$relative;
            if ($item->isLink()) {
                $link = readlink($from);
                if (! is_string($link) || str_starts_with($link, DIRECTORY_SEPARATOR)) {
                    throw new ContentSyncFailure('The candidate contains an unsupported symbolic link. It was left untouched.');
                }
                $resolved = realpath(dirname($from).DIRECTORY_SEPARATOR.$link);
                if (! is_string($resolved) || ! str_starts_with($resolved, $sourceRoot.DIRECTORY_SEPARATOR)) {
                    throw new ContentSyncFailure('The candidate contains an unsupported symbolic link. It was left untouched.');
                }
                if (! symlink($link, $to)) {
                    throw new ContentSyncFailure('Could not copy the candidate into isolated validation. It was left untouched.');
                }
            } elseif ($item->isDir()) {
                if (! is_dir($to) && ! mkdir($to, 0700, true) && ! is_dir($to)) {
                    throw new ContentSyncFailure('Could not copy the candidate into isolated validation. It was left untouched.');
                }
            } elseif ($item->isFile()) {
                if (! copy($from, $to)) {
                    throw new ContentSyncFailure('Could not copy the candidate into isolated validation. It was left untouched.');
                }
                @chmod($to, 0600);
            }
        }
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path) || is_link($path)) {
            if (file_exists($path) || is_link($path)) {
                @unlink($path);
            }

            return;
        }

        foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $item) {
            $child = $item->getPathname();
            if ($item->isDir() && ! $item->isLink()) {
                $this->removeTree($child);
            } else {
                @unlink($child);
            }
        }
        @rmdir($path);
    }
}
