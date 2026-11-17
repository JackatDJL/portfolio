<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Support\ContentSyncFailure;
use App\Support\PortfolioContentSync;
use App\Support\PortfolioContentSyncStatus;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('portfolio:content-sync {--dry-run : Report pending repository state without committing, fetching, or merging} {--source=cli : Identify cron, manual, or CLI runs}', function (PortfolioContentSync $sync, PortfolioContentSyncStatus $status): int {
    $runId = null;
    $source = (string) $this->option('source');
    $writeStatus = function (callable $operation): void {
        try {
            $operation();
        } catch (\Throwable $failure) {
            Log::warning('Could not persist portfolio content synchronization status.', [
                'exception' => $failure::class,
            ]);
        }
    };

    try {
        $result = $sync->run(
            (bool) $this->option('dry-run'),
            function () use ($status, $source, &$runId, $writeStatus): void {
                $writeStatus(function () use ($status, $source, &$runId): void {
                    $runId = $status->begin($source);
                });
            },
            function (string $message) use ($status, &$runId, $writeStatus): void {
                $writeStatus(fn () => $status->append($runId, $message));
                $this->line($message);
            },
        );
    } catch (ContentSyncFailure $failure) {
        $context = ['conflicting_paths' => $failure->conflictingPaths];
        if ($failure->conflictingPaths !== []) {
            Log::critical('Portfolio content synchronization stopped at a Git conflict.', $context);
        } else {
            Log::error('Portfolio content synchronization failed.', ['reason' => $failure->getMessage()]);
        }
        if ($runId !== null) {
            $writeStatus(fn () => $status->finish($runId, 'failed', $failure->getMessage()));
        } else {
            $writeStatus(fn () => $status->failBeforeStart($source, $failure->getMessage()));
        }
        $this->error($failure->getMessage());

        return self::FAILURE;
    } catch (\Throwable $failure) {
        Log::error('Portfolio content synchronization failed unexpectedly.', ['exception' => $failure::class]);
        if ($runId !== null) {
            $writeStatus(fn () => $status->finish($runId, 'failed', 'Unerwarteter Fehler: '.$failure::class));
        } else {
            $writeStatus(fn () => $status->failBeforeStart($source, 'Unerwarteter Fehler: '.$failure::class));
        }
        $this->error('Content synchronization failed unexpectedly. Check the application log for the exception type.');

        return self::FAILURE;
    }

    if ($result['status'] === 'busy') {
        $this->comment('Another content synchronization is already running; skipped.');
        if ($runId !== null) {
            $writeStatus(fn () => $status->finish($runId, 'failed', 'Ein anderer Sync-Lauf ist bereits aktiv.'));
        }
    } elseif ($result['status'] === 'dry-run') {
        $this->line(sprintf(
            'Dry run: %d local editorial path(s); remote main %s; remote content-sync %s. No repository changes made.',
            $result['pending_editorial_paths'],
            $result['remote_main'],
            $result['remote_content_sync'],
        ));
    } elseif ($result['status'] === 'noop') {
        $this->info('Content synchronization is up to date.');
    } else {
        $this->info('Content synchronization completed.');
    }

    if ($runId !== null && $result['status'] !== 'busy') {
        $message = match ($result['status']) {
            'noop' => 'Keine Änderungen gefunden; der Content-Branch ist aktuell.',
            'dry-run' => 'Trockenlauf abgeschlossen.',
            default => 'Synchronisierung abgeschlossen.',
        };
        $writeStatus(fn () => $status->finish($runId, 'completed', $message));
    }

    return self::SUCCESS;
})->purpose('Safely reconcile Statamic editorial content with Git');
