<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

final class ContentSyncControl
{
    public function status(): array
    {
        $state = $this->readState();
        $pid = $state['pid'] ?? null;
        $lock = @fopen((string) config('content-sync.lock_path'), 'c');
        $running = is_resource($lock) && ! flock($lock, LOCK_EX | LOCK_NB);
        if (is_resource($lock)) {
            if (! $running) flock($lock, LOCK_UN);
            fclose($lock);
        }
        $running = $running || (is_int($pid) && function_exists('posix_kill') && @posix_kill($pid, 0));

        return [
            'running' => $running,
            'started_at' => $state['started_at'] ?? null,
            'next_run_at' => CarbonImmutable::now()->startOfMinute()->addMinutes(5 - (int) now()->minute % 5)->toIso8601String(),
            'logs' => $this->logs(),
        ];
    }

    public function start(): bool
    {
        $statePath = (string) config('content-sync.control_state_path');
        File::ensureDirectoryExists(dirname($statePath));
        $lockPath = $statePath.'.lock';
        $lock = fopen($lockPath, 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            return false;
        }

        try {
            $state = $this->readState();
            if (is_int($state['pid'] ?? null) && function_exists('posix_kill') && @posix_kill($state['pid'], 0)) {
                return false;
            }

            $logPath = (string) config('content-sync.control_log_path');
            File::ensureDirectoryExists(dirname($logPath));
            $command = 'nohup '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(base_path('artisan')).' portfolio:content-sync >> '.escapeshellarg($logPath).' 2>&1 < /dev/null & echo $!';
            $process = proc_open(['/bin/sh', '-c', $command], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) return false;
            fclose($pipes[0]);
            $pid = (int) trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
            if ($pid < 1) return false;
            File::put($statePath, json_encode(['pid' => $pid, 'started_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR));
            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function begin(): void
    {
        File::ensureDirectoryExists(dirname((string) config('content-sync.control_state_path')));
        File::put((string) config('content-sync.control_state_path'), json_encode(['pid' => getmypid(), 'started_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR));
        $this->append('Lauf gestartet.');
    }

    public function finish(string $message): void
    {
        $this->append($message);
        $path = (string) config('content-sync.control_state_path');
        $state = $this->readState();
        if (($state['pid'] ?? null) === getmypid()) File::delete($path);
    }

    public function log(string $message): void
    {
        $this->append($message);
    }

    private function readState(): array
    {
        $path = (string) config('content-sync.control_state_path');
        if (! is_file($path)) return [];
        try { return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) ?: []; }
        catch (\Throwable) { return []; }
    }

    private function logs(): string
    {
        $path = (string) config('content-sync.control_log_path');
        if (! is_file($path)) return '';
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        return implode("\n", array_slice($lines, -120));
    }

    private function append(string $line): void
    {
        file_put_contents((string) config('content-sync.control_log_path'), '['.now()->toIso8601String().'] '.$line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
