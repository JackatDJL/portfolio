<?php

namespace Tests\Unit;

use App\Tags\SearchMetadata;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

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

    private function imageUrl(object $image): ?string
    {
        $tag = (new ReflectionClass(SearchMetadata::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(SearchMetadata::class, 'imageUrl');

        return $method->invoke($tag, $image, 'https://example.org');
    }
}
