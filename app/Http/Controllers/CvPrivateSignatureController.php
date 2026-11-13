<?php

namespace App\Http\Controllers;

use App\Support\CvCapabilities;
use App\Support\PrivateHandwrittenSignature;
use Illuminate\Http\Request;
use Statamic\Facades\GlobalSet;

final class CvPrivateSignatureController extends Controller
{
    public function __invoke(Request $request, PrivateHandwrittenSignature $signature)
    {
        $path = (string) $request->input('path', '');
        if (! preg_match('#^/cv(?:/[a-z0-9-]+)?$#', $path) || ! CvCapabilities::sessionAllows($request, $path)) {
            abort(404);
        }

        $cv = GlobalSet::find('cv')?->inDefaultSite();
        if (! $cv || ! filter_var($cv->value('private_cv_signature_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            abort(404);
        }

        $png = $signature->imageBytes();
        abort_if($png === null, 404);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
