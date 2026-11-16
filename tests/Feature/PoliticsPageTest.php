<?php

namespace Tests\Feature;

use Statamic\Facades\Entry;
use Tests\TestCase;

class PoliticsPageTest extends TestCase
{
    public function test_biography_renders_public_sources_and_personal_party_boundary(): void
    {
        $response = $this->get('/politik')->assertOk();
        foreach (['Ich übernehme Verantwortung.', 'Schülervertretung', 'Erstwählerforum', 'Kommunikation', 'Technik', 'Jugendchor', 'Kein offizieller Parteiauftritt.', 'Mobilität ohne Auto', 'digitale Bildung', 'Europa'] as $text) {
            $response->assertSee($text);
        }
        $response->assertSee('https://voltdeutschland.org/niedersachsen/menschen/jack-ruder', false)
            ->assertSee('/kontakt?thema=politik', false)->assertSee('data-politics-page', false);
    }

    public function test_editable_biographical_copy_is_taken_from_the_entry(): void
    {
        $repository = Entry::getFacadeRoot();
        $entry = clone $repository->query()->where('collection', 'pages')->where('slug', 'politik')->first();
        $entry->set('school_period', 'Zeitangabe aus dem CMS')->set('tech_intro', 'Technische Motivation aus dem CMS')
            ->set('political_interests_text', 'Politische Interessen aus dem CMS')->set('invitation_label', 'Kontaktabschluss aus dem CMS');
        $query = \Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($entry);
        $first = true;
        $mock = \Mockery::mock($repository)->makePartial();
        $mock->shouldReceive('query')->andReturnUsing(function () use (&$first, $query, $repository) {
            if ($first) {
                $first = false;

                return $query;
            }

            return $repository->query();
        });
        Entry::swap($mock);
        $this->get('/politik')->assertOk()->assertSee('Zeitangabe aus dem CMS')->assertSee('Technische Motivation aus dem CMS')
            ->assertSee('Politische Interessen aus dem CMS')->assertSee('Kontaktabschluss aus dem CMS');
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
        foreach ($images as $image) {
            $this->assertStringContainsString('fm=webp', $image->getAttribute('src'));
        }
    }
}
