<?php

namespace App\Http\Controllers;

use App\Support\ApplicationData;
use App\Support\CvNavigation;
use App\Support\CvPrivateData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;

class CvPrivateDataController extends Controller
{
    public function reveal(Request $request): JsonResponse
    {
        $path = (string) $request->input('path', '/cv');

        if (! CvNavigation::authorized($request, $path)) {
            abort(404);
        }

        $variables = GlobalSet::find('cv')?->inDefaultSite();
        if ($variables === null) {
            abort(404);
        }

        $data = CvPrivateData::reveal($variables);
        $id = CvNavigation::applicationId($path);
        if ($id) {
            $data = Arr::only($data, ['private_email', 'phone', 'street', 'house_number', 'postal_code', 'city', 'country']);
        }
        $settings = $id ? ApplicationData::group(Entry::find($id), 'signature') : [];
        $data['signature_mode'] = $id ? ($settings['mode'] ?? 'none') : (filter_var($variables->value('private_cv_signature_enabled', false), FILTER_VALIDATE_BOOLEAN) ? ($variables->value('private_cv_signature_mode') ?? 'handwritten') : 'none');
        if ($id && $data['signature_mode'] === 'typed') {
            $data['signature_name'] = (string) ($settings['name'] ?? '');
        }
        if (isset($data['date_of_birth'])) {
            $data['date_of_birth'] = Carbon::parse($data['date_of_birth'])->format('d.m.Y');
        }

        return response()->json($data)
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
