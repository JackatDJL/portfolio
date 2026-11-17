<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PortfolioContentSyncStatus
{
    private const MAX_EVENTS = 200;

    public function begin(string $source): string
    {
        return $this->mutate(function (array &$state) use ($source): ?string {
            $runId = (string) Str::uuid();
            $startedAt = now()->utc()->toIso8601String();
            $source = in_array($source, ['cron', 'manual', 'cli'], true) ? $source : 'cli';

            $state = [
                'run_id' => $runId,
                'source' => $source,
                'status' => 'running',
                'started_at' => $startedAt,
                'finished_at' => null,
                'events' => [[
                    'at' => $startedAt,
                    'message' => 'Synchronisierung gestartet ('.$source.').',
                ]],
            ];

            return $runId;
        });
    }

    public function append(?string $runId, string $message): void
    {
        if ($runId === null) {
            return;
        }

        $this->mutate(function (array &$state) use ($runId, $message): void {
            if (($state['run_id'] ?? null) !== $runId || ($state['status'] ?? null) !== 'running') {
                return;
            }

            $state['events'][] = [
                'at' => now()->utc()->toIso8601String(),
                'message' => $this->cleanMessage($message),
            ];
            $state['events'] = array_slice($state['events'], -self::MAX_EVENTS);
        });
    }

    public function finish(?string $runId, string $status, string $message): void
    {
        if ($runId === null) {
            return;
        }

        $this->mutate(function (array &$state) use ($runId, $status, $message): void {
            if (($state['run_id'] ?? null) !== $runId || ($state['status'] ?? null) !== 'running') {
                return;
            }

            $finishedAt = now()->utc()->toIso8601String();
            $state['events'][] = [
                'at' => $finishedAt,
                'message' => $this->cleanMessage($message),
            ];
            $state['events'] = array_slice($state['events'], -self::MAX_EVENTS);
            $state['status'] = $status === 'failed' ? 'failed' : 'completed';
            $state['finished_at'] = $finishedAt;
        });
    }

    public function failBeforeStart(string $source, string $message): void
    {
        $this->mutate(function (array &$state) use ($source, $message): void {
            if (($state['status'] ?? null) === 'running' && $this->lockIsHeld()) {
                return;
            }

            $now = now()->utc()->toIso8601String();
            $safeSource = in_array($source, ['cron', 'manual', 'cli'], true) ? $source : 'cli';
            $state = [
                'run_id' => (string) Str::uuid(),
                'source' => $safeSource,
                'status' => 'failed',
                'started_at' => $now,
                'finished_at' => $now,
                'events' => [
                    ['at' => $now, 'message' => 'Synchronisierung gestartet ('.$safeSource.').'],
                    ['at' => $now, 'message' => $this->cleanMessage($message)],
                ],
            ];
        });
    }

    /** @return array{run: ?array, next_run_at: string, interval_minutes: int} */
    public function snapshot(): array
    {
        $state = $this->read();
        if (($state['status'] ?? null) === 'running' && ! $this->lockIsHeld()) {
            $state['status'] = 'interrupted';
        }

        $now = now()->utc();
        $minutesToNextRun = 5 - ((int) $now->format('i') % 5);

        return [
            'run' => $state === [] ? null : $state,
            'next_run_at' => $now->copy()->startOfMinute()->addMinutes($minutesToNextRun)->toIso8601String(),
            'interval_minutes' => 5,
        ];
    }

    private function lockIsHeld(): bool
    {
        $path = (string) config('content-sync.lock_path', storage_path('framework/locks/content-sync.lock'));
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0770, true) && ! is_dir($directory)) {
            return false;
        }

        $lock = @fopen($path, 'c');
        if (! is_resource($lock)) {
            return false;
        }

        $held = ! flock($lock, LOCK_EX | LOCK_NB);
        if (! $held) {
            flock($lock, LOCK_UN);
        }
        fclose($lock);

        return $held;
    }

    private function cleanMessage(string $message): string
    {
        return mb_substr(trim((string) preg_replace('/[\r\n]+/', ' ', $message)), 0, 700);
    }

    private function path(): string
    {
        return (string) config('content-sync.status_path', storage_path('app/content-sync-status.json'));
    }

    private function read(): array
    {
        $path = $this->path();
        $lock = @fopen($path.'.lock', 'c');
        if (! is_resource($lock)) {
            return [];
        }

        try {
            if (! flock($lock, LOCK_SH)) {
                return [];
            }

            return $this->readUnlocked();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function readUnlocked(): array
    {
        $contents = @file_get_contents($this->path());
        if (! is_string($contents) || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function mutate(callable $callback): mixed
    {
        $path = $this->path();
        $directory = dirname($path);
        if (! is_dir($directory) && ! @mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Could not create the content synchronization status directory.');
        }

        $lock = @fopen($path.'.lock', 'c');
        if (! is_resource($lock)) {
            throw new \RuntimeException('Could not open the content synchronization status lock.');
        }
        @chmod($path.'.lock', 0600);

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Could not lock content synchronization status.');
            }

            $state = $this->readUnlocked();
            $result = $callback($state);
            $encoded = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (file_put_contents($path, $encoded, LOCK_EX) === false) {
                throw new \RuntimeException('Could not persist content synchronization status.');
            }
            @chmod($path, 0600);

            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
