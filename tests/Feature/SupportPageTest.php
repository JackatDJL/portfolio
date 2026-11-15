<?php

namespace Tests\Feature;

use Statamic\Facades\Entry;
use Statamic\View\View;
use Tests\TestCase;

class SupportPageTest extends TestCase
{
    public function test_support_stays_unpublished_until_real_media_and_content_are_ready(): void
    {
        $this->get('/support')->assertNotFound();
    }

    public function test_unpublished_support_is_excluded_from_the_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/support</loc>', false);
    }

    public function test_non_public_project_data_is_not_rendered_by_the_template(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'support')->first();
        foreach ([['published' => false, 'project_status' => 'active'], ['published' => true, 'project_status' => 'confidential']] as $state) {
            $hidden = [...$state, 'title' => 'Private project marker', 'summary' => 'Private summary marker', 'url' => '/private-project'];
            $html = (new View)->template('support/index')->cascadeContent($entry)
                ->with(['software_project' => [$hidden], 'robot_project' => [$hidden]])->render();
            $this->assertStringNotContainsString('Private project marker', $html);
            $this->assertStringNotContainsString('Private summary marker', $html);
            $this->assertStringNotContainsString('/private-project', $html);
        }
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
        foreach (['prtop', 'Rosenfold. Noch in Entwicklung.', 'AtheBlues', 'Schulteam', 'keine Zahlungen', 'nicht null Euro', 'getrennt', '3D-Rendering', 'vor der Auswahl einer Datei', 'Archivaufnahme', 'nicht der aktuelle Rescue-Line-Roboter', '3D-Druck', 'Bluetooth', 'db38debd'] as $text) {
            $response->assertSee($text);
        }
        $response->assertDontSee('<progress', false)->assertDontSee('360 €')->assertDontSee('900 €')->assertDontSee('700 €')
            ->assertSee('/kontakt?thema=sponsoring', false);
    }

    public function test_code_excerpt_is_editable_and_escaped_as_text(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'support')->first();
        $html = (new View)->template('support/index')->cascadeContent($entry)
            ->with(['rosenfold_code' => '<script>privateMarker()</script>', 'robot_archive_title' => 'Redaktionell geänderter Titel', 'financial_boundary' => 'Redaktionelle Finanzgrenze', 'contact_label' => 'Redaktioneller Kontakt'])
            ->render();
        $this->assertStringContainsString('Redaktionell geänderter Titel', $html);
        $this->assertStringContainsString('Redaktionelle Finanzgrenze', $html);
        $this->assertStringContainsString('Redaktioneller Kontakt', $html);
        $this->assertStringContainsString('&lt;script&gt;privateMarker()', $html);
        $this->assertStringNotContainsString('<script>privateMarker()', $html);
    }
}
