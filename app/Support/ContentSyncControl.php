<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

use function Illuminate\Support\php_binary;

final class ContentSyncControl
{
    private const MAX_LOG_BYTES = 1_048_576;

    private const LOG_TAIL_BYTES = 65_536;

    public function status(): array
    {
        $state = $this->pruneState();
        $activeRuns = $this->liveRuns($state);
        uasort($activeRuns, fn (array $left, array $right): int => ($left['started_at'] ?? '') <=> ($right['started_at'] ?? ''));

        $writerIsActive = $this->writerIsActive();
        $displayRuns = $activeRuns === [] && $writerIsActive ? $state['runs'] : $activeRuns;
        uasort($displayRuns, fn (array $left, array $right): int => ($left['started_at'] ?? '') <=> ($right['started_at'] ?? ''));

        return [
            'running' => $writerIsActive || $activeRuns !== [],
            'started_at' => $displayRuns === [] ? null : reset($displayRuns)['started_at'],
            'next_run_at' => CarbonImmutable::now()->startOfMinute()->addMinutes(5 - (int) now()->minute % 5)->toIso8601String(),
            'logs' => $this->logs(),
        ];
    }

    public function start(): bool
    {
        $stateLock = $this->acquireStateLock();
        try {
            $state = $this->readState();
            $this->pruneStaleRuns($state);
            $this->writeState($state);
            if ($state['runs'] !== [] || $this->writerIsActive()) {
                return false;
            }

            $logPath = (string) config('content-sync.control_log_path');
            File::ensureDirectoryExists(dirname($logPath));
            $command = 'nohup /usr/bin/flock -s -F '.escapeshellarg($this->logLockPath($logPath)).' '.escapeshellarg(php_binary()).' '.escapeshellarg(base_path('artisan')).' portfolio:content-sync >> '.escapeshellarg($logPath).' 2>&1 < /dev/null & echo $!';
            $process = proc_open(['/bin/sh', '-c', $command], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) {
                return false;
            }
            fclose($pipes[0]);
            $pid = (int) trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            if ($pid < 1) {
                return false;
            }

            $state = $this->readState();
            $state['runs'][(string) $pid] = [
                'started_at' => now()->toIso8601String(),
                'process_identity' => $this->processIdentity($pid),
            ];
            $this->writeState($state);

            return true;
        } finally {
            flock($stateLock, LOCK_UN);
            fclose($stateLock);
        }
    }

    public function begin(?int $pid = null): int
    {
        $pid ??= getmypid();
        $this->mutateState(function (array &$state) use ($pid): void {
            $this->pruneStaleRuns($state);
            $state['runs'][(string) $pid] ??= [
                'started_at' => now()->toIso8601String(),
                'process_identity' => $this->processIdentity($pid),
            ];
        });
        $this->append('Lauf gestartet.');

        return $pid;
    }

    public function finish(string $message, ?int $pid = null): void
    {
        $pid ??= getmypid();
        $this->append($message);
        $this->mutateState(function (array &$state) use ($pid): void {
            unset($state['runs'][(string) $pid]);
            $this->pruneStaleRuns($state);
        });
    }

    public function log(string $message): void
    {
        $this->append($message);
    }

    /** @return array{runs: array<string, array{started_at: string|null, process_identity?: string|null}>} */
    private function readState(): array
    {
        $path = (string) config('content-sync.control_state_path');
        if (! is_file($path)) {
            return ['runs' => []];
        }
        try {
            $state = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) ?: [];
        } catch (\Throwable) {
            return ['runs' => []];
        }

        if (isset($state['runs']) && is_array($state['runs'])) {
            return ['runs' => $state['runs']];
        }
        if (isset($state['pid'])) {
            return ['runs' => [(string) $state['pid'] => ['started_at' => $state['started_at'] ?? null]]];
        }

        return ['runs' => []];
    }

    private function writeState(array $state): void
    {
        $path = (string) config('content-sync.control_state_path');
        if ($state['runs'] === []) {
            File::delete($path);

            return;
        }
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        file_put_contents($temporary, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX);
        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Could not atomically update the content sync state.');
        }
    }

    private function mutateState(callable $mutation): void
    {
        $lock = $this->acquireStateLock();
        try {
            $state = $this->readState();
            $mutation($state);
            $this->writeState($state);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return resource */
    private function acquireStateLock()
    {
        $path = (string) config('content-sync.control_state_path');
        File::ensureDirectoryExists(dirname($path));
        $lock = fopen($path.'.lock', 'c');
        if (! is_resource($lock)) {
            throw new \RuntimeException('Could not open the content sync state lock.');
        }
        flock($lock, LOCK_EX);

        return $lock;
    }

    private function liveRuns(array $state): array
    {
        return array_filter($state['runs'], fn (array $run, string|int $pid): bool => $this->processIsRunning((int) $pid, $run), ARRAY_FILTER_USE_BOTH);
    }

    private function pruneState(): array
    {
        $lock = $this->acquireStateLock();
        try {
            $state = $this->readState();
            $before = count($state['runs']);
            $this->pruneStaleRuns($state);
            if (count($state['runs']) !== $before) {
                $this->writeState($state);
            }

            return $state;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function pruneStaleRuns(array &$state): void
    {
        foreach ($state['runs'] as $pid => $run) {
            if (! $this->processIsRunning((int) $pid, $run)) {
                unset($state['runs'][$pid]);
            }
        }
    }

    private function processIsRunning(int $pid, array $run): bool
    {
        $recordedIdentity = $run['process_identity'] ?? null;
        $currentIdentity = $this->processIdentity($pid);

        return is_string($recordedIdentity)
            && $recordedIdentity !== ''
            && is_string($currentIdentity)
            && hash_equals($recordedIdentity, $currentIdentity);
    }

    private function processIdentity(int $pid): ?string
    {
        if ($pid < 1) {
            return null;
        }
        $stat = @file_get_contents('/proc/'.$pid.'/stat');
        $closingParenthesis = is_string($stat) ? strrpos($stat, ')') : false;
        if (! is_string($stat) || $closingParenthesis === false) {
            return null;
        }

        $fields = preg_split('/\s+/', trim(substr($stat, $closingParenthesis + 1)));
        $startTime = $fields[19] ?? null;
        $bootId = @file_get_contents('/proc/sys/kernel/random/boot_id');
        if (! is_string($startTime) || ! is_string($bootId) || trim($bootId) === '') {
            return null;
        }

        return trim($bootId).':'.$startTime;
    }

    private function writerIsActive(): bool
    {
        $lock = @fopen((string) config('content-sync.lock_path'), 'c');
        $active = is_resource($lock) && ! flock($lock, LOCK_EX | LOCK_NB);
        if (is_resource($lock)) {
            if (! $active) {
                flock($lock, LOCK_UN);
            }
            fclose($lock);
        }

        return $active;
    }

    private function logs(): string
    {
        $path = (string) config('content-sync.control_log_path');
        if (! is_file($path)) {
            return '';
        }
        $this->trimLog($path);
        $handle = @fopen($path, 'rb');
        if (! is_resource($handle)) {
            return '';
        }
        $size = fstat($handle)['size'] ?? 0;
        $offset = max(0, $size - self::LOG_TAIL_BYTES);
        if ($offset > 0) {
            fseek($handle, $offset);
            fgets($handle);
        }
        $tail = stream_get_contents($handle, self::LOG_TAIL_BYTES);
        fclose($handle);
        $lines = preg_split('/\R/', (string) $tail) ?: [];

        return implode("\n", array_slice($lines, -120));
    }

    private function trimLog(string $path): void
    {
        $coordinationLock = @fopen($this->logLockPath($path), 'c');
        if (! is_resource($coordinationLock)) {
            return;
        }
        if (! flock($coordinationLock, LOCK_EX | LOCK_NB)) {
            fclose($coordinationLock);

            return;
        }

        $handle = @fopen($path, 'c+');
        if (! is_resource($handle)) {
            flock($coordinationLock, LOCK_UN);
            fclose($coordinationLock);

            return;
        }
        if (! flock($handle, LOCK_EX)) {
            fclose($handle);
            flock($coordinationLock, LOCK_UN);
            fclose($coordinationLock);

            return;
        }

        try {
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
            flock($coordinationLock, LOCK_UN);
            fclose($coordinationLock);
        }
    }

    private function append(string $line): void
    {
        $path = (string) config('content-sync.control_log_path');
        File::ensureDirectoryExists(dirname($path));
        $coordinationLock = fopen($this->logLockPath($path), 'c');
        if (! is_resource($coordinationLock)) {
            throw new \RuntimeException('Could not open the content sync log lock.');
        }
        flock($coordinationLock, LOCK_SH);
        try {
            file_put_contents($path, '['.now()->toIso8601String().'] '.$line.PHP_EOL, FILE_APPEND | LOCK_EX);
        } finally {
            flock($coordinationLock, LOCK_UN);
            fclose($coordinationLock);
        }
        $this->trimLog($path);
    }

    private function logLockPath(string $path): string
    {
        return $path.'.lock';
    }
}
