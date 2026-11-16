<?php

namespace App\Tags;

use App\Support\SearchPath;
use App\Support\SocialLinks;
use Statamic\Facades\AssetContainer;
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

        // Do not publish an unlabelled source as a social preview. A labelled
        // derivative can be added later without changing or overwriting the source.
        $asset = $this->assetForImage($image, $baseUrl);
        if (is_object($asset) && method_exists($asset, 'get')) {
            $aiInvolvement = $asset->get('ai_involvement');
            $aiInvolvement = $aiInvolvement instanceof Value ? $aiInvolvement->value() : $aiInvolvement;
            if (is_string($aiInvolvement) && $aiInvolvement !== 'none' && $aiInvolvement !== '') {
                return null;
            }

            if ($aiInvolvement === null || $aiInvolvement === '') {
                $disclosure = $asset->get('eu_ai_disclosure') ?? $asset->get('ai_disclosure');
                $disclosure = $disclosure instanceof Value ? $disclosure->value() : $disclosure;
                if (is_string($disclosure) && trim($disclosure) !== '') {
                    return null;
                }
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

    /** Resolves an Asset value, container-relative path, or local asset URL. */
    private function assetForImage(mixed $image, string $baseUrl): ?object
    {
        if (is_object($image) && method_exists($image, 'get')) {
            return $image;
        }

        if (! is_string($image) || trim($image) === '') {
            return null;
        }

        $reference = trim($image);
        if (str_contains($reference, '::')) {
            $asset = \Statamic\Facades\Asset::find($reference);

            return is_object($asset) ? $asset : null;
        }

        $parts = parse_url($reference);
        if ($parts === false) {
            return null;
        }

        $path = $this->normalizePath((string) ($parts['path'] ?? ''));
        if ($path === null) {
            return null;
        }
        $referenceHost = isset($parts['host']) ? strtolower($parts['host']) : null;
        $siteHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));

        foreach (AssetContainer::all() as $container) {
            if (! is_object($container) || ! method_exists($container, 'url') || ! method_exists($container, 'asset')) {
                continue;
            }

            if (method_exists($container, 'private') && $container->private()) {
                continue;
            }

            $containerUrl = $container->url();
            if (! is_string($containerUrl) || $containerUrl === '') {
                continue;
            }

            $containerPath = $this->normalizePath((string) (parse_url($containerUrl, PHP_URL_PATH) ?: $containerUrl));
            if ($containerPath === null) {
                continue;
            }
            $containerPath = rtrim($containerPath, '/') ?: '/';
            $normalizedPath = $path;

            if ($referenceHost !== null) {
                $containerAbsoluteUrl = method_exists($container, 'absoluteUrl') ? $container->absoluteUrl() : null;
                $containerHost = is_string($containerAbsoluteUrl)
                    ? strtolower((string) parse_url($containerAbsoluteUrl, PHP_URL_HOST))
                    : '';

                if (! in_array($referenceHost, array_filter([$siteHost, $containerHost]), true)) {
                    continue;
                }

                $prefix = rtrim($containerPath, '/');
                if ($prefix !== '' && ! str_starts_with($normalizedPath, $prefix.'/')) {
                    continue;
                }

                $assetPath = ltrim(substr($normalizedPath, strlen($prefix)), '/');
            } elseif (str_starts_with($normalizedPath, rtrim($containerPath, '/').'/')) {
                $assetPath = ltrim(substr($normalizedPath, strlen(rtrim($containerPath, '/'))), '/');
            } elseif (! str_starts_with($reference, '/')) {
                $assetPath = ltrim($path, '/');
                $containerPrefix = trim($containerPath, '/').'/';
                if (str_starts_with($assetPath, $containerPrefix)) {
                    $assetPath = substr($assetPath, strlen($containerPrefix));
                }
            } else {
                continue;
            }

            $assetPath = ltrim($this->normalizeDecodedPath($assetPath) ?? '', '/');
            if ($assetPath === '') {
                continue;
            }

            $asset = $container->asset($assetPath);
            if (is_object($asset)) {
                return $asset;
            }
        }

        return null;
    }

    /** Resolves URL dot segments before matching a path to a Statamic asset. */
    private function normalizePath(string $path): ?string
    {
        return $this->normalizeDecodedPath(rawurldecode($path));
    }

    /** Normalizes an already decoded path without changing literal percent sequences. */
    private function normalizeDecodedPath(string $path): ?string
    {
        $segments = explode('/', str_replace('\\', '/', $path));
        $normalized = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($normalized);
                continue;
            }

            if (str_contains($segment, "\0")) {
                return null;
            }

            $normalized[] = $segment;
        }

        return '/'.implode('/', $normalized);
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
