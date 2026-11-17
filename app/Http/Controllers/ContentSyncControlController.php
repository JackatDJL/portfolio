<?php

namespace App\Http\Controllers;

use App\Support\ContentSyncControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ContentSyncControlController
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuper(), 403);
        return view('cp.content-sync');
    }

    public function status(Request $request, ContentSyncControl $control): JsonResponse
    {
        abort_unless($request->user()?->isSuper(), 403);
        return response()->json($control->status())->header('Cache-Control', 'no-store');
    }

    public function start(Request $request, ContentSyncControl $control): JsonResponse
    {
        abort_unless($request->user()?->isSuper(), 403);
        $started = $control->start();
        return response()->json(['started' => $started], $started ? 202 : 409)
            ->header('Cache-Control', 'no-store');
    }
}
