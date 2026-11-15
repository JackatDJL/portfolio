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
            $this->get('/blog/'.$slug)
                ->assertOk()
                ->assertSee('class="media-figure blog-featured-image"', false)
                ->assertSee('blog-featured-image__image', false)
                ->assertSee('src="/img/asset/', false);
        } finally {
            $entry->delete();
        }
    }
}
