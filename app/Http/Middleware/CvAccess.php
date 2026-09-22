<?php
namespace App\Http\Middleware;

use App\Support\CvCapabilities;
use Closure;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;

final class CvAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->is('cv', 'cv/*')) return $next($request);
        $path = '/'.trim($request->path(), '/');
        $scope = preg_replace('#/pdf$#', '', $path);
        if (preg_match('#^/cv/([0-9a-f-]{36})(/pdf)?$#', $path, $match)) {
            $profile = Entry::find($match[1]);
            abort_unless($profile && $profile->collectionHandle() === 'cv_profiles', 404);
            $canonical = '/cv/'.$profile->slug();
            $request->attributes->set('cv_legacy_scope', $scope);
            $scope = $canonical;
            $destination = $canonical.($match[2] ?? '');
        }
        $token = (string) $request->query('cv', $request->query('token', ''));
        if ($token !== '') {
            $request->query->remove('cv');
            $request->query->remove('token');
            $request->server->set('QUERY_STRING', http_build_query($request->query()));
            $request->server->set('REQUEST_URI', $path);
        }
        if ($token !== '') {
            $valid = CvCapabilities::allows($token, $scope);
            if (!$valid && $request->attributes->has('cv_legacy_scope')) $valid = CvCapabilities::allows($token, $request->attributes->get('cv_legacy_scope'));
            abort_unless($valid, 404);
            $request->session()->regenerate();
            // Store only the digest; revocation and expiration remain authoritative on every request.
            $request->session()->put('cv_grants.'.hash('sha256', $scope), hash('sha256', $token));
            return redirect($destination ?? $path)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        }
        if (isset($destination)) return redirect($destination)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
