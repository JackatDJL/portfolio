<?php

namespace App\Http\Controllers;

use App\Support\CvCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CvPrivateLinkController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $path = $this->path($request);
        $records = \Illuminate\Support\Facades\DB::table('cv_access_tokens')->where('profile_path', $path)->whereNull('revoked_at')->orderByDesc('id')->get();
        $links = [];
        foreach ($records as $record) {
            if (isset($links[$record->kind]) || ($record->expires_at && now()->isAfter($record->expires_at))) continue;
            try { $url = url($path).'?cv='.\Illuminate\Support\Facades\Crypt::decryptString($record->encrypted_token); }
            catch (\Illuminate\Contracts\Encryption\DecryptException) { $url = null; }
            $links[$record->kind] = ['url' => $url, 'expires_at' => $record->expires_at];
        }
        return response()->json(['path' => $path, 'links' => $links])->header('Cache-Control', 'private, no-store');
    }

    public function temporary(Request $request): JsonResponse
    {
        $path = $this->path($request);

        if ($request->boolean('replace')) \Illuminate\Support\Facades\DB::table('cv_access_tokens')->where('profile_path', $path)->where('kind', 'temporary')->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        return $this->response($path, CvCapabilities::issueTemporary($path));
    }

    public function permanent(Request $request): JsonResponse
    {
        $path = $this->path($request);

        return $this->response($path, CvCapabilities::permanent($path, $request->boolean('replace')));
    }

    public function revoke(Request $request): JsonResponse
    {
        $path = $this->path($request);

        return response()->json(['revoked' => CvCapabilities::revokePermanent($path)])->header('Cache-Control', 'private, no-store');
    }

    private function path(Request $request): string
    {
        $path = (string) $request->input('path', '/cv');
        abort_unless(preg_match('#^/cv(?:/[a-z0-9-]+)?$#', $path), 404);

        if ($path !== '/cv') {
            $identifier = basename($path);
            $profile = \Statamic\Facades\Entry::query()->where('collection', 'cv_profiles')->where('slug', $identifier)->first()
                ?? \Statamic\Facades\Entry::find($identifier);
            abort_unless($profile && $profile->collectionHandle() === 'cv_profiles', 404);
            $path = '/cv/'.$profile->slug();
        }
        return $path;
    }

    private function response(string $path, array $capability): JsonResponse
    {
        return response()->json([
            'url' => url($path).'?cv='.$capability['token'],
            'identifier' => $capability['identifier'],
            'kind' => $capability['kind'],
            'expires_at' => $capability['expires_at'],
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
