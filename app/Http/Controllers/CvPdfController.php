<?php

namespace App\Http\Controllers;

use App\Support\CvCapabilities;
use App\Support\CvPdfRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CvPdfController extends Controller
{
    public function __invoke(Request $request, CvPdfRenderer $renderer): BinaryFileResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'regex:#^/cv(?:/[a-z0-9-]+)?$#'],
            'token' => ['nullable', 'string', 'max:256'],
        ]);
        $path = (string) $validated['path'];
        $token = (string) ($validated['token'] ?? '');

        if ($token !== '' && ! CvCapabilities::allows($token, $path)) {
            abort(404);
        }

        $pdf = $renderer->render($path, $token !== '' ? $token : null);

        return response()->download($pdf, 'Jack-Ruder-Lebenslauf.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ])->deleteFileAfterSend();
    }
}
