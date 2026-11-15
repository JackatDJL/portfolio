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

    public function test_hide_credit_keeps_caption_without_repeating_the_overlay_credit(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/attribution.antlers.html')), [
            'asset' => [
                'caption' => 'A caption below the image',
                'rights' => 'cc',
                'cc' => 'Source credit already shown in the overlay',
            ],
            'hide_credit' => true,
        ]);

        $this->assertStringContainsString('A caption below the image', $html);
        $this->assertStringNotContainsString('Source credit already shown in the overlay', $html);
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

    public function test_asset_ai_metadata_inherits_into_the_shared_media_overlay(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/overlays.antlers.html')), [
            'asset' => [
                'ai_involvement' => 'partially_modified',
                'ai_disclosure' => 'Hintergrund teilweise mit KI ergänzt',
                'ai_icon_appearance' => 'white-50',
                'rights' => 'cc',
                'cc' => 'Foto: Ada Beispiel, https://example.org/foto',
            ],
        ]);

        $this->assertStringContainsString('/assets/ai-labels/partially-modified/white-50.svg', $html);
        $this->assertStringContainsString('Hintergrund teilweise mit KI erg&auml;nzt', $html);
        $this->assertStringContainsString('aria-label="Bildnachweis"', $html);
        $this->assertStringContainsString('Foto: Ada Beispiel', $html);
        $this->assertStringContainsString('href="https://example.org/foto"', $html);
    }

    public function test_absent_and_none_ai_metadata_do_not_render_an_icon(): void
    {
        $partial = file_get_contents(resource_path('views/partials/media/overlays.antlers.html'));
        foreach ([[], ['ai_involvement' => 'none', 'ai_disclosure' => '']] as $metadata) {
            $html = (string) Antlers::parse($partial, ['asset' => $metadata]);
            $this->assertStringNotContainsString('media-overlay__ai', $html);
            $this->assertStringNotContainsString('/assets/ai-labels/', $html);
        }
    }

    public function test_unknown_ai_value_cannot_select_an_icon_path(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/overlays.antlers.html')), [
            'asset' => ['ai_involvement' => '../../injected', 'ai_disclosure' => 'ignored'],
        ]);

        $this->assertStringNotContainsString('/assets/ai-labels/', $html);
        $this->assertStringNotContainsString('ignored', $html);
    }

    public function test_blank_disclosure_uses_a_safe_default_and_unknown_appearance_is_whitelisted(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/overlays.antlers.html')), [
            'asset' => [
                'ai_involvement' => 'partially_modified',
                'ai_disclosure' => '',
                'ai_icon_appearance' => '../../unexpected',
            ],
        ]);

        $this->assertStringContainsString('/assets/ai-labels/partially-modified/black.svg', $html);
        $this->assertStringContainsString('Teilweise mit KI ver&auml;ndert', $html);
    }

    public function test_custom_disclosure_is_escaped_as_text(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/overlays.antlers.html')), [
            'asset' => [
                'ai_involvement' => 'basic_disclosure',
                'ai_disclosure' => '<script>alert(1)</script>',
            ],
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_asset_credit_remains_authoritative_and_legacy_overrides_are_fallbacks(): void
    {
        $partial = file_get_contents(resource_path('views/partials/media/overlays.antlers.html'));
        $assetCredit = (string) Antlers::parse($partial, [
            'asset' => ['rights' => 'cc', 'cc' => 'Stored credit'],
            'credit_override' => 'Old block credit',
            'rights_override' => 'jack',
        ]);
        $fallbackCredit = (string) Antlers::parse($partial, [
            'asset' => [],
            'credit_override' => 'Legacy block credit',
            'rights_override' => 'cc',
        ]);

        $this->assertStringContainsString('Stored credit', $assetCredit);
        $this->assertStringNotContainsString('Old block credit', $assetCredit);
        $this->assertStringContainsString('Legacy block credit', $fallbackCredit);
    }

    public function test_ai_metadata_fields_extend_the_existing_asset_blueprint(): void
    {
        $blueprint = file_get_contents(resource_path('blueprints/assets/assets.yaml'));
        foreach (['alt', 'caption', 'rights', 'cc', 'ai_involvement', 'ai_disclosure', 'ai_icon_appearance'] as $field) {
            $this->assertStringContainsString('handle: '.$field, $blueprint);
        }
        $this->assertStringContainsString('default: none', $blueprint);
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
