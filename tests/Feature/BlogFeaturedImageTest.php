<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Tests\TestCase;

class BlogFeaturedImageTest extends TestCase
{
    public function test_posts_use_the_shared_transition_with_or_without_a_cover(): void
    {
        $existing = $this->get('/blog/warum-ich-projekte-zu-codeberg-verschiebe')->assertOk();
        $existing->assertSee('class="editorial-hero-transition" data-editorial-hero', false)
            ->assertDontSee('blog-featured-image', false);

        $document = new \DOMDocument();
        @$document->loadHTML($existing->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//header[contains(concat(" ", normalize-space(@class), " "), " editorial-hero-transition ")]//img')->length);

        $slug = 'featured-image-rendering-fixture';
        $entry = Entry::make()
            ->id((string) Str::uuid())
            ->collection('posts')
            ->slug($slug)
            ->published(true)
            ->date(now()->subDay())
            ->data([
                'title' => 'Featured image fixture',
                'summary' => 'A temporary test entry.',
                'cover' => 'posts/demo-blogpost/hintergrund.jpg',
                'content' => [],
            ]);
        $entry->save();

        try {
            $response = $this->get('/blog/'.$slug)->assertOk();
            $response
                ->assertSee('class="editorial-hero-transition" data-editorial-hero', false)
                ->assertSee('editorial-hero-transition__frame', false)
                ->assertSee('src="/img/asset/', false);
            $response->assertDontSee('blog-featured-image', false);

            $document = new \DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);

            $this->assertSame(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " editorial-hero-transition ")]')->length);
            $this->assertSame(1, $xpath->query('//h1[normalize-space()="Featured image fixture"]')->length);
        } finally {
            $entry->delete();
        }
    }

    public function test_blog_cover_card_uses_media_overlay_without_nesting_credit_links(): void
    {
        $slug = 'featured-card-image-fixture';
        $entry = Entry::make()
            ->id((string) Str::uuid())
            ->collection('posts')
            ->slug($slug)
            ->published(true)
            ->date(now()->subDay())
            ->data([
                'title' => 'Featured card image fixture',
                'summary' => 'A temporary test entry.',
                'featured' => true,
                'cover' => 'posts/demo-blogpost/hintergrund.jpg',
                'content' => [],
            ]);
        $entry->save();

        try {
            $response = $this->get('/blog')->assertOk();
            $response->assertSee('class="media-figure journal-card__cover"', false)
                ->assertSee('class="media-frame__image-link"', false)
                ->assertSee('class="media-overlays journal-card__overlays"', false);

            $document = new \DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);

            $this->assertSame(0, $xpath->query('//figure[contains(concat(" ", normalize-space(@class), " "), " journal-card__cover ")]//a//a')->length);
        } finally {
            $entry->delete();
        }
    }
}
