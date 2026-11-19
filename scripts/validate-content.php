<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML;

// Validate the same preprocessed field values that the Statamic publish form uses.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$validationRoot = getenv('CONTENT_SYNC_VALIDATION_ROOT') ?: null;
$validationPaths = null;
if ($validationRoot !== null) {
    $validationRoot = realpath($validationRoot);
    if (! is_string($validationRoot) || ! is_dir($validationRoot)) {
        fwrite(STDERR, "Candidate validation root is unavailable.\n");
        exit(2);
    }

    $decodedPaths = json_decode((string) getenv('CONTENT_SYNC_VALIDATION_PATHS'), true);
    $validationPaths = is_array($decodedPaths) ? array_values(array_filter($decodedPaths, 'is_string')) : [];

    config()->set('statamic.stache.watcher', false);
    config()->set('statamic.stache.cache_store', 'array');
    config()->set('cache.default', 'array');
    config()->set('filesystems.disks.assets.root', $validationRoot.'/public/assets');

    $storeDirectories = [
        'taxonomies' => 'content/taxonomies',
        'terms' => 'content/taxonomies',
        'collections' => 'content/collections',
        'entries' => 'content/collections',
        'navigation' => 'content/navigation',
        'collection-trees' => 'content/trees/collections',
        'nav-trees' => 'content/trees/navigation',
        'globals' => 'content/globals',
        'global-variables' => 'content/globals',
        'asset-containers' => 'content/assets',
    ];
    $stache = app('stache');
    foreach ($storeDirectories as $name => $relative) {
        $store = $stache->store($name);
        if ($store && method_exists($store, 'directory')) {
            $store->directory($validationRoot.'/'.$relative);
            $store->resetMemoizedState();
        }
    }
}

$report = ['entries' => 0, 'entry_errors' => [], 'assets' => 0, 'asset_errors' => [], 'candidate_file_errors' => []];
$targetEntries = [];
$targetAssets = [];
$targetAssetMetadata = [];
$targetAssetContainers = [];
$targetYamlFiles = [];
if ($validationRoot !== null) {
    foreach ($validationPaths ?? [] as $path) {
        if (preg_match('#^content/collections/([^/]+)\.yaml$#', $path, $matches)) {
            $targetEntries[] = 'collection:'.$matches[1];
            $targetYamlFiles[] = $path;
        } elseif (preg_match('#^content/collections/([^/]+)/.+\.md$#', $path, $matches)) {
            $targetEntries[] = $path;
        } elseif (preg_match('#^content/assets/([^/]+)\.yaml$#', $path, $matches)) {
            $targetAssetContainers[] = $matches[1];
            $targetYamlFiles[] = $path;
        } elseif (str_starts_with($path, 'public/assets/')) {
            $assetRelativePath = substr($path, strlen('public/assets/'));
            if (preg_match('#(?:^|/)\.meta/[^/]+\.yaml$#', $assetRelativePath) === 1) {
                $targetAssetMetadata[] = $path;
                $targetYamlFiles[] = $path;
            } else {
                $targetAssets[] = $assetRelativePath;
            }
        } elseif (str_starts_with($path, 'content/') && str_ends_with($path, '.yaml')) {
            $targetYamlFiles[] = $path;
        }
    }
}
foreach (Entry::all() as $entry) {
    if (! in_array($entry->collectionHandle(), ['cv_profiles', 'experience', 'education', 'projects', 'posts', 'publications'])) {
        continue;
    }
    if ($validationRoot !== null) {
        $entryPath = str_replace('\\', '/', (string) ($entry->initialPath() ?? $entry->path()));
        $relativePath = str_starts_with($entryPath, $validationRoot.'/')
            ? substr($entryPath, strlen($validationRoot) + 1)
            : $entryPath;
        if (! in_array($relativePath, $targetEntries, true)
            && ! in_array('collection:'.$entry->collectionHandle(), $targetEntries, true)) {
            continue;
        }
    }
    $report['entries']++;
    $values = $entry->values()->all();
    if ($entry->collection()->dated()) {
        $values['date'] = $entry->date()->format('Y-m-d');
    }
    $fields = $entry->blueprint()->fields()->setParent($entry)->addValues($values)->preProcess();
    try {
        $fields->validate();
    } catch (ValidationException $e) {
        $report['entry_errors'][$entry->uri()] = $e->errors();
    }
}
foreach (Asset::all() as $asset) {
    if ($validationRoot !== null) {
        $container = $asset->container()->handle();
        $metadataPath = 'public/assets/'.$asset->metaPath();
        if (! in_array($container, $targetAssetContainers, true)
            && ! in_array($asset->path(), $targetAssets, true)
            && ! in_array($metadataPath, $targetAssetMetadata, true)) {
            continue;
        }
    }
    $report['assets']++;
    $fields = $asset->blueprint()->fields()->setParent($asset)->addValues($asset->data()->all())->preProcess();
    try {
        $fields->validate();
    } catch (ValidationException $e) {
        $report['asset_errors'][$asset->path()] = $e->errors();
    }
}
if ($validationRoot !== null) {
    foreach (array_unique($targetYamlFiles) as $relativePath) {
        $path = $validationRoot.'/'.$relativePath;
        if (! is_file($path)) {
            continue;
        }
        try {
            YAML::file($path)->parse(file_get_contents($path));
        } catch (Throwable) {
            $report['candidate_file_errors'][$relativePath] = ['Invalid YAML'];
        }
    }
}
$failed = (bool) ($report['entry_errors'] || $report['asset_errors'] || $report['candidate_file_errors']);
if ($validationRoot === null) {
    $path = storage_path('app/content-validation.json');
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    printf("Entries: %d checked, %d failed. Assets: %d checked, %d failed.\nReport: %s\n", $report['entries'], count($report['entry_errors']), $report['assets'], count($report['asset_errors']), $path);
} else {
    printf("Candidate validation: %d entries and %d assets checked; %d error group(s).\n", $report['entries'], $report['assets'], count($report['entry_errors']) + count($report['asset_errors']) + count($report['candidate_file_errors']));
}
exit($failed ? 1 : 0);
