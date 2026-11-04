<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;

class SearchDocumentsController extends Controller
{
    private const PUBLIC_COLLECTIONS = ['pages', 'projects', 'posts', 'publications', 'education', 'experience'];

    public function sitemap(): Response
    {
        $baseUrl = $this->baseUrl();
        $globals = GlobalSet::findByHandle('search_agent_optimization')?->inDefaultSite();
        $noindexRoutes = (array) ($globals?->get('noindex_routes') ?? []);
        $paths = collect(['/projekte', '/blog', '/publikationen', '/aktuell', '/cv'])
            ->reject(fn ($path) => in_array($path, $noindexRoutes, true))
            ->values()
            ->all();

        foreach (self::PUBLIC_COLLECTIONS as $collection) {
            foreach (Entry::query()->where('collection', $collection)->get() as $entry) {
                $path = $entry->url();

                if (! $entry->published()
                    || ! $path
                    || filter_var($entry->value('noindex') ?? false, FILTER_VALIDATE_BOOLEAN)
                    || in_array('/'.ltrim($path, '/'), $noindexRoutes, true)
                    || $entry->value('project_status') === 'confidential') {
                    continue;
                }

                $paths[] = $path;
            }
        }

        $writer = new \XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach (array_unique($paths) as $path) {
            $writer->startElement('url');
            $writer->writeElement('loc', $baseUrl.'/'.ltrim($path, '/'));
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return response($writer->outputMemory(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function llms(): Response
    {
        return $this->markdown('llms');
    }

    public function llmsFull(): Response
    {
        return $this->markdown('llms_full');
    }

    private function markdown(string $field): Response
    {
        $globals = GlobalSet::findByHandle('search_agent_optimization')?->inDefaultSite();
        $markdown = trim((string) $globals?->get($field));
        abort_if($markdown === '', 404);

        $baseUrl = $this->baseUrl();
        $markdown = preg_replace_callback('/(\]\()([^\s)]+)(\))/u', function (array $match) use ($baseUrl): string {
            $target = $match[2];

            if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|#)~i', $target)) {
                return $match[0];
            }

            $path = str_starts_with($target, '/') ? $target : '/'.$target;

            return $match[1].$baseUrl.$path.$match[3];
        }, $markdown) ?? $markdown;

        return response($markdown."\n", 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function baseUrl(): string
    {
        $globals = GlobalSet::findByHandle('site')?->inDefaultSite();

        return rtrim((string) ($globals?->get('website_url') ?: config('app.url')), '/');
    }
}
