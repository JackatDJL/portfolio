<?php

namespace Tests\Feature;

use Statamic\Facades\Antlers;
use Tests\TestCase;

class AssetAttributionTest extends TestCase
{
    public function test_third_party_credit_and_caption_render_without_duplication(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/attribution.antlers.html')), [
            'asset' => ['caption' => 'Schulgebäude', 'rights' => 'cc', 'cc' => 'Foto: A, https://example.org/photo'],
        ]);
        $this->assertStringContainsString('Schulgeb&auml;ude', $html);
        $this->assertStringContainsString('Foto: A', $html);
        $this->assertStringContainsString('href="https://example.org/photo"', $html);
        $this->assertSame(1, substr_count($html, 'Schulgeb&auml;ude'));
    }

    public function test_gallery_caption_and_credit_values_use_the_same_renderer(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/attribution.antlers.html')), [
            'caption_override' => 'Ein Moment',
            'credit_override' => 'Foto: B, https://example.org/gallery',
            'rights_override' => 'cc',
        ]);
        $this->assertStringContainsString('Ein Moment', $html);
        $this->assertStringContainsString('Foto: B', $html);
        $this->assertStringContainsString('href="https://example.org/gallery"', $html);
    }

    public function test_own_image_does_not_show_stale_third_party_credit(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/attribution.antlers.html')), [
            'asset' => ['rights' => 'jack', 'cc' => 'Old attribution'],
        ]);
        $this->assertStringNotContainsString('figcaption', $html);
        $this->assertStringNotContainsString('Old attribution', $html);
    }

    public function test_missing_rights_do_not_invent_an_owner_or_license(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/attribution.antlers.html')), ['asset' => []]);
        $this->assertStringNotContainsString('figcaption', $html);
    }

    public function test_own_images_use_the_central_license_without_a_stale_credit(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/attribution.antlers.html')), [
            'asset' => ['rights' => 'jack', 'cc' => 'Old attribution'],
            'site' => ['name' => 'Jack Ruder', 'content_license_name' => 'CC BY-SA 4.0', 'content_license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.de'],
        ]);
        $this->assertStringContainsString('Jack Ruder', $html);
        $this->assertStringContainsString('CC BY-SA 4.0', $html);
        $this->assertStringNotContainsString('Old attribution', $html);
    }

    public function test_project_caption_only_appears_once_on_the_detail_page(): void
    {
        $caption = 'Workshop bei der Projektwoche des Athenaeums 2025.';
        $detail = $this->get('/projekte/hackclub-stade')->assertOk()->getContent();
        $this->assertSame(1, substr_count($detail, $caption));
        $this->assertStringContainsString('Jack Ruder ·', $detail);
        $this->assertStringNotContainsString($caption, $this->get('/projekte')->assertOk()->getContent());
    }
}
