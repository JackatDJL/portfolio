<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class AiDisclosureIconController extends Controller
{
    private const KINDS = ['basic', 'fully-generated', 'partially-modified'];

    private const APPEARANCES = ['black', 'white', 'black-50', 'white-50'];

    public function __invoke(string $kind, string $appearance): Response
    {
        abort_unless(in_array($kind, self::KINDS, true) && in_array($appearance, self::APPEARANCES, true), 404);

        $path = resource_path("ai-labels/{$kind}/{$appearance}.svg");
        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
