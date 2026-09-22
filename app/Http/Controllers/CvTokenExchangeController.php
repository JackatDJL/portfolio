<?php

namespace App\Http\Controllers;

use App\Support\CvCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CvTokenExchangeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $origin = $request->header('Origin');
        if ($origin && $origin !== $request->getSchemeAndHttpHost()) {
            abort(403);
        }
        if ($request->header('Sec-Fetch-Site') === 'cross-site') {
            abort(403);
        }

        $input = $request->validate([
            'path' => ['required', 'regex:#^/cv(?:/[a-z0-9-]+)?$#'],
            'token' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/'],
        ]);
        $path = $input['path'];
        abort_unless(CvCapabilities::allows($input['token'], $path), 404);

        $request->session()->regenerate();
        $request->session()->put('cv_grants.'.hash('sha256', $path), hash('sha256', $input['token']));

        return response()->json(['authorized' => true])
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
