<?php

namespace App\Http\Controllers;

use App\Support\ContentSyncControl;
use App\Support\ContentSyncFailure;
use App\Support\PortfolioContentSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class ContentSyncControlController
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuper(), 403);

        return view('cp.content-sync');
    }

    public function status(Request $request, ContentSyncControl $control, PortfolioContentSync $sync): JsonResponse
    {
        abort_unless($request->user()?->isSuper(), 403);
        $status = $control->status();
        try {
            $status['conflicts'] = $sync->conflictSummary();
        } catch (ContentSyncFailure) {
            $status['conflicts'] = ['busy' => true, 'merge_in_progress' => false, 'files' => []];
        }

        return response()->json($status)->header('Cache-Control', 'no-store');
    }

    public function start(Request $request, ContentSyncControl $control): JsonResponse
    {
        abort_unless($request->user()?->isSuper(), 403);
        $started = $control->start();

        return response()->json(['started' => $started], $started ? 202 : 409)
            ->header('Cache-Control', 'no-store');
    }

    public function conflicts(Request $request, PortfolioContentSync $sync): JsonResponse
    {
        abort_unless($request->user()?->isSuper(), 403);

        return response()->json($sync->conflictDetails())->header('Cache-Control', 'no-store');
    }

    public function resolve(Request $request, PortfolioContentSync $sync, ContentSyncControl $control): JsonResponse
    {
        abort_unless($request->user()?->isSuper(), 403);
        $payload = json_decode($request->getContent(), true);
        abort_unless(is_array($payload), 422, 'A JSON conflict resolution payload is required.');
        $input = Validator::make($payload, [
            'fingerprint' => ['required', 'string', 'size:64'],
            'resolutions' => ['required', 'array', 'min:1'],
            'resolutions.*.path' => ['required', 'string', 'max:1024'],
            'resolutions.*.choice' => ['nullable', 'in:ours,theirs'],
            'resolutions.*.sections' => ['sometimes', 'array'],
            'resolutions.*.sections.*.id' => ['required', 'integer', 'min:0'],
            'resolutions.*.sections.*.choice' => ['required', 'in:ours,theirs,manual'],
            'resolutions.*.sections.*.text' => ['nullable', 'string', 'max:262144'],
        ])->validate();

        $resolutions = [];
        foreach ($input['resolutions'] as $resolution) {
            $path = $resolution['path'];
            if (array_key_exists($path, $resolutions)) {
                abort(422, 'The candidate conflict request contains a duplicate file.');
            }
            $resolutions[$path] = $resolution;
            unset($resolutions[$path]['path']);
        }

        $pid = $control->begin();
        $control->log('Kandidatenkonflikt wird abschnittsweise validiert.');
        try {
            $result = $sync->resolveConflicts($input['fingerprint'], $resolutions);
            if ($result['status'] === 'busy') {
                $control->finish('Konfliktauflösung übersprungen: Ein anderer Sync-Lauf hält bereits den Lock.', $pid);

                return response()->json(['resolved' => false, 'started' => false], 409)
                    ->header('Cache-Control', 'no-store');
            }

            $control->finish('Konflikt validiert; die reguläre Synchronisierung wird gestartet.', $pid);
            $started = $control->start();

            return response()->json(['resolved' => true, 'started' => $started], 202)
                ->header('Cache-Control', 'no-store');
        } catch (ContentSyncFailure $failure) {
            $control->finish('Konfliktauflösung fehlgeschlagen. Kandidat und Live-Content bleiben getrennt.', $pid);
            throw $failure;
        } catch (\Throwable $failure) {
            $control->finish('Konfliktauflösung fehlgeschlagen. Kandidat und Live-Content bleiben getrennt.', $pid);
            throw $failure;
        }
    }
}
