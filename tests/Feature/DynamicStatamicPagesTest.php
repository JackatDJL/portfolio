<?php

namespace Tests\Feature;

use Statamic\Facades\Entry;
use Tests\TestCase;

class DynamicStatamicPagesTest extends TestCase
{
    public function test_published_entries_found_by_uri_render_on_their_dynamic_frontend_routes(): void
    {
        $pages = [
            '/projekte/prtop',
            '/blog/warum-ich-projekte-zu-codeberg-verschiebe',
            '/publikationen/zinsen-und-finanzen',
            '/cv/edu/gymnasium-athenaeum-stade',
            '/cv/exp/atheblues-robotik-und-teamarbeit',
        ];

        foreach ($pages as $uri) {
            $entry = Entry::findByUri($uri);

            $this->assertNotNull($entry, "Expected a Statamic entry for {$uri}.");
            $this->assertTrue($entry->published(), "Expected the Statamic entry at {$uri} to be published.");

            $this->get($uri)
                ->assertOk()
                ->assertSee((string) $entry->value('title'));
        }
    }

    public function test_homepage_entry_is_rendered_through_the_frontend_router(): void
    {
        $homepage = Entry::findByUri('/');

        $this->assertNotNull($homepage, 'Expected a Statamic homepage entry at /.');

        $this->get('/')
            ->assertOk()
            ->assertSee('Jack Ruder');
    }

    public function test_unknown_dynamic_detail_routes_remain_not_found(): void
    {
        $this->get('/projekte/not-a-published-project')->assertNotFound();
        $this->get('/cv/not-a-published-profile')->assertNotFound();
    }
}
