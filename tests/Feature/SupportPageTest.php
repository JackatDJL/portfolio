<?php

namespace Tests\Feature;

use Tests\TestCase;

class SupportPageTest extends TestCase
{
    public function test_support_page_shows_separate_goals_budgets_and_disclaimers(): void
    {
        config(['support.github_sponsors_url' => null]);

        $this->get('/support')->assertOk()
            ->assertSee('Persönliche Open-Source- und Softwareprojekte')
            ->assertSee('Rosenfold und künftige unabhängige Vorhaben')
            ->assertSee('AtheBlues / Schulrobotik')
            ->assertSee('Hosting und Domains: 120 €')
            ->assertSee('Bestätigt: 0 €')
            ->assertSee('zugelassene Schule oder Teamorganisation')
            ->assertSee('nicht automatisch steuerlich absetzbar')
            ->assertDontSee('GitHub Sponsors');
    }

    public function test_github_sponsors_link_is_rendered_only_when_configured(): void
    {
        config(['support.github_sponsors_url' => 'https://github.com/sponsors/example']);

        $this->get('/support')->assertOk()->assertSee('https://github.com/sponsors/example');
    }
}
