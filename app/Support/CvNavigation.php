<?php

namespace App\Support;

use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

/** Non-secret return context; authorization remains in the existing scoped session. */
final class CvNavigation
{
    public static function applicationId(string $path): ?string
    {
        // Check for new format: /cv/{application-slug}
        if (preg_match('#^/cv/([a-z0-9-]+)$#D', $path, $matches)) {
            $application = Entry::query()->where('collection', 'applications')->where('slug', $matches[1])->first();
            if ($application) {
                return $application->id();
            }
        }
        // Check for old format: /applications/{uuid}/cv
        return preg_match('#^/applications/([a-f0-9-]{36})/cv$#D', $path, $matches) ? $matches[1] : null;
    }

    public static function authorized(Request $request, string $path): bool
    {
        if ($id = self::applicationId($path)) {
            $entry = Entry::find($id);

            return $entry && $entry->collectionHandle() === 'applications'
                && (User::current()?->isSuper() || ApplicationCapabilities::sessionAllows($request, $id));
        }

        return preg_match('#^/cv(?:/[a-z0-9-]+)?$#D', $path) && CvCapabilities::sessionAllows($request, $path);
    }

    public static function returnPath(mixed $path): string
    {
        if (! is_string($path) || $path === '/cv') {
            return '/cv';
        }
        if (preg_match('#^/cv/([a-z0-9-]+)$#D', $path, $matches)) {
            $profile = Entry::query()->where('collection', 'cv_profiles')->where('slug', $matches[1])->where('published', true)->first();
            if ($profile) {
                return $path;
            }
            // Check if it's an application slug
            $application = Entry::query()->where('collection', 'applications')->where('slug', $matches[1])->first();
            if ($application && self::authorized(request(), $path)) {
                return $path;
            }
            return '/cv';
        }
        if (self::applicationId($path) && self::authorized(request(), $path)) {
            return $path;
        }

        return '/cv';
    }

    public static function detailUrl(string $url, string $returnPath, ?string $token = null): string
    {
        $parts = parse_url($url);
        $host = parse_url((string) config('cv.canonical_base_url'), PHP_URL_HOST);
        if (! $parts || (isset($parts['host']) && $parts['host'] !== $host && $parts['host'] !== request()->getHost())
            || isset($parts['query']) || isset($parts['fragment'])
            || ! preg_match('#^/(?:cv/(?:exp|edu)|projekte|publikationen)/[a-zA-Z0-9_-]+$#D', $parts['path'] ?? '')) {
            return $url;
        }
        $result = $url.'?'.http_build_query(['cv_return' => $returnPath], '', '&', PHP_QUERY_RFC3986);

        return $result.($token && preg_match('/^[A-Za-z0-9]{64}$/D', $token) ? '#cv='.$token : '');
    }
}
