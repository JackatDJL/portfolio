<?php

namespace Tests\Feature;

use Tests\TestCase;

class AiDisclosureIconTest extends TestCase
{
    public function test_all_supported_disclosure_icons_are_served_as_svg(): void
    {
        $kinds = ['basic', 'fully-generated', 'partially-modified'];
        $appearances = ['black', 'white', 'black-50', 'white-50'];

        foreach ($kinds as $kind) {
            foreach ($appearances as $appearance) {
                $response = $this->get("/media/ai-disclosures/{$kind}/{$appearance}.svg");

                $response->assertOk()
                    ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
                    ->assertHeader('X-Content-Type-Options', 'nosniff')
                    ->assertHeader('Cache-Control', 'public, max-age=86400')
                    ->assertSee('<svg', false);
            }
        }
    }

    public function test_unsupported_disclosure_icon_values_return_not_found(): void
    {
        $this->get('/media/ai-disclosures/unknown/black.svg')->assertNotFound();
        $this->get('/media/ai-disclosures/basic/unknown.svg')->assertNotFound();
    }
}
