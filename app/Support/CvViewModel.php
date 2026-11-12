<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\GlobalSet;

final class CvViewModel
{
    public function __construct(private readonly CvProfileContentResolver $resolver) {}

    public function make(?string $profileSlug = null, bool $authorized = false, ?string $capabilityToken = null, ?array $resolved = null): array
    {
        $resolved ??= $this->resolver->resolve($profileSlug);
        $identity = $resolved['public_identity'];
        $profile = $resolved['profile'];
        $canonicalUrl = rtrim((string) config('cv.canonical_base_url'), '/')
            .'/cv'.($profile && ! empty($profile['slug']) ? '/'.$profile['slug'] : '');
        $interactiveUrl = $canonicalUrl.($authorized && $capabilityToken ? '#cv='.$capabilityToken : '');
        $private = $authorized ? CvPrivateData::reveal(GlobalSet::find('cv')->inDefaultSite()) : [];

        return [
            'name' => $identity['name'],
            'location' => $identity['location'],
            'profile' => $profile,
            'template' => $resolved['template'],
            'accent' => $resolved['application_accent'] ?? $profile['accent'] ?? '#5adbbd',
            'canonical_url' => $canonicalUrl,
            'interactive_url' => $interactiveUrl,
            'photo' => $this->assetPath($identity['profile_image_path']),
            'contact' => [
                'public_email' => $identity['email'],
                'email' => $private['private_email'] ?? null,
                'phone' => $private['phone'] ?? null,
                'address' => $this->address($private),
                'website' => $identity['website'],
                'github' => $identity['github_url'],
                'codeberg' => $identity['codeberg_url'],
                'social_links' => $identity['social_links'],
            ],
            'about' => $this->plainText($resolved['sections']['about']['value']),
            'knowledge' => (array) $resolved['sections']['knowledge']['value'],
            'soft_skills' => (array) $resolved['sections']['soft_skills']['value'],
            'experience' => $this->collection('experience'),
            'education' => $this->collection('education'),
            'projects' => $this->entryReferences($resolved['sections']['projects']['value']),
            'publications' => $this->entryReferences($resolved['sections']['publications']['value']),
            'milestones' => $this->milestones($resolved['sections']['milestones']['value']),
        ];
    }

    public function forApplication(\Statamic\Contracts\Entries\Entry $application, ?array $signatureSource = null): array
    {
        // Corrupt configured private data must stop the export, never become public masks.
        $private = CvPrivateData::reveal(GlobalSet::find('cv')->inDefaultSite(), strict: true);
        $resolved = $this->resolver->resolveForApplication($application);
        $cv = $this->make(null, true, null, $resolved);
        // Legacy CV profile accents may predate hex validation. Normalize only the export view model;
        // never rewrite the shared profile or its stored data.
        $cv['accent'] = '#'.Latex::normalizeHexColor((string) $cv['accent']);
        $addressParts = array_values(array_filter([
            CvPrivateData::streetLine($private),
            trim(($private['postal_code'] ?? '').' '.($private['city'] ?? '')),
            $private['country'] ?? null,
        ]));
        $cv['contact']['address'] = implode(', ', $addressParts);
        $signature = ApplicationData::group($application, 'signature');
        $handwritten = ($signature['mode'] ?? 'none') === 'handwritten';
        if ($handwritten && (! is_array($signatureSource) || ! is_file($signatureSource['path'] ?? ''))) {
            throw new \RuntimeException('Die gespeicherte private Handschrift ist für diesen Export nicht verfügbar.');
        }
        $cv['signature_image'] = $handwritten ? $signatureSource['path'] : null;
        $cv['signature_digest'] = $handwritten ? $signatureSource['sha256'] : null;
        $cv['signature_width_mm'] = $handwritten ? (int) ($signature['width_mm'] ?? 46) : null;
        $cv['signature_baseline_mm'] = $handwritten ? (int) ($signature['baseline_mm'] ?? 0) : null;
        $cv['signed_by'] = $handwritten ? $cv['name'] : null;
        $cv['signed_date'] = $handwritten ? Carbon::parse(ApplicationData::date($application))->locale('de')->translatedFormat('j. F Y') : null;
        $cv['signed_place'] = $handwritten ? trim((string) ($private['city'] ?? '')) : null;

        return [...$cv, 'private_export' => true,
            'sender_return_address' => implode(', ', array_slice($addressParts, 0, 2)),
        ];
    }

    private function collection(string $handle): array
    {
        return Entries::query()->where('collection', $handle)->where('published', true)->get()
            ->sortByDesc(fn (Entry $entry) => (string) $entry->value('started_at'))
            ->map(fn (Entry $entry) => $this->entry($entry))->values()->all();
    }

    private function entryReferences(mixed $references): array
    {
        $references = is_array($references) ? $references : ($references ? [$references] : []);

        return collect($references)
            ->map(fn ($reference) => $reference instanceof Entry ? $reference : Entries::find((string) $reference))
            ->filter(fn ($entry) => $entry instanceof Entry && $entry->published())
            ->map(fn (Entry $entry) => $this->entry($entry))
            ->values()->all();
    }

    private function milestones(mixed $references): array
    {
        $references = is_array($references) ? $references : ($references ? [$references] : []);

        return collect($references)
            ->map(fn ($reference) => $reference instanceof Entry ? $reference : Entries::find((string) $reference))
            ->filter(fn ($entry) => $entry instanceof Entry && $entry->collectionHandle() === 'cv_milestones' && $entry->published())
            ->map(fn (Entry $entry) => [
                'title' => (string) $entry->value('title', ''),
                'chronology' => (string) $entry->value('chronology', ''),
                'date' => (string) $entry->value('date_label', ''),
                'organisation' => (string) $entry->value('organisation', ''),
                'summary' => (string) $entry->value('summary', ''),
                'relations' => $this->milestoneRelations($entry),
            ])
            ->values()->all();
    }

    private function milestoneRelations(Entry $entry): array
    {
        $relations = [];
        foreach (['related_projects', 'related_experience', 'related_education'] as $handle) {
            foreach ($this->entryReferences($entry->value($handle, [])) as $item) {
                $relations[] = ['title' => $item['title'], 'url' => $item['url']];
            }
        }
        foreach ((array) $entry->value('links', []) as $link) {
            $href = $link['href'] ?? '';
            if (is_string($href) && $href !== '') {
                $relations[] = ['title' => (string) ($link['label'] ?? $href), 'url' => $href];
            }
        }

        return $relations;
    }

    private function entry(Entry $entry): array
    {
        return [
            'title' => (string) $entry->value('title', ''),
            'programme' => (string) $entry->value('programme', ''),
            'organisation' => (string) $entry->value('organisation', ''),
            'period' => $this->period($entry),
            'summary' => (string) $entry->value('summary', ''),
            'url' => rtrim((string) config('cv.canonical_base_url'), '/').$entry->url(),
            'type' => ucfirst((string) ($entry->value('publication_type') ?? '')),
        ];
    }

    private function period(Entry $entry): string
    {
        if ($entry->value('period_label')) {
            return (string) $entry->value('period_label');
        }
        $start = $entry->value('started_at');
        $end = $entry->value('ended_at');
        if (! $start) {
            return $entry->date()?->format('m/Y') ?? '';
        }

        return Carbon::parse($start)->format('m/Y').' – '.($end ? Carbon::parse($end)->format('m/Y') : 'heute');
    }

    private function plainText(mixed $bard): string
    {
        if (is_string($bard)) {
            return trim(strip_tags($bard));
        }
        if (! is_array($bard)) {
            return '';
        }
        $walk = function (array $nodes) use (&$walk): array {
            $parts = [];
            foreach ($nodes as $node) {
                if (isset($node['text'])) {
                    $parts[] = $node['text'];
                }
                if (isset($node['content']) && is_array($node['content'])) {
                    $parts = [...$parts, ...$walk($node['content'])];
                }
            }

            return $parts;
        };

        return trim(implode(' ', $walk($bard)));
    }

    private function assetPath(mixed $asset): ?string
    {
        return is_string($asset) && $asset !== '' ? public_path('assets/'.$asset) : null;
    }

    private function address(array $private): ?string
    {
        $parts = array_values(array_filter([
            trim(($private['street'] ?? '').' '.($private['house_number'] ?? '')),
            trim(($private['postal_code'] ?? '').' '.($private['city'] ?? '')),
            $private['country'] ?? null,
        ]));

        return $parts ? implode(', ', $parts) : null;
    }
}
