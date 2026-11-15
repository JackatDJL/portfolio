<?php

namespace Tests\Unit;

use App\Tags\SearchMetadata;
use Statamic\Facades\AssetContainer;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class SearchMetadataTest extends TestCase
{
    public function test_ai_labelled_source_is_not_emitted_as_an_unlabelled_social_preview(): void
    {
        $asset = new class
        {
            public function get(string $key): ?string
            {
                return $key === 'ai_involvement' ? 'partially_modified' : null;
            }

            public function absoluteUrl(): string
            {
                return 'https://example.org/source.png';
            }
        };

        $this->assertNull($this->imageUrl($asset));
    }

    public function test_unlabelled_asset_remains_available_as_a_social_preview(): void
    {
        $asset = new class
        {
            public function get(string $key): ?string
            {
                return null;
            }

            public function absoluteUrl(): string
            {
                return 'https://example.org/source.png';
            }
        };

        $this->assertSame('https://example.org/source.png', $this->imageUrl($asset));
    }

    public function test_ai_asset_is_found_from_bare_path_rooted_path_and_absolute_url(): void
    {
        $asset = new class
        {
            public function get(string $key): ?string
            {
                return $key === 'ai_involvement' ? 'partially_modified' : null;
            }
        };

        $container = new class($asset)
        {
            public function __construct(private object $asset) {}

            public function private(): bool
            {
                return false;
            }

            public function url(): string
            {
                return '/assets';
            }

            public function absoluteUrl(): string
            {
                return 'https://example.org/assets';
            }

            public function asset(string $path): ?object
            {
                return $path === 'jack/profile_pictures/generated.png' ? $this->asset : null;
            }
        };

        AssetContainer::shouldReceive('all')->times(3)->andReturn(collect([$container]));

        foreach ([
            'jack/profile_pictures/generated.png',
            '/assets/jack/profile_pictures/generated.png',
            'https://example.org/assets/jack/profile_pictures/generated.png',
        ] as $reference) {
            $this->assertNull($this->imageUrl($reference));
        }
    }

    public function test_unrelated_absolute_image_url_is_preserved(): void
    {
        AssetContainer::shouldReceive('all')->once()->andReturn(collect([]));

        $this->assertSame('https://cdn.example.net/image.png', $this->imageUrl('https://cdn.example.net/image.png'));
    }

    private function imageUrl(mixed $image): ?string
    {
        $tag = (new ReflectionClass(SearchMetadata::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(SearchMetadata::class, 'imageUrl');

        return $method->invoke($tag, $image, 'https://example.org');
    }
}
