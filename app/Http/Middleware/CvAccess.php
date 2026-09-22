<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class CvAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('cv', 'cv/*')) {
            return $next($request);
        }
        $path = '/'.trim($request->path(), '/');
        abort_if(preg_match('#^/cv/[0-9a-f-]{36}(?:/pdf)?$#', $path), 404);
        // Share tokens are accepted only in a POST body by CvTokenExchangeController.
        abort_if($request->query->has('cv') || $request->query->has('token'), 404);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
