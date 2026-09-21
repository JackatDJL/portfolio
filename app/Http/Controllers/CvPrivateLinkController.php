<?php

namespace App\Http\Controllers;

use App\Support\CvCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CvPrivateLinkController extends Controller
{
    public function temporary(Request $request): JsonResponse
    {
        $path = $this->path($request);

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

        return $path;
    }

    private function response(string $path, array $capability): JsonResponse
    {
        return response()->json([
            'url' => url($path).'#cv='.$capability['token'],
            'identifier' => $capability['identifier'],
            'kind' => $capability['kind'],
            'expires_at' => $capability['expires_at'],
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
