<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;

// Validate the same preprocessed field values that the Statamic publish form uses.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$report = ['entries' => 0, 'entry_errors' => [], 'assets' => 0, 'asset_errors' => []];
foreach (Entry::all() as $entry) {
    if (! in_array($entry->collectionHandle(), ['cv_profiles', 'experience', 'education', 'projects', 'posts', 'publications'])) {
        continue;
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
    $report['assets']++;
    $fields = $asset->blueprint()->fields()->setParent($asset)->addValues($asset->data()->all())->preProcess();
    try {
        $fields->validate();
    } catch (ValidationException $e) {
        $report['asset_errors'][$asset->path()] = $e->errors();
    }
}
$path = storage_path('app/content-validation.json');
file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
printf("Entries: %d checked, %d failed. Assets: %d checked, %d failed.\nReport: %s\n", $report['entries'], count($report['entry_errors']), $report['assets'], count($report['asset_errors']), $path);
exit($report['entry_errors'] || $report['asset_errors'] ? 1 : 0);
