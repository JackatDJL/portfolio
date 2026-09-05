<?php

namespace Tests\Feature;

use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    public function test_lab_renders_with_internal_headers_and_its_own_assets(): void
    {
        $response = $this->get('/design-system')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Semantische Farben')
            ->assertSee('name="theme"', false)
            ->assertSee('prose-site', false)
            ->assertSee('chip-technology', false)
            ->assertSee('content-width', false)
            ->assertDontSee('lab-table', false)
            ->assertDontSee('resources/js/site.js');

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_lab_can_be_disabled(): void
    {
        config(['design-system.enabled' => false]);
        $this->get('/design-system')->assertNotFound();
    }

    public function test_lab_is_unavailable_in_production_even_when_enabled(): void
    {
        $this->app->instance('env', 'production');
        config(['design-system.enabled' => true]);
        $this->get('/design-system')->assertNotFound();
    }
}
