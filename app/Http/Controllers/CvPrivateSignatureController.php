<?php

namespace App\Http\Controllers;

use App\Support\ApplicationData;
use App\Support\CvNavigation;
use App\Support\PrivateHandwrittenSignature;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;

final class CvPrivateSignatureController extends Controller
{
    public function __invoke(Request $request, PrivateHandwrittenSignature $signature)
    {
        $path = (string) $request->input('path', '');
        if (! CvNavigation::authorized($request, $path)) {
            abort(404);
        }

        $cv = GlobalSet::find('cv')?->inDefaultSite();
        $id = CvNavigation::applicationId($path);
        $enabled = $id ? (ApplicationData::group(Entry::find($id), 'signature')['mode'] ?? 'none') === 'handwritten' : ($cv && filter_var($cv->value('private_cv_signature_enabled', false), FILTER_VALIDATE_BOOLEAN) && ($cv->value('private_cv_signature_mode') ?? 'handwritten') === 'handwritten');
        if (! $enabled) {
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
