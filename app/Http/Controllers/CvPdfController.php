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
        abort_if($request->query->has('token') || $request->query->has('cv'), 404);
        $authorized = ! $request->boolean('public') && CvCapabilities::sessionAllows($request, $path);
        $capabilityToken = $authorized ? CvCapabilities::privatePdfToken($request, $path) : null;
        abort_if($authorized && $capabilityToken === null, 404);
        $pdf = $renderer->render($profile, $authorized, $capabilityToken);

        return response()->download($pdf, 'Jack-Ruder-Lebenslauf'.($profile ? '-'.$profile : '').'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline; filename="Jack-Ruder-Lebenslauf.pdf"',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ], 'inline')->deleteFileAfterSend();
    }
}
