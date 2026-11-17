<?php

namespace Tests\Feature;

use Tests\TestCase;

class MediaAttributionTest extends TestCase
{
    public function test_gallery_figures_render_the_eu_disclosure_as_an_accessible_icon_only_label(): void
    {
        $html = view('partials.gallery.figure', [
            'width' => 909,
            'height' => 802,
            'url' => 'projects/prtop/prtop-tokio-main-run.png',
            'alt' => 'Testbild',
            'caption' => null,
            'cc' => null,
            'rights' => 'jack',
            'ai_involvement' => 'partially_modified',
            'ai_icon_appearance' => 'white-50',
            'eu_ai_disclosure' => 'KI-Hinweis-Test',
        ])->render();

        $this->assertStringContainsString('/media/ai-disclosures/partially-modified/white-50.svg', $html);
        $this->assertStringContainsString('aria-label="KI-Hinweis-Test"', $html);
        $this->assertStringNotContainsString('KI-Hinweis-Test</span>', $html);
        $this->assertStringNotContainsString('asset-attribution__ai-disclosure', $html);
    }
}
