<?php

namespace Tests\Feature;

use Statamic\Facades\Antlers;
use Statamic\Facades\Asset;
use Tests\TestCase;

class AssetAttributionTest extends TestCase
{
    public function test_project_hero_places_caption_after_the_image_frame_without_duplicating_credit(): void
    {
        $cover = clone Asset::find('assets::projects/erstwaehlerforum-stade/img-20260602-wa0030.jpg');
        $cover->set('caption', 'Synthetic hero caption');
        $cover->set('rights', 'cc');
        $cover->set('cc', 'Synthetic photographer');
        $html = view('partials.projects.hero-transition', [
            'cover' => $cover,
            'title' => 'Synthetic project',
        ])->render();
        $this->assertSame(1, substr_count($html, 'Synthetic hero caption'));
        $this->assertSame(1, substr_count($html, 'Synthetic photographer'));
        $this->assertStringContainsString('project-hero-transition__caption', $html);
        $this->assertGreaterThan(strpos($html, 'project-hero-transition__copy'), strpos($html, 'Synthetic hero caption'));
    }

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

    public function test_own_images_do_not_show_redundant_credits_or_licenses(): void
    {
        $context = [
            'asset' => ['rights' => 'jack', 'cc' => 'Old attribution'],
            'site' => ['name' => 'Jack Ruder', 'content_license_name' => 'CC BY-SA 4.0', 'content_license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/deed.de'],
        ];
        $attribution = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/attribution.antlers.html')), $context);
        $overlay = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/overlays.antlers.html')), $context);

        foreach ([$attribution, $overlay] as $html) {
            $this->assertStringNotContainsString('Jack Ruder', $html);
            $this->assertStringNotContainsString('CC BY-SA 4.0', $html);
            $this->assertStringNotContainsString('Old attribution', $html);
        }
        $this->assertStringNotContainsString('media-overlay__credit', $overlay);
    }

    public function test_asset_ai_metadata_inherits_into_the_shared_media_overlay(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/overlays.antlers.html')), [
            'asset' => [
                'ai_involvement' => 'partially_modified',
                'eu_ai_disclosure' => 'Hintergrund teilweise mit KI ergänzt',
                'ai_icon_appearance' => 'white-50',
                'credit_appearance' => 'light',
                'rights' => 'cc',
                'cc' => 'Foto: Ada Beispiel, https://example.org/foto',
            ],
        ]);

        $this->assertStringContainsString('/assets/ai-labels/partially-modified/white-50.svg', $html);
        $this->assertStringContainsString('aria-label="Hintergrund teilweise mit KI erg&auml;nzt"', $html);
        $this->assertStringNotContainsString('Hintergrund teilweise mit KI erg&auml;nzt</span>', $html);
        $this->assertStringContainsString('media-overlay__credit--light', $html);
        $this->assertStringContainsString('aria-label="Bildnachweis"', $html);
        $this->assertStringContainsString('Foto: Ada Beispiel', $html);
        $this->assertStringContainsString('href="https://example.org/foto"', $html);
    }

    public function test_unknown_credit_appearance_falls_back_to_the_existing_dark_treatment(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/overlays.antlers.html')), [
            'asset' => [
                'rights' => 'cc',
                'cc' => 'Foto: Ada Beispiel',
                'credit_appearance' => 'javascript:alert(1)',
            ],
        ]);

        $this->assertStringContainsString('media-overlay__credit--dark', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_legacy_ai_disclosure_remains_the_accessible_name_for_existing_metadata(): void
    {
        $html = (string) Antlers::parse(file_get_contents(resource_path('views/partials/media/overlays.antlers.html')), [
            'asset' => [
                'ai_involvement' => 'basic_disclosure',
                'ai_disclosure' => 'Bestehender KI-Hinweis',
            ],
        ]);

        $this->assertStringContainsString('aria-label="Bestehender KI-Hinweis"', $html);
        $this->assertStringNotContainsString('Bestehender KI-Hinweis</span>', $html);
    }

    public function test_legacy_eu_disclosure_gets_a_basic_icon_unless_involvement_is_explicitly_none(): void
    {
        $partial = file_get_contents(resource_path('views/partials/media/overlays.antlers.html'));
        $asset = Asset::find('assets::projects/ai-ctx/effect-ai-ctx.png');
        $this->assertNotNull($asset);
        $this->assertFalse($asset->data()->has('ai_involvement'));
        $asset->set('eu_ai_disclosure', 'Gespeicherter EU-KI-Hinweis');

        $legacy = (string) Antlers::parse($partial, ['asset' => $asset]);

        $asset->set('ai_involvement', 'none');
        $explicitlyNone = (string) Antlers::parse($partial, ['asset' => $asset]);

        $this->assertStringContainsString('/assets/ai-labels/basic/black.svg', $legacy);
        $this->assertStringContainsString('aria-label="Gespeicherter EU-KI-Hinweis"', $legacy);
        $this->assertStringNotContainsString('Gespeicherter EU-KI-Hinweis</span>', $legacy);
        $this->assertStringNotContainsString('/assets/ai-labels/', $explicitlyNone);
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
        $this->assertStringContainsString('aria-label="Teilweise mit KI ver&auml;ndert"', $html);
        $this->assertStringNotContainsString('Teilweise mit KI ver&auml;ndert</span>', $html);
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
        $this->assertStringContainsString('aria-label="&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
        $this->assertStringNotContainsString('&lt;script&gt;</span>', $html);
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
        foreach (['alt', 'caption', 'rights', 'cc', 'ai_involvement', 'eu_ai_disclosure', 'ai_icon_appearance', 'credit_appearance'] as $field) {
            $this->assertStringContainsString('handle: '.$field, $blueprint);
        }
        $this->assertStringContainsString('key: none', $blueprint);
        $this->assertStringNotContainsString('default: none', $blueprint);
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
