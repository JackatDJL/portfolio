<?php

namespace App\Http\Controllers;

use App\Support\CvCapabilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CvPrivateLinkController extends Controller
{
    public function open(Request $request): RedirectResponse
    {
        $path = (string) $request->query('path', '/cv');
        abort_unless(preg_match('#^/cv(?:/[a-z0-9-]+)?$#', $path), 404);

        $token = CvCapabilities::issue($path);

        return redirect()->to(url($path).'#cv='.$token)
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
