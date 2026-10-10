<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Statamic\Facades\GlobalSet;
use Tests\TestCase;

class NowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_now_page_renders_the_existing_editorial_content_and_shared_links(): void
    {
        $this->get('/aktuell')
            ->assertOk()
            ->assertSee('03.10.2026')
            ->assertSee('prtop')
            ->assertSee('Presse- und Webkommunikation bei Volt Stade')
            ->assertSee('Jugendchor Neukloster')
            ->assertSee('Klangwerk')
            ->assertSee('Communications bei Volt Stade')
            ->assertSee('djl.foundation')
            ->assertSee('class="semantic-link"', false)
            ->assertSee('class="btn btn-primary', false);
    }

    public function test_now_page_omits_optional_sections_when_cms_fields_are_empty(): void
    {
        $variables = GlobalSet::find('now')->inDefaultSite();
        $original = $variables->data()->all();

        try {
            $variables->set('items', []);
            $variables->set('homepage_links', []);
            $variables->set('homepage_button_label', null);
            $variables->set('homepage_button_link', null);

            $this->get('/aktuell')
                ->assertOk()
                ->assertSee('Zurzeit entwickle ich prtop')
                ->assertDontSee('Aktuelle Arbeit')
                ->assertDontSee('Weitere aktuelle Links')
                ->assertDontSee('Engagiere dich beim Digital Independence Day!');
        } finally {
            $variables->data($original);
        }
    }
}
