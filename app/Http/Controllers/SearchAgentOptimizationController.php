<?php

namespace App\Http\Controllers;

use App\Support\SearchPath;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;

class SearchAgentOptimizationController extends Controller
{
    private const INDEXABLE_COLLECTIONS = [
        'pages', 'projects', 'posts', 'publications', 'education', 'experience',
    ];

    private const ROUTES = [
        '/projekte' => 'Projekte', '/blog' => 'Blog',
        '/publikationen' => 'Veröffentlichungen', '/aktuell' => 'Aktuell', '/cv' => 'Lebenslauf',
    ];

    private const PAGE_SIZE = 100;

    /** Builds the searchable, filterable, paginated list of published entries and routes. */
    public function index(Request $request): View
    {
        $globals = GlobalSet::findByHandle('search_agent_optimization')?->inDefaultSite();
        $noindexRoutes = SearchPath::normalizeList($globals?->get('noindex_routes') ?? []);
        $entries = collect(self::INDEXABLE_COLLECTIONS)
            ->flatMap(fn (string $handle) => Entry::query()->where('collection', $handle)->get())
            ->filter(fn ($entry) => $entry->status() === 'published' && $entry->url())
            ->map(function ($entry) use ($noindexRoutes) {
                $path = SearchPath::normalize($entry->url());

                return [
                    'id' => $entry->id(),
                    'title' => (string) ($entry->value('title') ?: $entry->slug()),
                    'collection' => $entry->collection()->title(),
                    'url' => $entry->url(),
                    'noindex' => filter_var($entry->value('noindex') ?? false, FILTER_VALIDATE_BOOLEAN)
                        || ($path !== null && in_array($path, $noindexRoutes, true))
                        || $entry->value('project_status') === 'confidential',
                ];
            })
            ->values();

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
            ))
            ->values();

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $visibleEntries->forPage($currentPage, self::PAGE_SIZE)->values(),
            $visibleEntries->count(),
            self::PAGE_SIZE,
            $currentPage,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()],
        );

        return view('cp.search-agent-optimization', [
            'entries' => $paginator,
            'filter' => $filter,
            'query' => (string) $request->query('q', ''),
            'counts' => [
                'all' => $entries->count(),
                'indexed' => $entries->where('noindex', false)->count(),
                'noindex' => $entries->where('noindex', true)->count(),
            ],
        ]);
    }

    /** Applies an authorized bulk indexing update and serializes route-list changes. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:index,noindex'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*' => ['required', 'string', 'max:220'],
            'filter' => ['nullable', 'in:all,indexed,noindex'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $items = array_values(array_unique($data['items']));
        $entries = collect(self::INDEXABLE_COLLECTIONS)
            ->flatMap(fn (string $handle) => Entry::query()->where('collection', $handle)->get())
            ->keyBy(fn ($entry) => $entry->id());
        $routePaths = [];
        $selectedEntries = [];

        // Validate the full submitted selection and its permissions before any entry is saved.
        foreach ($items as $id) {
            if (str_starts_with($id, 'route:')) {
                $path = substr($id, 6);
                abort_unless(array_key_exists($path, self::ROUTES), 422);
                $routePaths[] = $path;

                continue;
            }

            $entry = $entries->get($id);
            abort_unless($entry && $entry->status() === 'published' && $entry->url(), 422);

            if ($data['action'] === 'index' && $entry->value('project_status') === 'confidential') {
                continue;
            }

            abort_unless(User::current()->can('edit', $entry), 403);
            $selectedEntries[] = $entry;
        }

        $cache = Cache::store();
        abort_unless($cache->getStore() instanceof LockProvider, 503, 'Index settings cannot be updated safely.');

        try {
            $changed = $cache->lock('search-agent-optimization:noindex-routes', 300)->block(10, function () use ($data, $routePaths, $selectedEntries) {
                $globalSet = GlobalSet::findByHandle('search_agent_optimization')?->inDefaultSite();
                $originalRoutes = SearchPath::normalizeList($globalSet?->get('noindex_routes') ?? []);
                $noindexRoutes = $originalRoutes;

                foreach ($routePaths as $path) {
                    $noindexRoutes = $data['action'] === 'noindex'
                        ? array_values(array_unique([...$noindexRoutes, $path]))
                        : array_values(array_filter($noindexRoutes, fn ($route) => $route !== $path));
                }

                if ($data['action'] === 'index') {
                    foreach ($selectedEntries as $entry) {
                        $path = SearchPath::normalize($entry->url());

                        if ($path !== null && in_array($path, $noindexRoutes, true)) {
                            $noindexRoutes = array_values(array_filter($noindexRoutes, fn ($route) => $route !== $path));
                        }
                    }
                }

                $routesChanged = $noindexRoutes !== $originalRoutes;
                if ($routesChanged) {
                    abort_unless($globalSet, 503, 'Index settings are not configured.');
                    abort_unless(User::current()->can('edit', $globalSet), 403);
                }

                foreach ($selectedEntries as $entry) {
                    $entry->set('noindex', $data['action'] === 'noindex');
                    $entry->save();
                }

                if ($routesChanged) {
                    $globalSet->set('noindex_routes', $noindexRoutes);
                    $globalSet->save();
                }

                return count($routePaths) + count($selectedEntries);
            });
        } catch (LockTimeoutException) {
            abort(409, 'Index settings are being updated. Please try again.');
        }

        return redirect()->route('statamic.cp.search-agent-optimization.index', array_filter([
            'filter' => $data['filter'] ?? null,
            'q' => $data['q'] ?? null,
            'page' => $data['page'] ?? null,
        ]))->with('status', sprintf('%d Eintrag/Einträge aktualisiert.', $changed));
    }
}
