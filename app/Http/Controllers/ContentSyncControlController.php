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
        } catch (ContentSyncFailure $failure) {
            $status['conflicts'] = [
                'busy' => false,
                'merge_in_progress' => false,
                'unavailable' => true,
                'recovery_required' => $failure->recoveryRequired,
                'files' => [],
            ];
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
            'resolutions.*.mode_choice' => ['nullable', 'in:ours,theirs'],
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
        try {
            $control->log('Kandidatenkonflikt wird abschnittsweise validiert.');
            $result = $sync->resolveConflicts($input['fingerprint'], $resolutions);
        } catch (\Throwable $failure) {
            try {
                $control->finish('Konfliktauflösung fehlgeschlagen. Kandidat und Live-Content bleiben getrennt.', $pid);
            } catch (\Throwable) {
                // Keep the original resolution failure as the response cause.
            }

            if ($failure instanceof ContentSyncFailure && $failure->recoveryRequired) {
                return response()->json([
                    'resolved' => false,
                    'started' => false,
                    'recovery_required' => true,
                    'message' => 'Der Kandidaten-Rollback ist unvollständig. Der Kandidat muss manuell geprüft werden; ein Sync-Start bleibt gesperrt.',
                ], 409)->header('Cache-Control', 'no-store');
            }

            throw $failure;
        }

        if ($result['status'] === 'busy') {
            $control->finish('Konfliktauflösung übersprungen: Ein anderer Sync-Lauf hält bereits den Lock.', $pid);

            return response()->json(['resolved' => false, 'started' => false], 409)
                ->header('Cache-Control', 'no-store');
        }

        try {
            $control->finish('Konflikt validiert; die reguläre Synchronisierung wird gestartet.', $pid);
        } catch (\Throwable) {
            return response()->json([
                'resolved' => true,
                'started' => false,
                'start_failed' => true,
                'message' => 'Konflikt aufgelöst. Der reguläre Sync-Start konnte nicht vorbereitet werden; bitte manuell erneut starten.',
            ], 202)->header('Cache-Control', 'no-store');
        }

        try {
            $started = $control->start();
        } catch (\Throwable) {
            try {
                $control->log('Konflikt aufgelöst; der reguläre Sync-Start ist fehlgeschlagen. Bitte manuell erneut starten.');
            } catch (\Throwable) {
                // The response still distinguishes a completed resolution from a failed launch.
            }

            return response()->json([
                'resolved' => true,
                'started' => false,
                'start_failed' => true,
                'message' => 'Konflikt aufgelöst. Die Synchronisierung konnte nicht gestartet werden; bitte manuell erneut starten.',
            ], 202)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'resolved' => true,
            'started' => $started,
            'message' => $started
                ? 'Kandidat validiert. Die reguläre Synchronisierung läuft.'
                : 'Konflikt aufgelöst. Starte die reguläre Synchronisierung mit dem Button oben.',
        ], 202)->header('Cache-Control', 'no-store');
    }
}
