<?php

namespace App\Http\Controllers;

use App\Support\CvCapabilities;
use App\Support\CvPrivateData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Statamic\Facades\GlobalSet;

class CvPrivateDataController extends Controller
{
    public function reveal(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');
        $path = (string) $request->input('path', '/cv');

        if (! preg_match('#^/cv(?:/[a-z0-9-]+)?$#', $path) || ! CvCapabilities::allows($token, $path)) {
            abort(404);
        }

        $variables = GlobalSet::find('cv')?->inDefaultSite();
        if ($variables === null) {
            abort(404);
        }

        $data = CvPrivateData::reveal($variables);
        if (isset($data['date_of_birth'])) {
            $data['date_of_birth'] = Carbon::parse($data['date_of_birth'])->format('d.m.Y');
        }

        return response()->json($data)
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
