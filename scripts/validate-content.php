<?php

use App\Support\ContentSyncConflictDocument;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\YAML;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Group;
use Symfony\Component\Finder\SplFileInfo;

$validationResultPath = getenv('CONTENT_SYNC_VALIDATION_RESULT_PATH') ?: null;
$validationCompleted = false;
$writeValidationResult = static function (bool $completed, bool $passed) use ($validationResultPath): bool {
    if (! is_string($validationResultPath) || $validationResultPath === '') {
        return true;
    }

    $json = json_encode(['completed' => $completed, 'passed' => $passed], JSON_THROW_ON_ERROR);

    return @file_put_contents($validationResultPath, $json."\n", LOCK_EX) === strlen($json) + 1;
};
register_shutdown_function(static function () use (&$validationCompleted, $writeValidationResult): void {
    if (! $validationCompleted) {
        $validationCompleted = $writeValidationResult(false, false);
    }
});
$redactedExceptionHandler = static function (Throwable $_exception) use (&$validationCompleted, $writeValidationResult): void {
    fwrite(STDERR, "Content validation did not complete.\n");
    $validationCompleted = $writeValidationResult(false, false);
    exit(1);
};
set_exception_handler($redactedExceptionHandler);

// Validate the same preprocessed field values that the Statamic publish form uses.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler($redactedExceptionHandler);

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
    $disks = config('filesystems.disks', []);
    $validationDiskRoots = json_decode((string) getenv('CONTENT_SYNC_VALIDATION_DISK_ROOTS'), true);
    $validationDiskRoots = is_array($validationDiskRoots) ? $validationDiskRoots : [];
    foreach ($disks as $handle => &$disk) {
        if ($handle !== 'assets' && ($disk['driver'] ?? null) === 'local') {
            $root = $validationDiskRoots[$handle] ?? null;
            $disk['root'] = is_string($root) && $root !== ''
                ? $root
                : $validationRoot.'/.external-assets-disabled/'.$handle;
        }
    }
    unset($disk);
    config()->set('filesystems.disks', $disks);

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
    foreach (array_keys($disks) as $handle) {
        Storage::forgetDisk($handle);
    }
}

$report = ['entries' => 0, 'entry_errors' => [], 'global_errors' => [], 'assets' => 0, 'asset_errors' => [], 'candidate_file_errors' => []];
$targetEntries = [];
$targetAssets = [];
$targetAssetMetadata = [];
$targetAssetContainers = [];
$targetGlobals = [];
$targetYamlFiles = [];
$validatedEntryPaths = [];
$isEntryFile = static function (string $handle, string $relativePath) use ($validationRoot): bool {
    if (! is_string($validationRoot) || ! str_starts_with($relativePath, 'content/collections/'.$handle.'/')) {
        return false;
    }

    $withinCollection = substr($relativePath, strlen('content/collections/'.$handle.'/'));
    $file = new SplFileInfo(
        $validationRoot.'/'.$relativePath,
        dirname($withinCollection) === '.' ? '' : dirname($withinCollection),
        $withinCollection,
    );

    return app('stache')->store('entries')->store($handle)->getItemFilter($file) === true;
};
$containsExternalAssetDisk = null;
$inspectResolvedField = null;
$inspectAssetFieldConfig = null;
$containsExternalAssetDisk = function (array $definitions) use (&$containsExternalAssetDisk, &$inspectResolvedField): bool {
    try {
        $fields = new Fields($definitions);
    } catch (Throwable) {
        return true;
    }

    foreach ($fields->all() as $field) {
        if ($inspectResolvedField($field)) {
            return true;
        }
    }

    return false;
};
$inspectResolvedField = function (Field $field) use (&$containsExternalAssetDisk, &$inspectResolvedField, &$inspectAssetFieldConfig): bool {
    $configuration = $field->config();
    if (($configuration['type'] ?? null) === 'assets') {
        $containerHandle = $configuration['container'] ?? null;
        $container = is_string($containerHandle) ? AssetContainer::find($containerHandle) : null;
        if (! $container || $container->diskHandle() !== 'assets') {
            return true;
        }
    }

    try {
        $fieldtype = $field->fieldtype();
        if ($fieldtype instanceof Group) {
            foreach ($fieldtype->fields()->all() as $nestedField) {
                if ($inspectResolvedField($nestedField)) {
                    return true;
                }
            }
        }
    } catch (Throwable) {
        return true;
    }

    return $inspectAssetFieldConfig($configuration);
};
$inspectAssetFieldConfig = function (array $configuration) use (&$containsExternalAssetDisk, &$inspectAssetFieldConfig): bool {
    if (($configuration['type'] ?? null) === 'assets') {
        $containerHandle = $configuration['container'] ?? null;
        $container = is_string($containerHandle) ? AssetContainer::find($containerHandle) : null;
        if (! $container || $container->diskHandle() !== 'assets') {
            return true;
        }
    }

    foreach ($configuration as $key => $value) {
        if (! is_array($value)) {
            continue;
        }
        if ($key === 'fields') {
            if ($containsExternalAssetDisk($value)) {
                return true;
            }
        } elseif ($inspectAssetFieldConfig($value)) {
            return true;
        }
    }

    return false;
};
if ($validationRoot !== null) {
    foreach (array_unique($validationPaths ?? []) as $relativePath) {
        $path = $validationRoot.'/'.$relativePath;
        if (! is_file($path) || is_link($path)) {
            continue;
        }
        $contents = @file_get_contents($path);
        if (! is_string($contents)) {
            $report['candidate_file_errors'][$relativePath] = ['The staged candidate file could not be checked for unresolved conflict markers'];

            continue;
        }
        if (! str_contains($contents, "\0")
            && mb_check_encoding($contents, 'UTF-8')
            && ContentSyncConflictDocument::containsConflictMarker($contents)) {
            $report['candidate_file_errors'][$relativePath] = ['The staged candidate contains unresolved Git conflict markers'];
        }
    }

    foreach ($validationPaths ?? [] as $path) {
        if (preg_match('#^content/collections/([^/]+)\.yaml$#', $path, $matches)) {
            $targetEntries[] = 'collection:'.$matches[1];
            $targetYamlFiles[] = $path;
        } elseif (preg_match('#^content/collections/([^/]+)/.+$#', $path, $matches)
            && is_file($validationRoot.'/'.$path)
            && $isEntryFile($matches[1], $path)) {
            $targetEntries[] = $path;
        } elseif (preg_match('#^content/assets/([^/]+)\.yaml$#', $path, $matches)) {
            $targetAssetContainers[] = $matches[1];
            $targetYamlFiles[] = $path;
        } elseif (preg_match('#^content/globals/([^/]+)/([^/]+)\.yaml$#', $path, $matches)) {
            $targetGlobals[] = ['path' => $path, 'site' => $matches[1], 'handle' => $matches[2]];
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
    if ($validationRoot === null
        && ! in_array($entry->collectionHandle(), ['cv_profiles', 'experience', 'education', 'projects', 'posts', 'publications'])) {
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
        $validatedEntryPaths[] = $relativePath;
        try {
            $fieldDefinitions = $entry->blueprint()->fields()->all()->map(static fn ($field): array => [
                'handle' => $field->handle(),
                'field' => $field->config(),
            ])->all();
            $hasExternalAssetDisk = $containsExternalAssetDisk($fieldDefinitions);
        } catch (Throwable) {
            $report['candidate_file_errors'][$relativePath] = ['Target blueprint could not be checked against the candidate asset snapshot'];

            continue;
        }
        if ($hasExternalAssetDisk) {
            $report['candidate_file_errors'][$relativePath] = ['Target entry references an asset disk outside the candidate snapshot'];

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
if ($validationRoot !== null) {
    foreach ($targetGlobals as $targetGlobal) {
        $path = $validationRoot.'/'.$targetGlobal['path'];
        if (! is_file($path)) {
            continue;
        }
        $globalSet = GlobalSet::find($targetGlobal['handle']);
        $variables = $globalSet?->in($targetGlobal['site']);
        if (! $variables) {
            $report['candidate_file_errors'][$targetGlobal['path']] = ['Target global variables could not be resolved for blueprint validation'];

            continue;
        }
        try {
            $blueprint = $variables->blueprint();
            $fieldDefinitions = $blueprint->fields()->all()->map(static fn ($field): array => [
                'handle' => $field->handle(),
                'field' => $field->config(),
            ])->all();
            if ($containsExternalAssetDisk($fieldDefinitions)) {
                $report['candidate_file_errors'][$targetGlobal['path']] = ['Target global references an asset disk outside the candidate snapshot'];

                continue;
            }
            $fields = $blueprint->fields()->setParent($variables)->addValues($variables->data()->all())->preProcess();
            $fields->validate();
        } catch (ValidationException $e) {
            $report['global_errors'][$targetGlobal['path']] = $e->errors();
        } catch (Throwable) {
            $report['candidate_file_errors'][$targetGlobal['path']] = ['Target global could not be validated against its candidate blueprint'];
        }
    }
}

if ($validationRoot !== null) {
    $targetEntryFiles = [];
    $targetedEntryPaths = [];
    $wholeCollectionTargets = [];
    foreach ($targetEntries as $target) {
        if (str_starts_with($target, 'collection:')) {
            $handle = substr($target, strlen('collection:'));
            $wholeCollectionTargets[$handle] = true;
            $directory = $validationRoot.'/content/collections/'.$handle;
            if (! is_dir($directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $path = 'content/collections/'.$handle.'/'.substr($file->getPathname(), strlen($directory) + 1);
                    if ($isEntryFile($handle, $path)) {
                        $targetEntryFiles[] = $path;
                    }
                }
            }
        } elseif (preg_match('#^content/collections/([^/]+)/.+$#', $target, $matches)
            && is_file($validationRoot.'/'.$target)
            && $isEntryFile($matches[1], $target)) {
            $targetEntryFiles[] = $target;
            $targetedEntryPaths[] = $target;
        }
    }
    foreach (array_unique($targetEntryFiles) as $path) {
        if (! in_array($path, $validatedEntryPaths, true)) {
            $report['candidate_file_errors'][$path] = ['Entry was not loaded for blueprint validation'];
        }
    }

    $targetedEntryPaths = array_fill_keys($targetedEntryPaths, true);
    $idsToPaths = [];
    $collectionsRoot = $validationRoot.'/content/collections';
    if (is_dir($collectionsRoot)) {
        foreach (new DirectoryIterator($collectionsRoot) as $collectionDirectory) {
            if ($collectionDirectory->isDot() || ! $collectionDirectory->isDir() || $collectionDirectory->isLink()) {
                continue;
            }
            $handle = $collectionDirectory->getFilename();
            $directory = $collectionDirectory->getPathname();
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $path = 'content/collections/'.$handle.'/'.substr($file->getPathname(), strlen($directory) + 1);
                if (! $isEntryFile($handle, $path)) {
                    continue;
                }
                try {
                    $values = YAML::file($file->getPathname())->parse(file_get_contents($file->getPathname()));
                } catch (Throwable) {
                    $values = null;
                }
                if (! is_array($values)) {
                    if (isset($targetedEntryPaths[$path]) || isset($wholeCollectionTargets[$handle])) {
                        $report['candidate_file_errors'][$path] = ['An entry identifier in the targeted collection could not be checked'];
                    }

                    continue;
                }
                $id = $values['id'] ?? null;
                if ((is_string($id) || is_int($id)) && (string) $id !== '') {
                    $idsToPaths[(string) $id][] = $path;
                }
            }
        }
    }

    foreach ($idsToPaths as $paths) {
        if (count($paths) < 2) {
            continue;
        }
        $affectsTarget = array_intersect_key(array_fill_keys($paths, true), $targetedEntryPaths) !== [];
        if (! $affectsTarget) {
            foreach ($paths as $path) {
                if (preg_match('#^content/collections/([^/]+)/#', $path, $matches)
                    && isset($wholeCollectionTargets[$matches[1]])) {
                    $affectsTarget = true;
                    break;
                }
            }
        }
        if (! $affectsTarget) {
            continue;
        }
        foreach ($paths as $path) {
            $report['candidate_file_errors'][$path] = ['The candidate contains duplicate entry identifiers for targeted content'];
        }
    }
}

if ($validationRoot !== null) {
    $containers = AssetContainer::all();
    foreach (array_unique($targetAssetContainers) as $handle) {
        $containerPath = $validationRoot.'/content/assets/'.$handle.'.yaml';
        if (! file_exists($containerPath) && ! is_link($containerPath)) {
            continue;
        }
        $container = AssetContainer::find($handle);
        if (is_link($containerPath) || ! is_file($containerPath) || ! $container || $container->diskHandle() !== 'assets') {
            $report['candidate_file_errors']['content/assets/'.$handle.'.yaml'] = ['Asset container disk is outside the candidate snapshot'];
        }
    }

    $assets = $containers
        ->filter(static fn ($container): bool => $container->diskHandle() === 'assets')
        ->flatMap(static fn ($container) => $container->assets());
} else {
    $assets = Asset::all();
}

foreach ($assets as $asset) {
    if ($validationRoot !== null) {
        $container = $asset->container()->handle();
        $metadataPath = 'public/assets/'.$asset->metaPath();
        if (! in_array($container, $targetAssetContainers, true)
            && ! in_array($asset->path(), $targetAssets, true)
            && ! in_array($metadataPath, $targetAssetMetadata, true)) {
            continue;
        }
    }
    $assetPath = 'public/assets/'.$asset->path();
    $blueprint = null;
    if ($validationRoot !== null) {
        try {
            $blueprint = $asset->blueprint();
            $fieldDefinitions = $blueprint->fields()->all()->map(static fn ($field): array => [
                'handle' => $field->handle(),
                'field' => $field->config(),
            ])->all();
            if ($containsExternalAssetDisk($fieldDefinitions)) {
                $report['candidate_file_errors'][$assetPath] = ['Target asset references a disk outside the candidate snapshot'];

                continue;
            }
        } catch (Throwable) {
            $report['candidate_file_errors'][$assetPath] = ['Target asset blueprint could not be checked against the candidate asset snapshot'];

            continue;
        }
    } else {
        $blueprint = $asset->blueprint();
    }
    $report['assets']++;
    $fields = $blueprint->fields()->setParent($asset)->addValues($asset->data()->all())->preProcess();
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
$failed = (bool) ($report['entry_errors'] || $report['global_errors'] || $report['asset_errors'] || $report['candidate_file_errors']);
if ($validationRoot === null) {
    $path = storage_path('app/content-validation.json');
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    printf("Entries: %d checked, %d failed. Assets: %d checked, %d failed.\nReport: %s\n", $report['entries'], count($report['entry_errors']), $report['assets'], count($report['asset_errors']), $path);
} else {
    printf("Candidate validation: %d entries and %d assets checked; %d error group(s).\n", $report['entries'], $report['assets'], count($report['entry_errors']) + count($report['global_errors']) + count($report['asset_errors']) + count($report['candidate_file_errors']));
}
$validationCompleted = $writeValidationResult(true, ! $failed);
if (! $validationCompleted) {
    fwrite(STDERR, "Content validation completion could not be recorded.\n");
    exit(1);
}
exit($failed ? 1 : 0);
