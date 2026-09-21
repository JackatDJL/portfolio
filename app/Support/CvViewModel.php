<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\GlobalSet;

final class CvViewModel
{
    public function make(?string $profileSlug = null, bool $authorized = false): array
    {
        $globals = GlobalSet::find('cv')?->inDefaultSite();
        if ($globals === null) throw new \RuntimeException('CV globals are unavailable.');
        $profile = $profileSlug === null ? null : Entries::query()->where('collection', 'cv_profiles')->where('slug', $profileSlug)->first();
        if ($profileSlug !== null && ! $profile instanceof Entry) abort(404);
        $value = fn (string $key, mixed $default = null) => $globals->value($key) ?? $default;
        $profileValue = fn (string $key, mixed $default = null) => $profile?->value($key) ?? $default;
        $selected = fn (string $key) => $this->entries((array) ($profileValue($key) ?: $value($key, [])));
        $private = $authorized ? CvPrivateData::reveal($globals) : [];
        return [
            'name' => (string) $value('full_name', 'Jack Ruder'), 'location' => (string) $value('public_location', ''),
            'profile' => $profile ? ['slug' => $profile->slug(), 'recipient' => (string) $profileValue('recipient_name', ''), 'year' => (string) $profileValue('application_year', ''), 'context' => (string) $profileValue('optional_context', '')] : null,
            'accent' => (string) $profileValue('accent_color', '#9ee9cf'),
            'canonical_url' => rtrim((string) config('cv.canonical_base_url'), '/').'/cv'.($profile ? '/'.$profile->slug() : ''),
            'photo' => $this->assetPath($value('profile_image')),
            'contact' => ['email' => $private['private_email'] ?? null, 'phone' => $private['phone'] ?? null, 'address' => $this->address($private), 'website' => (string) $value('website', ''), 'github' => (string) $value('github_url', ''), 'codeberg' => (string) $value('codeberg_url', '')],
            'about' => $this->plainText($value('about', [])), 'knowledge' => (array) $value('knowledge', []), 'soft_skills' => (array) $value('soft_skills', []),
            'experience' => $this->collection('experience'), 'education' => $this->collection('education'), 'projects' => $selected('projects'), 'publications' => $selected('publications'),
        ];
    }
    private function collection(string $handle): array { return Entries::query()->where('collection', $handle)->where('published', true)->get()->sortByDesc(fn (Entry $entry) => (string) $entry->value('started_at'))->map(fn (Entry $entry) => $this->entry($entry))->values()->all(); }
    private function entries(array $ids): array { return collect($ids)->map(fn ($id) => Entries::find((string) $id))->filter()->filter(fn (Entry $entry) => $entry->published())->map(fn (Entry $entry) => $this->entry($entry))->values()->all(); }
    private function entry(Entry $entry): array { return ['title' => (string) $entry->value('title'), 'programme' => (string) $entry->value('programme'), 'organisation' => (string) $entry->value('organisation'), 'period' => $this->period($entry), 'summary' => (string) $entry->value('summary'), 'url' => rtrim((string) config('cv.canonical_base_url'), '/').$entry->url(), 'type' => ucfirst((string) ($entry->value('publication_type') ?? ''))]; }
    private function period(Entry $entry): string { if ($entry->value('period_label')) return (string) $entry->value('period_label'); $start = $entry->value('started_at'); $end = $entry->value('ended_at'); if (! $start) return $entry->date()?->format('m/Y') ?? ''; return Carbon::parse($start)->format('m/Y').' – '.($end ? Carbon::parse($end)->format('m/Y') : 'heute'); }
    private function plainText(mixed $bard): string { if (! is_array($bard)) return ''; $walk = function (array $nodes) use (&$walk): array { $parts = []; foreach ($nodes as $node) { if (isset($node['text'])) $parts[] = $node['text']; if (isset($node['content']) && is_array($node['content'])) $parts = [...$parts, ...$walk($node['content'])]; } return $parts; }; return trim(implode(' ', $walk($bard))); }
    private function assetPath(mixed $asset): ?string { return is_string($asset) && $asset !== '' ? public_path('assets/'.$asset) : null; }
    private function address(array $private): ?string { $parts = array_values(array_filter([trim(($private['street'] ?? '').' '.($private['house_number'] ?? '')), trim(($private['postal_code'] ?? '').' '.($private['city'] ?? '')), $private['country'] ?? null])); return $parts ? implode(', ', $parts) : null; }
}
