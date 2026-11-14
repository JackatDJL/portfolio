<?php

namespace Tests\Feature;

use Statamic\Facades\Entry;
use Tests\TestCase;

class PoliticsPageTest extends TestCase
{
    public function test_politics_page_renders_linked_public_records_and_verified_party_profile(): void
    {
        $page = Entry::findByUri('/politik');
        $this->assertNotNull($page);
        $this->assertTrue($page->published());
        $this->assertCount(3, $page->value('experience'));
        $this->assertCount(3, $page->value('milestones'));

        $this->get('/politik')
            ->assertOk()
            ->assertSee('Offizielles Profil bei Volt Niedersachsen')
            ->assertSee('Pressearbeit, soziale Medien, Website und öffentliche Kommunikation')
            ->assertSee('Erstwählerforum Stade')
            ->assertSee('Breaking Free from Big Tech')
            ->assertSee('/projekte')
            ->assertSee('/blog')
            ->assertSee('/cv')
            ->assertDontSee('Wahlzeit');
    }

    public function test_politics_page_has_canonical_profilepage_schema_and_is_in_sitemap(): void
    {
        $response = $this->get('/politik')->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://jack.djl.foundation/politik">', $html);
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $match);
        $this->assertNotEmpty($match[1] ?? null);
        $schema = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $profile = collect($schema['@graph'])->firstWhere('@type', 'ProfilePage');
        $this->assertNotNull($profile);
        $this->assertSame('https://jack.djl.foundation/#person', $profile['mainEntity']['@id']);

        $this->get('/sitemap.xml')->assertOk()->assertSee('https://jack.djl.foundation/politik', false);
    }

    public function test_politics_page_and_llms_navigation_do_not_expose_private_cv_or_application_data(): void
    {
        $this->get('/politik')
            ->assertOk()
            ->assertDontSee('cv=')
            ->assertDontSee('/applications/')
            ->assertDontSee('private-data');

        $this->get('/llms.txt')->assertOk()->assertSee('/politik');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/applications/', false)->assertDontSee('/cv/private', false);
        $this->get('/applications/00000000-0000-4000-8000-000000000000')->assertNotFound();
    }
}
