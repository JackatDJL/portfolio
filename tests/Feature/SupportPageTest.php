<?php

namespace Tests\Feature;

use Statamic\Facades\Entry;
use Tests\TestCase;

class SupportPageTest extends TestCase
{
    public function test_support_stays_unpublished_until_real_media_and_content_are_ready(): void
    {
        $this->get('/support')->assertNotFound();
    }

    public function test_editorial_draft_separates_projects_and_does_not_invent_financial_totals(): void
    {
        $entry = clone Entry::query()->where('collection', 'pages')->where('slug', 'support')->first();
        $entry->published(true);
        $query = \Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('first')->andReturn($entry);
        $repository = Entry::getFacadeRoot();
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
        $response = $this->get('/support')->assertOk();
        foreach (['prtop', 'Rosenfold. Noch in Entwicklung.', 'AtheBlues', 'Schulteam', 'keine Zahlungen', 'nicht null Euro', 'getrennt'] as $text) {
            $response->assertSee($text);
        }
        $response->assertDontSee('<progress', false)->assertDontSee('360 €')->assertDontSee('900 €')->assertDontSee('700 €')
            ->assertSee('/kontakt?thema=sponsoring', false);
    }
}
