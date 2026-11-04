<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;

class SearchAgentOptimizationController extends Controller
{
    private const INDEXABLE_COLLECTIONS = [
        'pages', 'projects', 'posts', 'publications', 'education', 'experience',
    ];

    private const ROUTES = [
        '/projekte' => 'Projekte', '/blog' => 'Blog',
        '/publikationen' => 'Veröffentlichungen', '/aktuell' => 'Aktuell', '/cv' => 'Lebenslauf',
    ];

    public function index(Request $request): View
    {
        $entries = collect(self::INDEXABLE_COLLECTIONS)
            ->flatMap(fn (string $handle) => Entry::query()->where('collection', $handle)->get())
            ->filter(fn ($entry) => $entry->published() && $entry->url())
            ->map(fn ($entry) => [
                'id' => $entry->id(),
                'title' => (string) ($entry->value('title') ?: $entry->slug()),
                'collection' => $entry->collection()->title(),
                'url' => $entry->url(),
                'noindex' => filter_var($entry->value('noindex') ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values();

        $globals = GlobalSet::findByHandle('search_agent_optimization')?->inDefaultSite();
        $noindexRoutes = (array) ($globals?->get('noindex_routes') ?? []);
        $routes = collect(self::ROUTES)->map(fn (string $title, string $path) => [
            'id' => 'route:'.$path,
            'title' => $title,
            'collection' => 'Seite',
            'url' => $path,
            'noindex' => in_array($path, $noindexRoutes, true),
        ]);
        $entries = $entries->concat($routes)->sortBy(fn (array $entry) => mb_strtolower($entry['title']))->values();

        $filter = $request->query('filter', 'all');
        abort_unless(in_array($filter, ['all', 'indexed', 'noindex'], true), 400);

        $query = mb_strtolower(trim((string) $request->query('q', '')));
        $visibleEntries = $entries
            ->when($filter === 'indexed', fn ($items) => $items->where('noindex', false))
            ->when($filter === 'noindex', fn ($items) => $items->where('noindex', true))
            ->when($query !== '', fn ($items) => $items->filter(fn ($entry) =>
                str_contains(mb_strtolower($entry['title'].' '.$entry['collection'].' '.$entry['url']), $query)
            ));

        return view('cp.search-agent-optimization', [
            'entries' => $visibleEntries,
            'filter' => $filter,
            'query' => (string) $request->query('q', ''),
            'counts' => [
                'all' => $entries->count(),
                'indexed' => $entries->where('noindex', false)->count(),
                'noindex' => $entries->where('noindex', true)->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:index,noindex'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['required', 'string', 'max:220'],
            'filter' => ['nullable', 'in:all,indexed,noindex'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $entries = collect(self::INDEXABLE_COLLECTIONS)
            ->flatMap(fn (string $handle) => Entry::query()->where('collection', $handle)->get());
        $allowed = $entries->keyBy(fn ($entry) => $entry->id());
        $changed = 0;
        $globalSet = GlobalSet::findByHandle('search_agent_optimization')?->inDefaultSite();
        $noindexRoutes = collect((array) ($globalSet?->get('noindex_routes') ?? []));

        foreach (array_unique($data['items']) as $id) {
            if (str_starts_with($id, 'route:')) {
                $path = substr($id, 6);
                abort_unless(array_key_exists($path, self::ROUTES), 422);
                $noindexRoutes = $data['action'] === 'noindex'
                    ? $noindexRoutes->push($path)->unique()->values()
                    : $noindexRoutes->reject(fn ($route) => $route === $path)->values();
                $changed++;
                continue;
            }

            $entry = $allowed->get($id);
            if (! $entry || ! $entry->published() || ! $entry->url()) {
                continue;
            }

            $entry->set('noindex', $data['action'] === 'noindex');
            $entry->save();
            $changed++;
        }

        if ($globalSet && $changed > 0) {
            $globalSet->set('noindex_routes', $noindexRoutes->all());
            $globalSet->save();
        }

        return redirect()->route('statamic.cp.search-agent-optimization.index', array_filter([
            'filter' => $data['filter'] ?? null,
            'q' => $data['q'] ?? null,
        ]))->with('status', sprintf('%d Eintrag/Einträge aktualisiert.', $changed));
    }
}
