<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Tests\TestCase;

class BlogFeaturedImageTest extends TestCase
{
    public function test_selected_cover_renders_in_the_article_hero_and_missing_cover_keeps_the_header_compact(): void
    {
        $existing = $this->get('/blog/warum-ich-projekte-zu-codeberg-verschiebe')->assertOk();
        $existing->assertDontSee('blog-featured-image', false);

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
                ->assertSee('class="media-figure blog-featured-image"', false)
                ->assertSee('blog-featured-image__image', false)
                ->assertSee('src="/img/asset/', false);

            $document = new \DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);

            $this->assertSame(1, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " blog-featured-image ")]')->length);
            $this->assertSame(0, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " post-featured-image ")]')->length);
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
            $response->assertSee('class="media-figure journal-cover"', false)
                ->assertSee('class="media-frame__image-link"', false)
                ->assertSee('class="media-overlays"', false);

            $document = new \DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);

            $this->assertSame(0, $xpath->query('//figure[contains(concat(" ", normalize-space(@class), " "), " journal-cover ")]//a//a')->length);
        } finally {
            $entry->delete();
        }
    }
}
