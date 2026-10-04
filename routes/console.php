<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Support\ContentSyncFailure;
use App\Support\PortfolioContentSync;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('portfolio:content-sync {--dry-run : Report pending repository state without committing, fetching, or merging}', function (PortfolioContentSync $sync): int {
    try {
        $result = $sync->run((bool) $this->option('dry-run'));
    } catch (ContentSyncFailure $failure) {
        $context = ['conflicting_paths' => $failure->conflictingPaths];
        if ($failure->conflictingPaths !== []) {
            Log::critical('Portfolio content synchronization stopped at a Git conflict.', $context);
        } else {
            Log::error('Portfolio content synchronization failed.', ['reason' => $failure->getMessage()]);
        }
        $this->error($failure->getMessage());

        return self::FAILURE;
    } catch (\Throwable $failure) {
        Log::error('Portfolio content synchronization failed unexpectedly.', ['exception' => $failure::class]);
        $this->error('Content synchronization failed unexpectedly. Check the application log for the exception type.');

        return self::FAILURE;
    }

    if ($result['status'] === 'busy') {
        $this->comment('Another content synchronization is already running; skipped.');
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

    return self::SUCCESS;
})->purpose('Safely reconcile Statamic editorial content with Git');
