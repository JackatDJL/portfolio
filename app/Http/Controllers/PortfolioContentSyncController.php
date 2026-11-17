<?php

namespace App\Http\Controllers;

use App\Support\PortfolioContentSyncStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Statamic\Facades\User;

class PortfolioContentSyncController extends Controller
{
    public function index(PortfolioContentSyncStatus $status)
    {
        $this->authorizeSync();

        return response()->view('cp.content-sync', ['sync' => $status->snapshot()])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function status(PortfolioContentSyncStatus $status): JsonResponse
    {
        $this->authorizeSync();

        return response()->json($status->snapshot())
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function run(PortfolioContentSyncStatus $status): JsonResponse
    {
        $this->authorizeSync();

        Artisan::call('portfolio:content-sync', ['--source' => 'manual']);
        $output = Artisan::output();

        if (str_contains($output, 'Another content synchronization is already running; skipped.')) {
            return response()->json($status->snapshot(), 409)
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return response()->json($status->snapshot())
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    private function authorizeSync(): void
    {
        abort_unless(User::current()?->isSuper(), 403);
    }
}
