<?php

namespace App\Http\Controllers;

use App\Support\CvPdfRenderer;
use App\Support\LatexCompilationFailure;
use App\Support\PdfCompilerControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class PdfCompilerControlController
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuper(), 403);

        return view('cp.pdf-compiler');
    }

    public function status(Request $request, PdfCompilerControl $control): JsonResponse
    {
        abort_unless($request->user()?->isSuper(), 403);

        return response()->json($control->status())->header('Cache-Control', 'no-store');
    }

    public function recompilePublic(Request $request, CvPdfRenderer $renderer, PdfCompilerControl $control): JsonResponse
    {
        abort_unless($request->user()?->isSuper(), 403);
        $control->record('public_recompile_started', ['operation' => 'public_cv']);

        try {
            $renderer->recompilePublic();
        } catch (Throwable $failure) {
            $reference = $failure instanceof LatexCompilationFailure
                ? $failure->reference
                : bin2hex(random_bytes(6));
            $context = [
                'reference' => $reference,
                'operation' => 'public_cv',
                'stage' => 'Compiler',
                'failure_class' => $failure::class,
            ];
            if ($failure instanceof LatexCompilationFailure) {
                $context += [
                    'diagnostic' => $failure->diagnosticCode,
                    'source_line' => $failure->sourceLine,
                    'pass' => $failure->pass,
                    'exit_code' => $failure->exitCode,
                ];
            }
            $control->record('public_recompile_failed', $context);

            return response()->json([
                'message' => 'Der öffentliche Lebenslauf konnte nicht neu kompiliert werden. Siehe PDF-Compiler-Logs.',
                'reference' => $reference,
            ], 503)->header('Cache-Control', 'no-store');
        }

        $control->record('public_recompile_finished', ['operation' => 'public_cv']);

        return response()->json(['message' => 'Der öffentliche Lebenslauf wurde neu kompiliert.'], 200)
            ->header('Cache-Control', 'no-store');
    }

    public function clearCache(Request $request, PdfCompilerControl $control): JsonResponse
    {
        abort_unless($request->user()?->isSuper(), 403);

        try {
            $result = $control->clearCaches();
        } catch (Throwable $failure) {
            $control->record('cache_clear_failed', [
                'operation' => 'pdf',
                'failure_class' => $failure::class,
            ]);

            return response()->json(['message' => 'PDF-Cache konnte nicht geleert werden.'], 500)
                ->header('Cache-Control', 'no-store');
        }

        if ($result['busy']) {
            return response()->json([
                'message' => 'Eine PDF-Kompilierung läuft gerade. Bitte den Cache anschließend erneut leeren.',
            ], 409)->header('Cache-Control', 'no-store');
        }

        $control->record('cache_cleared', [
            'operation' => 'pdf',
            'pdfs_removed' => $result['pdfs_removed'],
            'bytes_removed' => $result['bytes_removed'],
        ]);

        return response()->json([
            'message' => sprintf('%d PDF-Dateien und %s Cache-Daten wurden entfernt.', $result['pdfs_removed'], number_format($result['bytes_removed'], 0, ',', '.').' Byte'),
        ])->header('Cache-Control', 'no-store');
    }
}
