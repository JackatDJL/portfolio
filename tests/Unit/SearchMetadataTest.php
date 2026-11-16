<?php

namespace Tests\Unit;

use App\Tags\SearchMetadata;
use Statamic\Facades\Asset;
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

    public function test_legacy_disclosure_is_not_emitted_without_an_image_label(): void
    {
        foreach (['eu_ai_disclosure', 'ai_disclosure'] as $disclosureHandle) {
            $asset = new class($disclosureHandle)
            {
                public function __construct(private string $disclosureHandle) {}

                public function get(string $key): ?string
                {
                    return $key === $this->disclosureHandle ? 'Gespeicherter KI-Hinweis' : null;
                }

                public function absoluteUrl(): string
                {
                    return 'https://example.org/source.png';
                }
            };

            $this->assertNull($this->imageUrl($asset));
        }
    }

    public function test_explicit_none_keeps_legacy_disclosure_source_available_for_social_preview(): void
    {
        $asset = new class
        {
            public function get(string $key): ?string
            {
                return match ($key) {
                    'ai_involvement' => 'none',
                    'eu_ai_disclosure' => 'Gespeicherter KI-Hinweis',
                    default => null,
                };
            }

            public function absoluteUrl(): string
            {
                return 'https://example.org/source.png';
            }
        };

        $this->assertSame('https://example.org/source.png', $this->imageUrl($asset));
    }

    public function test_real_asset_with_legacy_disclosure_is_excluded_unless_none_is_explicit(): void
    {
        $asset = Asset::find('assets::projects/ai-ctx/effect-ai-ctx.png');
        $this->assertNotNull($asset);
        $this->assertFalse($asset->data()->has('ai_involvement'));
        $asset->set('eu_ai_disclosure', 'Gespeicherter KI-Hinweis');

        $this->assertNull($this->imageUrl($asset));

        $asset->set('ai_involvement', 'none');
        $this->assertSame($asset->absoluteUrl(), $this->imageUrl($asset));
    }

    public function test_ai_icon_css_reset_applies_outside_media_figure_wrappers(): void
    {
        $css = file_get_contents(resource_path('css/gallery.css'));

        $this->assertMatchesRegularExpression('/\.media-overlays img\.media-overlay__ai-icon\s*\{[^}]*border:\s*0/', $css);
    }

    public function test_ai_asset_is_found_from_bare_path_rooted_path_and_absolute_url_with_dot_segments(): void
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

        AssetContainer::shouldReceive('all')->times(5)->andReturn(collect([$container]));

        foreach ([
            'jack/profile_pictures/generated.png',
            '/assets/jack/profile_pictures/generated.png',
            'https://example.org/assets/jack/profile_pictures/generated.png',
            'https://example.org/assets/unused/../jack/profile_pictures/generated.png',
            'https://example.org/assets/unused/%2e%2e/jack/profile_pictures/generated.png',
        ] as $reference) {
            $this->assertNull($this->imageUrl($reference));
        }
    }

    public function test_unrelated_absolute_image_url_is_preserved(): void
    {
        AssetContainer::shouldReceive('all')->once()->andReturn(collect([]));

        $this->assertSame('https://cdn.example.net/image.png', $this->imageUrl('https://cdn.example.net/image.png'));
    }

    public function test_percent_encoded_literal_in_ai_asset_filename_is_decoded_only_once(): void
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
                return $path === 'jack/generated%20image.png' ? $this->asset : null;
            }
        };

        AssetContainer::shouldReceive('all')->once()->andReturn(collect([$container]));

        $this->assertNull($this->imageUrl('https://example.org/assets/jack/generated%2520image.png'));
    }

    private function imageUrl(mixed $image): ?string
    {
        $tag = (new ReflectionClass(SearchMetadata::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(SearchMetadata::class, 'imageUrl');

        return $method->invoke($tag, $image, 'https://example.org');
    }
}
