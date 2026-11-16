<?php

namespace Tests\Feature;

use Statamic\Facades\Entry;
use Statamic\View\View;
use Tests\TestCase;

class SupportPageTest extends TestCase
{
    public function test_editorial_image_scope_keeps_real_images_without_custom_type_metadata(): void
    {
        $query = \Statamic\Facades\Asset::query()->where('container', 'assets');
        (new \App\Scopes\EditorialImages)->apply($query, []);
        $assets = $query->get();
        $this->assertTrue($assets->contains(fn ($asset) => $asset->path() === 'projects/rosenfold/rosenfold-ready.png'));
        $this->assertTrue($assets->contains(fn ($asset) => $asset->path() === 'projects/atheblues/atheblues-rescue-line-cad-20261115.png'));
        $this->assertTrue($assets->every(fn ($asset) => $asset->isImage()));
    }

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
                ->with(['education_project' => [$hidden]])->render();
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
        foreach (['Rosenfold.', 'AtheBlues', 'Schulteam', 'getrennt', 'Projektwoche', 'Rust-Paketmanager', 'Meine Projekte unterstützen'] as $text) {
            $response->assertSee($text);
        }
        $response->assertDontSee('prtop')->assertDontSee('<details', false)->assertDontSee('Erst der Zweck')
            ->assertDontSee('<progress', false)->assertDontSee('360 €')->assertDontSee('900 €')->assertDontSee('700 €')
            ->assertSee('data-support-root', false)->assertSee('https://www.atheblues.de/donate', false);

    }

    public function test_widgets_and_intro_remain_editable_and_escape_markup(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'support')->first();
        $html = (new View)->template('support/index')->cascadeContent($entry)->with([
            'intro' => '<script>privateMarker()</script>',
            'support_options' => [['title' => 'Mein Widget aus dem CMS', 'text' => 'Bedarf aus dem CMS', 'label' => 'Kontakt', 'link' => '/kontakt']],
            'contact_label' => 'Redaktioneller Kontakt',
        ])->render();
        $this->assertStringContainsString('Mein Widget aus dem CMS', $html);
        $this->assertStringContainsString('Bedarf aus dem CMS', $html);
        $this->assertStringContainsString('Redaktioneller Kontakt', $html);
        $this->assertStringNotContainsString('<script>privateMarker()', $html);
    }

    public function test_cad_is_current_and_the_opening_uses_the_root_photo_structure(): void
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'support')->first();
        $html = (new View)->template('support/index')->cascadeContent($entry)->render();
        $this->assertStringContainsString('schnell austauschbare Motorhalterung', $html);
        $this->assertStringContainsString('Sortiermodul', $html);
        $this->assertStringNotContainsString('cad.onshape.com/documents/', $html);
        $this->assertStringContainsString('data-home-person-media', $html);
        $this->assertStringContainsString('data-home-thread-path', $html);
        $this->assertStringContainsString('data-entry-cta', $html);
        $this->assertStringNotContainsString('prtop', $html);
    }
}
