<?php

namespace App\Http\Controllers;

use App\Support\CvCapabilities;
use App\Support\CvPdfRenderer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CvPdfController extends Controller
{
    public function __invoke(Request $request, CvPdfRenderer $renderer, ?string $profile = null): BinaryFileResponse
    {
        $path = '/cv'.($profile ? '/'.$profile : '');
        $token = (string) $request->query('token', '');
        if ($token !== '' && ! CvCapabilities::allows($token, $path)) abort(404);
        $pdf = $renderer->render($profile, $token !== '');
        return response()->download($pdf, 'Jack-Ruder-Lebenslauf'.($profile ? '-'.$profile : '').'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ])->deleteFileAfterSend();
    }
}
