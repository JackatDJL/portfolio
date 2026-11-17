<?php

namespace App\Http\Controllers;

use App\Support\CvCapabilities;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

class CvPrivateLinkController extends Controller
{
    public function grants(Request $request): JsonResponse
    {
        abort_unless(User::current()?->isSuper(), 403);
        $path = $this->path($request);
        return response()->json(['path' => $path, 'grants' => CvCapabilities::namedGrants($path)])
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function issueGrant(Request $request): JsonResponse
    {
        abort_unless(User::current()?->isSuper(), 403);
        $input = $request->validate([
            'path' => ['required', 'regex:#^/cv(?:/[a-z0-9-]+)?$#'],
            'label' => ['required', 'string', 'min:1', 'max:140'],
            'kind' => ['required', 'in:temporary,permanent'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
        ]);
        $path = $this->path($request);
        if ($path !== '/cv') {
            $profile = Entry::query()->where('collection', 'cv_profiles')->where('slug', basename($path))->first();
            abort_unless($profile?->published(), 422, 'Nur veröffentlichte CV-Profile können einen öffentlichen Zugriffslink erhalten.');
        }
        $grant = $request->boolean('reuse') && $input['kind'] === 'permanent'
            ? CvCapabilities::permanentForLabel($path, $input['label'])
            : CvCapabilities::issueNamed($path, $input['kind'], $input['label'], (int) ($input['minutes'] ?? 15));
        if (empty($grant['created'])) {
            return response()->json(['created' => false, 'grant' => ['id' => $grant['id'], 'label' => $grant['label'], 'kind' => $grant['kind']]])
                ->header('Cache-Control', 'private, no-store');
        }
        return response()->json([
            'created' => true,
            'url' => url($path).'#cv='.$grant['token'],
            'grant' => ['id' => $grant['id'], 'label' => $grant['label'], 'kind' => $grant['kind'], 'expires_at' => $grant['expires_at']],
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function revokeGrant(Request $request, int $grantId): JsonResponse
    {
        abort_unless(User::current()?->isSuper(), 403);
        $path = $this->path($request);
        return response()->json(['revoked' => CvCapabilities::revokeGrant($path, $grantId)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function qr(Request $request): Response
    {
        $path = $this->path($request);
        $source = $request->query('source', 'public');
        abort_unless(in_array($source, ['public', 'temporary', 'permanent'], true), 404);
        $url = url($path);
        if ($source !== 'public') {
            $record = DB::table('cv_access_tokens')
                ->where('profile_path', $path)->where('kind', $source)->whereNull('label')->whereNull('revoked_at')
                ->orderByDesc('id')->first();
            abort_unless($record && (! $record->expires_at || now()->isBefore($record->expires_at)), 404);
            $url .= '#cv='.Crypt::decryptString($record->encrypted_token);
        }
        $renderer = new ImageRenderer(
            new RendererStyle(1024, 6),
            new SvgImageBackEnd
        );
        $svg = (new Writer($renderer))->writeString($url, 'UTF-8', ErrorCorrectionLevel::H());

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $path = $this->path($request);
        $records = DB::table('cv_access_tokens')->where('profile_path', $path)->whereNull('label')->whereNull('revoked_at')->orderByDesc('id')->get();
        $links = [];
        foreach ($records as $record) {
            if (isset($links[$record->kind]) || ($record->expires_at && now()->isAfter($record->expires_at))) {
                continue;
            }
            try {
                $url = url($path).'#cv='.Crypt::decryptString($record->encrypted_token);
            } catch (DecryptException) {
                $url = null;
            }
            $links[$record->kind] = ['url' => $url, 'expires_at' => $record->expires_at];
        }

        return response()->json(['path' => $path, 'links' => $links])->header('Cache-Control', 'private, no-store');
    }

    public function temporary(Request $request): JsonResponse
    {
        $path = $this->path($request);

        if ($request->boolean('replace')) {
            DB::table('cv_access_tokens')->where('profile_path', $path)->where('kind', 'temporary')->whereNull('label')->whereNull('revoked_at')->update(['revoked_at' => now(), 'label_key' => null, 'updated_at' => now()]);
        }

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
            $profile = Entry::query()->where('collection', 'cv_profiles')->where('slug', $identifier)->first()
                ?? Entry::find($identifier);
            abort_unless($profile && $profile->collectionHandle() === 'cv_profiles', 404);
            $path = '/cv/'.$profile->slug();
        }

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
