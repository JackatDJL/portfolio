<?php

namespace Tests\Feature;

use Tests\TestCase;

class PoliticsPageTest extends TestCase
{
    public function test_biography_renders_public_sources_and_personal_party_boundary(): void
    {
        $response = $this->get('/politik')->assertOk();
        foreach (['Raum für eigene Fragen.', 'Schülervertretung', 'Erstwählerforum', 'Kommunikation', 'Technik', 'Jugendchor', 'Kein offizieller Parteiauftritt.'] as $text) {
            $response->assertSee($text);
        }
        $response->assertSee('https://voltdeutschland.org/niedersachsen/menschen/jack-ruder', false)
            ->assertSee('/kontakt?thema=politik', false)->assertSee('data-politics-page', false);
    }

    public function test_photography_has_dimensions_alt_text_and_one_prioritized_image(): void
    {
        $html = $this->get('/politik')->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $images = $xpath->query('//article[contains(@class,"politics-page")]//img');
        $this->assertCount(4, $images);
        $priority = 0;
        foreach ($images as $image) {
            $this->assertNotEmpty($image->getAttribute('alt'));
            $this->assertGreaterThan(0, (int) $image->getAttribute('width'));
            $this->assertGreaterThan(0, (int) $image->getAttribute('height'));
            if ($image->getAttribute('fetchpriority') === 'high') {
                $priority++;
            } else {
                $this->assertSame('lazy', $image->getAttribute('loading'));
            }
        }
        $this->assertSame(1, $priority);
    }
}
