<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class ApplicationAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_if($request->query->has('token') || $request->query->has('cv') || $request->query->has('application'), 404);
        $response = $next($request);
        $response->headers->add([
            'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive', 'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);

        return $response;
    }
}
