<?php

namespace App\Tags;

use App\Support\SearchPath;
use App\Support\SocialLinks;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\Value;
use Statamic\Tags\Tags;

class SearchMetadata extends Tags
{
    /** Renders canonical, social, robots, and structured metadata for the current page. */
    public function index(): string
    {
        $globals = GlobalSet::findByHandle('site')?->inDefaultSite();
        $searchSettings = GlobalSet::findByHandle('search_agent_optimization')?->inDefaultSite();
        $site = [];
        foreach (['name', 'website_url', 'short_bio', 'github_url', 'codeberg_url', 'profile_image', 'location', 'social_links'] as $key) {
            $site[$key] = $globals?->get($key);
        }
        $site['social_links'] = SocialLinks::normalize($site['social_links'] ?? []);
        $seo = [];
        foreach (['site_description', 'default_og_image', 'schema_same_as', 'person_topics', 'person_job_title', 'noindex_routes'] as $key) {
            $seo[$key] = $searchSettings?->get($key);
        }
        $baseUrl = rtrim((string) ($this->plainValue($site['website_url'] ?? null) ?: config('app.url')), '/');
        $path = SearchPath::normalize(request()->getPathInfo()) ?? '/';
        $canonical = $baseUrl.$path;

        $seoTitle = $this->plainValue($this->context->get('seo_title'));
        $title = trim((string) ($seoTitle ?: $this->plainValue($this->context->get('title')) ?: $this->plainValue($site['name'] ?? null) ?: 'Jack Ruder'));
        $siteName = (string) ($this->plainValue($site['name'] ?? null) ?: 'Jack Ruder');
        if ($path === '/' && ! $seoTitle) {
            $title = $siteName;
        }
        if ($title !== $siteName && ! str_contains($title, $siteName)) {
            $title .= ' | '.$siteName;
        }

        $description = trim((string) (
            $this->plainValue($this->context->get('seo_description'))
            ?: $this->plainValue($this->context->get('summary'))
            ?: $this->plainValue($this->context->get('abstract'))
            ?: $this->plainValue($seo['site_description'] ?? null)
            ?: $this->plainValue($site['short_bio'] ?? null)
        ));
        $image = $this->imageUrl($this->context->get('seo_image'), $baseUrl)
            ?: $this->imageUrl($seo['default_og_image'] ?? null, $baseUrl);
        $noindex = filter_var($this->plainValue($this->context->get('noindex')) ?? false, FILTER_VALIDATE_BOOLEAN)
            || in_array($path, SearchPath::normalizeList($seo['noindex_routes'] ?? []), true)
            || $this->plainValue($this->context->get('project_status')) === 'confidential';
        $collection = $this->context->get('collection');
        $collection = is_object($collection) && method_exists($collection, 'handle') ? $collection->handle() : (string) $collection;
        $type = in_array($collection, ['posts', 'publications'], true) ? 'article' : 'website';

        $site['schema_same_as'] = $this->listValue($seo['schema_same_as'] ?? []);
        $site['person_topics'] = $this->listValue($seo['person_topics'] ?? []);
        $site['person_job_title'] = $this->plainValue($seo['person_job_title'] ?? null);

        $tags = [
            $description !== '' ? '<meta name="description" content="'.e($description).'">' : '',
            '<link rel="canonical" href="'.e($canonical).'">',
            '<meta name="robots" content="'.($noindex ? 'noindex, nofollow' : 'index, follow').'">',
            '<meta property="og:type" content="'.$type.'">',
            '<meta property="og:locale" content="de_DE">',
            '<meta property="og:site_name" content="'.e($siteName).'">',
            '<meta property="og:title" content="'.e($title).'">',
            $description !== '' ? '<meta property="og:description" content="'.e($description).'">' : '',
            '<meta property="og:url" content="'.e($canonical).'">',
            '<meta name="twitter:card" content="'.($image ? 'summary_large_image' : 'summary').'">',
            '<meta name="twitter:title" content="'.e($title).'">',
            $description !== '' ? '<meta name="twitter:description" content="'.e($description).'">' : '',
            $image ? '<meta property="og:image" content="'.e($image).'">' : '',
            $image ? '<meta property="og:image:alt" content="'.e($siteName).'">' : '',
            $image ? '<meta name="twitter:image" content="'.e($image).'">' : '',
            '<script type="application/ld+json">'.$this->jsonLd($site, $baseUrl, $canonical, $title, $description).'</script>',
        ];

        return implode("\n        ", array_filter($tags));
    }

    /** Converts an asset value or path to an absolute image URL. */
    private function imageUrl(mixed $image, string $baseUrl): ?string
    {
        if ($image instanceof Value) {
            $image = $image->value();
        }

        if (is_iterable($image)) {
            foreach ($image as $candidate) {
                $image = $candidate;
                break;
            }
        }

        if (is_object($image)) {
            if (method_exists($image, 'absoluteUrl')) {
                $image = $image->absoluteUrl();
            } elseif (method_exists($image, 'url')) {
                $image = $image->url();
            } elseif ($image instanceof \Stringable) {
                $image = (string) $image;
            }
        }

        if (! is_string($image) || trim($image) === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        if (str_starts_with($image, '/')) {
            return $baseUrl.$image;
        }

        return $baseUrl.'/assets/'.ltrim($image, '/');
    }

    /** Unwraps a Statamic field value before using its underlying data. */
    private function plainValue(mixed $value): mixed
    {
        return $value instanceof Value ? $value->value() : $value;
    }

    /** Converts an iterable Statamic field value into a plain PHP list. */
    private function listValue(mixed $value): array
    {
        $value = $this->plainValue($value);

        if (! is_iterable($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $items[] = $this->plainValue($item);
        }

        return $items;
    }

    /** Encodes the site's Person, WebSite, and current WebPage schema graph. */
    private function jsonLd(array $site, string $baseUrl, string $canonical, string $title, string $description): string
    {
        $sameAs = array_values(array_unique(array_filter([
            $site['github_url'] ?? null,
            $site['codeberg_url'] ?? null,
            ...((array) ($site['schema_same_as'] ?? [])),
            ...array_map(
                fn (array $link) => $link['url'],
                array_filter($site['social_links'] ?? [], fn (array $link) => $link['schema_same_as']),
            ),
        ], fn ($url) => is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https://#i', $url))));

        $person = array_filter([
            '@type' => 'Person',
            '@id' => $baseUrl.'/#person',
            'name' => $site['name'] ?? 'Jack Ruder',
            'url' => $baseUrl.'/',
            'description' => $site['short_bio'] ?? null,
            'jobTitle' => $site['person_job_title'] ?? null,
            'image' => $this->imageUrl($site['profile_image'] ?? null, $baseUrl),
            'sameAs' => $sameAs ?: null,
            'homeLocation' => ! empty($site['location']) ? [
                '@type' => 'Place',
                'name' => $site['location'],
            ] : null,
            'knowsAbout' => (array) ($site['person_topics'] ?? []),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');

        $graph = [
            $person,
            [
                '@type' => 'WebSite',
                '@id' => $baseUrl.'/#website',
                'url' => $baseUrl.'/',
                'name' => $site['name'] ?? 'Jack Ruder',
                'inLanguage' => 'de-DE',
                'publisher' => ['@id' => $baseUrl.'/#person'],
            ],
            [
                '@type' => 'WebPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => $title,
                'description' => $description ?: null,
                'inLanguage' => 'de-DE',
                'isPartOf' => ['@id' => $baseUrl.'/#website'],
                'about' => ['@id' => $baseUrl.'/#person'],
            ],
        ];

        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        ) ?: '{}';
    }
}
