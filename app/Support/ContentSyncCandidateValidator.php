<?php

namespace App\Support;

use Symfony\Component\Process\Process;

use function Illuminate\Support\php_binary;

class ContentSyncCandidateValidator
{
    /** @param array<string, string> $testDiskRoots */
    public function __construct(private readonly array $testDiskRoots = [])
    {
        if ($this->testDiskRoots !== [] && ! app()->environment('testing')) {
            throw new \LogicException('Candidate validation disk overrides are only available in tests.');
        }
    }

    /** @param list<string> $targetPaths */
    public function validate(string $candidatePath, array $targetPaths, string $expectedIndexTree): string
    {
        $directory = storage_path('app/private/content-sync-validation/'.bin2hex(random_bytes(16)));
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new ContentSyncFailure('Could not prepare an isolated candidate validation directory. The candidate was left untouched.');
        }
        @chmod($directory, 0700);

        try {
            if (preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $expectedIndexTree) !== 1) {
                throw new ContentSyncFailure('The staged candidate tree was not pinned for validation. The candidate was left untouched.');
            }
            $indexTree = $expectedIndexTree;
            $sourcePath = $directory.'/index';
            if (! mkdir($sourcePath, 0700, true) && ! is_dir($sourcePath)) {
                throw new ContentSyncFailure('Could not prepare an isolated staged candidate snapshot. The candidate was left untouched.');
            }
            if (! hash_equals($indexTree, $this->gitIndexTree($candidatePath))) {
                throw new ContentSyncFailure('Could not prepare the pinned staged candidate for validation. The candidate was left untouched.');
            }
            $this->exportPinnedTree($candidatePath, $indexTree, $sourcePath);

            $this->copyTree($sourcePath.'/content', $directory.'/content', $sourcePath);
            $this->copyTree($sourcePath.'/public/assets', $directory.'/public/assets', $sourcePath);

            $externalDiskRoots = [];
            foreach (config('filesystems.disks', []) as $handle => $disk) {
                if ($handle === 'assets' || ($disk['driver'] ?? null) !== 'local') {
                    continue;
                }
                $externalDiskRoots[$handle] = $this->testDiskRoots[$handle]
                    ?? $directory.'/.external-assets-disabled/'.$handle;
            }

            $process = new Process(
                [php_binary(), base_path('scripts/validate-content.php')],
                base_path(),
                [
                    'APP_KEY' => (string) config('app.key'),
                    'ASSETS_DISK_ROOT' => $this->testDiskRoots['assets'] ?? $directory.'/public/assets',
                    'CONTENT_SYNC_VALIDATION_ROOT' => $directory,
                    'CONTENT_SYNC_VALIDATION_RESULT_PATH' => $directory.'/validation-result.json',
                    'CONTENT_SYNC_VALIDATION_PATHS' => json_encode($targetPaths, JSON_THROW_ON_ERROR),
                    'CONTENT_SYNC_VALIDATION_DISK_ROOTS' => json_encode($externalDiskRoots, JSON_THROW_ON_ERROR),
                ],
                null,
                300,
            );
            $process->run();

            $resultPath = $directory.'/validation-result.json';
            $result = is_file($resultPath) && ! is_link($resultPath)
                ? json_decode((string) file_get_contents($resultPath), true)
                : null;
            if (! $process->isSuccessful() || ! is_array($result)
                || ($result['completed'] ?? false) !== true || ($result['passed'] ?? false) !== true) {
                throw new ContentSyncFailure('Candidate validation failed. The unresolved candidate was restored and no live content changed.');
            }
            if (! hash_equals($indexTree, $this->gitIndexTree($candidatePath))) {
                throw new ContentSyncFailure('The staged candidate changed during validation. The candidate was left untouched.');
            }

            return $indexTree;
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

    private function gitIndexTree(string $candidatePath): string
    {
        $process = new Process(
            ['git', 'write-tree'],
            $candidatePath,
            null,
            null,
            30,
        );
        $process->run();
        $tree = trim($process->getOutput());
        if (! $process->isSuccessful() || preg_match('/^(?:[a-f0-9]{40}|[a-f0-9]{64})$/', $tree) !== 1) {
            throw new ContentSyncFailure('Could not identify the staged candidate tree for validation. The candidate was left untouched.');
        }

        return $tree;
    }

    private function exportPinnedTree(string $candidatePath, string $treeHash, string $destination): void
    {
        $listing = new Process(['git', 'ls-tree', '-r', '-z', '--full-tree', $treeHash], $candidatePath, null, null, 60);
        $listing->run();
        if (! $listing->isSuccessful()) {
            throw new ContentSyncFailure('Could not read the pinned candidate tree for validation. The candidate was left untouched.');
        }

        foreach (explode("\0", $listing->getOutput()) as $record) {
            if ($record === '') {
                continue;
            }
            $separator = strpos($record, "\t");
            if ($separator === false) {
                throw new ContentSyncFailure('The pinned candidate tree contains an unsupported entry. It was left untouched.');
            }
            $metadata = substr($record, 0, $separator);
            $path = substr($record, $separator + 1);
            if ($path !== 'content' && $path !== 'public' && $path !== 'public/assets'
                && ! str_starts_with($path, 'content/') && ! str_starts_with($path, 'public/assets/')) {
                continue;
            }
            if (preg_match('/^(100644|100755|120000) blob ([a-f0-9]{40}|[a-f0-9]{64})$/', $metadata, $matches) !== 1) {
                throw new ContentSyncFailure('The candidate contains an unsupported staged file type. It was left untouched.');
            }

            $mode = $matches[1];
            $blobHash = $matches[2];
            $parts = explode('/', $path);
            if (in_array('', $parts, true) || in_array('.', $parts, true) || in_array('..', $parts, true)) {
                throw new ContentSyncFailure('The pinned candidate tree contains an unsafe path. It was left untouched.');
            }
            $parent = $destination;
            foreach (array_slice($parts, 0, -1) as $part) {
                $parent .= DIRECTORY_SEPARATOR.$part;
                if (is_link($parent) || (file_exists($parent) && ! is_dir($parent))) {
                    throw new ContentSyncFailure('The pinned candidate tree contains a conflicting path. It was left untouched.');
                }
                if (! is_dir($parent) && ! mkdir($parent, 0700) && ! is_dir($parent)) {
                    throw new ContentSyncFailure('Could not export the pinned candidate for validation. The candidate was left untouched.');
                }
            }
            $target = $parent.DIRECTORY_SEPARATOR.end($parts);
            if (file_exists($target) || is_link($target)) {
                throw new ContentSyncFailure('The pinned candidate tree contains a duplicate path. It was left untouched.');
            }

            if ($mode === '120000') {
                $link = $this->readBlob($candidatePath, $blobHash);
                if ($link === '' || str_contains($link, "\0") || ! symlink($link, $target)) {
                    throw new ContentSyncFailure('The candidate contains an unsupported symbolic link. It was left untouched.');
                }

                continue;
            }

            $this->writeBlob($candidatePath, $blobHash, $target);
        }
    }

    private function readBlob(string $candidatePath, string $blobHash): string
    {
        $process = new Process(['git', 'cat-file', 'blob', $blobHash], $candidatePath, null, null, 300);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new ContentSyncFailure('Could not read a pinned candidate file for validation. The candidate was left untouched.');
        }

        return $process->getOutput();
    }

    private function writeBlob(string $candidatePath, string $blobHash, string $target): void
    {
        $handle = @fopen($target, 'xb');
        if (! is_resource($handle)) {
            throw new ContentSyncFailure('Could not export the pinned candidate for validation. The candidate was left untouched.');
        }

        $writeFailed = false;
        try {
            $process = new Process(['git', 'cat-file', 'blob', $blobHash], $candidatePath, null, null, 300);
            $process->disableOutput();
            $process->run(static function (string $type, string $buffer) use ($handle, &$writeFailed): void {
                if ($type !== Process::OUT || $writeFailed) {
                    return;
                }
                $offset = 0;
                $length = strlen($buffer);
                while ($offset < $length) {
                    $written = fwrite($handle, substr($buffer, $offset));
                    if ($written === false || $written === 0) {
                        $writeFailed = true;

                        return;
                    }
                    $offset += $written;
                }
            });
            if (! $process->isSuccessful() || $writeFailed) {
                throw new ContentSyncFailure('Could not export the pinned candidate for validation. The candidate was left untouched.');
            }
        } catch (\Throwable $failure) {
            fclose($handle);
            @unlink($target);
            if ($failure instanceof ContentSyncFailure) {
                throw $failure;
            }
            throw new ContentSyncFailure('Could not export the pinned candidate for validation. The candidate was left untouched.');
        }

        fclose($handle);
        @chmod($target, 0600);
    }

    private function copyTree(string $source, string $target, string $snapshotRoot): void
    {
        $this->assertNoSymlinkAncestors($snapshotRoot, $source);
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
        $targetRoot = realpath($target);
        if (! is_string($sourceRoot) || ! is_string($targetRoot)) {
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
                $sourceTarget = $this->normalizeAbsolutePath(dirname($from).DIRECTORY_SEPARATOR.$link);
                $copyTarget = $this->normalizeAbsolutePath(dirname($to).DIRECTORY_SEPARATOR.$link);
                $resolved = realpath(dirname($from).DIRECTORY_SEPARATOR.$link);
                if (! is_string($sourceTarget) || ! is_string($copyTarget) || ! is_string($resolved)
                    || ! $this->isWithinDirectory($sourceTarget, $sourceRoot)
                    || ! $this->isWithinDirectory($resolved, $sourceRoot)) {
                    throw new ContentSyncFailure('The candidate contains an unsupported symbolic link. It was left untouched.');
                }
                $relativeTarget = ltrim(substr($sourceTarget, strlen($sourceRoot)), DIRECTORY_SEPARATOR);
                $expectedCopyTarget = $relativeTarget === ''
                    ? $targetRoot
                    : $targetRoot.DIRECTORY_SEPARATOR.$relativeTarget;
                if ($copyTarget !== $expectedCopyTarget) {
                    throw new ContentSyncFailure('The candidate contains a symbolic link that would resolve differently in isolated validation. It was left untouched.');
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

    private function normalizeAbsolutePath(string $path): ?string
    {
        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return null;
        }

        $segments = [];
        foreach (explode(DIRECTORY_SEPARATOR, $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
    }

    private function isWithinDirectory(string $path, string $directory): bool
    {
        return $path === $directory || str_starts_with($path, rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }

    private function assertNoSymlinkAncestors(string $snapshotRoot, string $source): void
    {
        $root = rtrim($snapshotRoot, DIRECTORY_SEPARATOR);
        $rootReal = realpath($root);
        if (! is_string($rootReal) || is_link($root)) {
            throw new ContentSyncFailure('The staged candidate root is not a safe directory. The candidate was left untouched.');
        }
        if ($source !== $root && ! str_starts_with($source, $root.DIRECTORY_SEPARATOR)) {
            throw new ContentSyncFailure('The candidate validation path is outside the staged snapshot. It was left untouched.');
        }

        $relative = ltrim(substr($source, strlen($root)), DIRECTORY_SEPARATOR);
        $current = $root;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if ($segment === '') {
                continue;
            }
            $current .= DIRECTORY_SEPARATOR.$segment;
            if (is_link($current)) {
                throw new ContentSyncFailure('The candidate contains an unsupported symbolic link in a validation path. It was left untouched.');
            }
        }

        if (! file_exists($source) && ! is_link($source)) {
            return;
        }
        $resolved = realpath($source);
        if (! is_string($resolved) || ! str_starts_with($resolved, $rootReal.DIRECTORY_SEPARATOR)) {
            throw new ContentSyncFailure('The candidate validation path resolves outside the staged snapshot. It was left untouched.');
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
