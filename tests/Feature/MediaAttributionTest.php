<?php

namespace Tests\Feature;

use Tests\TestCase;

class MediaAttributionTest extends TestCase
{
    public function test_gallery_figures_render_the_asset_eu_ai_disclosure(): void
    {
        $html = view('partials.gallery.figure', [
            'width' => 909,
            'height' => 802,
            'url' => 'projects/prtop/prtop-tokio-main-run.png',
            'alt' => 'Testbild',
            'caption' => null,
            'cc' => null,
            'rights' => 'jack',
            'eu_ai_disclosure' => 'KI-Hinweis-Test',
        ])->render();

        $this->assertStringContainsString(
            '<span class="asset-attribution__ai-disclosure">KI-Hinweis-Test</span>',
            $html,
        );
    }
}
