<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

require dirname(__DIR__).'/vendor/autoload.php';

const PUBLICATION_DIRECTORY = __DIR__.'/../content/collections/publications';
const PREVIEW_DIRECTORY = __DIR__.'/../public/documents';

/** @return array{slug: string, url: string, published: bool}|null */
function previewForPublication(string $path): ?array
{
    $contents = file_get_contents($path);
    if ($contents === false || preg_match('/\A---\R(.*?)\R---/s', $contents, $matches) !== 1) {
        fwrite(STDERR, "Could not read front matter: {$path}\n");

        return null;
    }

    try {
        $frontMatter = Yaml::parse($matches[1]);
    } catch (Throwable $exception) {
        fwrite(STDERR, "Could not parse front matter: {$path} ({$exception->getMessage()})\n");

        return null;
    }

    if (! is_array($frontMatter) || ! is_string($frontMatter['document_url'] ?? null)) {
        return null;
    }

    $url = $frontMatter['document_url'];
    if (filter_var($url, FILTER_VALIDATE_URL) === false || preg_match('/\.pdf(?:[?#]|$)/i', $url) !== 1) {
        return null;
    }

    $filename = pathinfo($path, PATHINFO_FILENAME);
    $defaultSlug = preg_replace('/^\d{4}-\d{2}-\d{2}\./', '', $filename);
    $slug = is_string($frontMatter['slug'] ?? null) ? $frontMatter['slug'] : $defaultSlug;
    if (! is_string($slug) || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) !== 1) {
        fwrite(STDERR, "Invalid publication slug for preview: {$path}\n");

        return null;
    }

    return [
        'slug' => $slug,
        'url' => $url,
        'published' => ($frontMatter['published'] ?? true) !== false,
    ];
}

function downloadPdf(string $url, string $destination): void
{
    $context = stream_context_create([
        'http' => [
            'follow_location' => 1,
            'max_redirects' => 5,
            'timeout' => 30,
            'user_agent' => 'jack-ruder-publication-preview-sync/1.0',
        ],
    ]);
    $source = @fopen($url, 'rb', false, $context);
    if ($source === false) {
        throw new RuntimeException('request failed');
    }

    $temporary = tempnam(PREVIEW_DIRECTORY, '.preview-');
    if ($temporary === false) {
        fclose($source);
        throw new RuntimeException('could not create temporary file');
    }

    $target = null;
    try {
        $target = fopen($temporary, 'wb');
        if ($target === false) {
            throw new RuntimeException('could not open temporary file');
        }
        if (stream_copy_to_stream($source, $target) === false) {
            throw new RuntimeException('could not write downloaded file');
        }
        fclose($target);
        $target = null;
        fclose($source);
        $source = null;

        $header = file_get_contents($temporary, false, null, 0, 5);
        if ($header !== '%PDF-') {
            throw new RuntimeException('response is not a PDF');
        }

        if (! rename($temporary, $destination)) {
            throw new RuntimeException('could not replace generated preview');
        }
    } catch (Throwable $exception) {
        if (is_resource($target)) {
            fclose($target);
        }
        if (is_resource($source)) {
            fclose($source);
        }
        if (is_file($temporary)) {
            unlink($temporary);
        }
        throw $exception;
    }
}

if (! is_dir(PREVIEW_DIRECTORY) && ! mkdir(PREVIEW_DIRECTORY, 0755, true) && ! is_dir(PREVIEW_DIRECTORY)) {
    fwrite(STDERR, 'Could not create preview directory: '.PREVIEW_DIRECTORY."\n");
    exit(1);
}

$failures = 0;
foreach (glob(PUBLICATION_DIRECTORY.'/*.md') ?: [] as $path) {
    $preview = previewForPublication($path);
    if ($preview === null) {
        continue;
    }

    $destination = PREVIEW_DIRECTORY.'/'.$preview['slug'].'.pdf';
    try {
        downloadPdf($preview['url'], $destination);
        fwrite(STDOUT, "Mirrored {$preview['slug']} to public/documents/{$preview['slug']}.pdf\n");
    } catch (Throwable $exception) {
        $message = "Could not mirror {$preview['slug']} from {$preview['url']}: {$exception->getMessage()}";
        if ($preview['published']) {
            fwrite(STDERR, "ERROR: {$message}\n");
            $failures += 1;
        } else {
            fwrite(STDERR, "WARNING: {$message}\n");
        }
    }
}

exit($failures === 0 ? 0 : 1);
