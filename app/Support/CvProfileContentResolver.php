<?php

namespace App\Support;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;

final class CvProfileContentResolver
{
    private const SECTIONS = ['about', 'knowledge', 'soft_skills', 'projects', 'publications', 'milestones'];

    public function resolve(?string $profileSlug = null): array
    {
        $cv = GlobalSet::find('cv')?->inDefaultSite();
        $site = GlobalSet::find('site')?->inDefaultSite();
        if (! $cv instanceof Variables || ! $site instanceof Variables) {
            throw new \RuntimeException('CV and site globals are required.');
        }

        $profile = $profileSlug === null
            ? null
            : Entries::query()->where('collection', 'cv_profiles')->where('slug', $profileSlug)->first();
        if ($profileSlug !== null && (! $profile instanceof Entry || ! $profile->published())) {
            abort(404);
        }

        $sourceMode = $profile?->value('source_mode') ?: 'custom';
        if (! in_array($sourceMode, ['custom', 'template_override', 'template_only'], true)) {
            $sourceMode = 'custom';
        }
        $template = $this->referencedEntry($profile?->value('content_template'));
        if ($template && $template->collectionHandle() !== 'cv_profile_templates') {
            $template = null;
        }

        $sections = [];
        foreach (self::SECTIONS as $handle) {
            $selection = $this->sectionSelection($profile, $sourceMode, $handle);
            $owner = match (true) {
                $selection === 'hide' => null,
                $selection === 'override' => $profile,
                $sourceMode === 'template_only', $sourceMode === 'template_override' => $template,
                default => $cv,
            };
            $sections[$handle] = $this->section($owner, $handle);
        }

        $templateTimeline = $template?->value('interactive_timeline');
        $globalTimeline = (bool) $cv->value('interactive_timeline', false);
        $timelineSelection = $profile?->value('interactive_timeline') ?: 'inherit';
        $timelineEnabled = match ($timelineSelection) {
            'show' => true,
            'hide' => false,
            default => match ($sourceMode) {
                'template_override', 'template_only' => $template ? (bool) ($templateTimeline ?? $globalTimeline) : false,
                default => $globalTimeline,
            },
        };

        return [
            'source_mode' => $sourceMode,
            'profile' => $profile ? [
                'slug' => $profile->slug(),
                'title' => (string) $profile->value('title', ''),
                'recipient' => (string) $profile->value('recipient_name', ''),
                'year' => (string) $profile->value('application_year', ''),
                'context' => (string) $profile->value('optional_context', ''),
                'accent' => (string) $profile->value('accent_color', '#9ee9cf'),
            ] : null,
            'template' => $template ? ['id' => $template->id(), 'title' => (string) $template->value('title', '')] : null,
            'public_identity' => [
                'name' => (string) $site->value('name', ''),
                'email' => (string) $site->value('email', ''),
                'location' => (string) $site->value('location', ''),
                'website' => (string) $site->value('website_url', ''),
                'github_url' => (string) $site->value('github_url', ''),
                'codeberg_url' => (string) $site->value('codeberg_url', ''),
                'short_bio' => (string) $site->value('short_bio', ''),
                'profile_image' => $this->augmentedValue($site, 'profile_image'),
                'profile_image_path' => $site->value('profile_image'),
            ],
            'sections' => $sections,
            'interactive_timeline' => $timelineEnabled,
            'timeline_start_year' => (int) $cv->value('timeline_start_year', now()->year),
            'print' => [
                'place' => (string) $cv->value('print_place', ''),
                'date' => (string) $cv->value('print_date', ''),
                'signature' => $this->augmentedValue($cv, 'signature'),
            ],
        ];
    }

    private function sectionSelection(?Entry $profile, string $sourceMode, string $handle): string
    {
        if ($sourceMode === 'template_only') {
            return 'inherit';
        }

        $selectorHandle = $handle.'_source';
        $raw = $profile?->data()->all() ?? [];
        if (array_key_exists($selectorHandle, $raw) && in_array($raw[$selectorHandle], ['inherit', 'override', 'hide'], true)) {
            return $raw[$selectorHandle];
        }

        // Older custom profiles used a saved field value as an implicit override.
        if ($sourceMode === 'custom' && $this->hasRawValue($profile, $handle)) {
            return 'override';
        }

        return 'inherit';
    }

    private function section(Entry|Variables|null $owner, string $handle): array
    {
        if ($owner === null) {
            return ['source' => 'hide', 'value' => [], 'display' => null];
        }

        $value = $owner->value($handle);
        $isReferenceSection = in_array($handle, ['projects', 'publications', 'milestones'], true);
        if ($isReferenceSection) {
            $value = $this->publicEntries($value, $handle);
        }
        $display = $isReferenceSection ? ($value ?? []) : ($this->augmentedValue($owner, $handle) ?? $value ?? []);

        return [
            'source' => $owner instanceof Entry && $owner->collectionHandle() === 'cv_profile_templates' ? 'template' : 'custom',
            'value' => $value ?? [],
            'display' => empty($display) ? null : $display,
        ];
    }

    private function publicEntries(mixed $references, string $handle): array
    {
        $references = is_array($references) ? $references : ($references ? [$references] : []);

        return collect($references)
            ->map(fn ($reference) => $reference instanceof Entry ? $reference : Entries::find((string) $reference))
            ->filter(function ($entry) use ($handle) {
                if (! $entry instanceof Entry || ! $entry->published()) {
                    return false;
                }
                if ($handle === 'projects') {
                    return $entry->collectionHandle() === 'projects' && $entry->value('project_status') !== 'confidential';
                }
                if ($handle === 'publications') {
                    return $entry->collectionHandle() === 'publications';
                }

                return $entry->collectionHandle() === 'cv_milestones';
            })
            ->values()
            ->all();
    }

    private function hasRawValue(?Entry $entry, string $handle): bool
    {
        return $entry && array_key_exists($handle, $entry->data()->all());
    }

    private function augmentedValue(Entry|Variables $owner, string $handle): mixed
    {
        return $owner->toAugmentedArray([$handle])[$handle] ?? null;
    }

    private function referencedEntry(mixed $reference): ?Entry
    {
        if ($reference instanceof Entry) {
            return $reference;
        }
        if (is_array($reference)) {
            $reference = reset($reference);
        }
        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $entry = Entries::find($reference);

        return $entry instanceof Entry ? $entry : null;
    }
}
