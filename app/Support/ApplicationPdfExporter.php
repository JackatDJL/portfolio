<?php

namespace App\Support;

use App\Signatures\ApplicationCompositeSource;
use Illuminate\Validation\ValidationException;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Asset;
use Symfony\Component\Process\Process;
use Throwable;

final class ApplicationPdfExporter implements ApplicationCompositeSource
{
    public function __construct(
        private CvViewModel $viewModel,
        private CvPdfRenderer $renderer,
        private ApplicationLetter $letter,
        private ApplicationPdfPostProcessor $postProcessor,
    ) {}

    /** Caller must authorize the application before invoking this private-only renderer. */
    public function render(Entry $entry, bool $cached = true): string
    {
        $deadline = microtime(true) + (int) config('applications.timeout');
        $stage = 'Datenprüfung';
        $work = null;
        $compiled = [];
        $lock = null;
        try {
            $work = $this->directory(config('applications.work_path')).'/'.bin2hex(random_bytes(16));
            $this->directory($work);
            $cv = $this->viewModel->forApplication($entry);
            if ($cv['photo']) {
                $copy = $work.'/photo.'.pathinfo($cv['photo'], PATHINFO_EXTENSION);
                if (! copy($cv['photo'], $copy)) {
                    throw new ApplicationPdfException;
                }
                chmod($copy, 0600);
                $cv['photo_digest'] = hash_file('sha256', $copy);
                $cv['photo'] = $copy;
            }
            $documents = $this->snapshotDocuments($entry, $work);
            $letter = $this->letter->make($entry, $cv, $documents);
            $source = view('latex.application-letter', compact('letter'))->render();
            $key = $this->key($entry, $cv, $source, $documents);
            $directory = $this->directory(config('applications.cache_path').'/'.hash('sha256', $entry->id()));
            $canonical = $directory.'/'.$key.'.pdf';
            if ($cached) {
                $lock = fopen($directory.'/export.lock', 'c');
                if (! $lock) {
                    throw new ApplicationPdfException;
                }
                chmod($directory.'/export.lock', 0600);
                while (! flock($lock, LOCK_EX | LOCK_NB)) {
                    if (microtime(true) >= $deadline) {
                        throw new ApplicationPdfException;
                    }
                    usleep(100000);
                }
                if ($this->valid($canonical) && is_file($canonical.'.sha256') && hash_equals(trim(file_get_contents($canonical.'.sha256')), hash_file('sha256', $canonical))) {
                    touch($canonical);

                    return $canonical;
                }
            }
            $stage = 'Anschreiben';
            $compiled[] = $this->renderer->compile($source, passes: 2, deadline: $deadline, validationMarkers: [
                'APPLICATION_RECIPIENT_ADDRESS_OVERFLOW' => [
                    'field' => 'recipient.address',
                    'message' => 'Die Empfängeranschrift ist zu hoch für den vorgesehenen Bereich. Bitte kürzen oder die Anschrift auf weniger Zeilen verteilen.',
                ],
            ]);
            $stage = 'Lebenslauf';
            $compiled[] = $this->renderer->renderViewModel($cv, deadline: $deadline);
            $parts = [
                ['path' => $compiled[0], 'label' => 'Anschreiben'],
                ['path' => $compiled[1], 'label' => 'Lebenslauf'],
                ...array_map(fn ($document) => ['path' => $document['path'], 'label' => $document['label']], $documents),
            ];
            $manifest = ['parts' => $parts, 'max_pages' => (int) config('applications.max_pages')];
            $manifestPath = $work.'/manifest.json';
            file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
            chmod($manifestPath, 0600);
            $stage = 'Zusammenstellung';
            $output = $work.'/composed.pdf';
            $process = new Process([config('applications.python'), base_path('scripts/compose-application-pdf.py'), $manifestPath, $output]);
            $process->setTimeout(max(0.1, $deadline - microtime(true)));
            $process->run();
            if (! $process->isSuccessful() || ! $this->valid($output)) {
                throw new ApplicationPdfException;
            }
            $stage = 'Nachbearbeitung';
            $this->postProcessor->process($output, ['application_id' => $entry->id(), 'fingerprint' => $key]);
            if (! $this->valid($output)) {
                throw new ApplicationPdfException;
            }
            $validation = new Process([config('applications.python'), base_path('scripts/compose-application-pdf.py'), '--validate', $output]);
            $validation->setTimeout(max(0.1, $deadline - microtime(true)))->run();
            if (! $validation->isSuccessful()) {
                throw new ApplicationPdfException;
            }
            chmod($output, 0600);
            // Preview is ephemeral; canonical exports are atomically published only after success.
            $destination = $cached ? $canonical : $directory.'/preview-'.bin2hex(random_bytes(16)).'.pdf';
            if (! rename($output, $destination)) {
                throw new ApplicationPdfException;
            }
            if ($cached) {
                file_put_contents($work.'/digest', hash_file('sha256', $destination));
                chmod($work.'/digest', 0600);
                if (! rename($work.'/digest', $destination.'.sha256')) {
                    throw new ApplicationPdfException;
                }
            }
            $this->pruneDirectory($directory, $destination);

            return $destination;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new ApplicationPdfException($stage);
        } finally {
            foreach ($compiled as $file) {
                @unlink($file);
            }
            if ($work) {
                $this->removeWork($work);
            }
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private function snapshotDocuments(Entry $entry, string $work): array
    {
        $documents = [];
        $total = 0;
        $root = realpath(config('filesystems.disks.application_documents.root'));
        foreach (ApplicationData::documents($entry) as $index => $selection) {
            $reference = $selection['file'] ?? null;
            if (is_array($reference)) {
                if (count($reference) !== 1) {
                    throw new ApplicationPdfException;
                }
                $reference = reset($reference);
            }
            if (! is_string($reference) || $reference === '') {
                throw new ApplicationPdfException;
            }
            if (! str_contains($reference, '::')) {
                $reference = 'application_documents::'.$reference;
            }
            $asset = Asset::find($reference);
            if (! $asset || $asset->containerHandle() !== 'application_documents') {
                throw new ApplicationPdfException;
            }
            $path = realpath($asset->resolvedPath());
            if (! $root || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
                throw new ApplicationPdfException;
            }
            $size = filesize($path);
            if ($size > config('applications.max_document_bytes') || $total + $size > config('applications.max_total_bytes')) {
                throw new ApplicationPdfException;
            }
            $copy = $work.'/document-'.count($documents).'.pdf';
            $remaining = min((int) config('applications.max_document_bytes'), (int) config('applications.max_total_bytes') - $total);
            $copied = $this->copySnapshot($path, $copy, $remaining);
            $total += $copied;
            chmod($copy, 0600);
            if (! $this->valid($copy)) {
                throw new ApplicationPdfException;
            }
            $documents[] = ['path' => $copy, 'label' => (string) ($selection['label'] ?? 'Dokument'), 'sha256' => hash_file('sha256', $copy)];
        }

        return $documents;
    }

    /** Copy a bounded snapshot so concurrent asset growth cannot bypass export limits. */
    private function copySnapshot(string $source, string $destination, int $limit): int
    {
        $input = @fopen($source, 'rb');
        $output = @fopen($destination, 'xb');
        if (! $input || ! $output) {
            if ($input) {
                fclose($input);
            }
            if ($output) {
                fclose($output);
            }
            throw new ApplicationPdfException;
        }
        chmod($destination, 0600);
        $copied = 0;
        try {
            while (! feof($input)) {
                $chunk = fread($input, min(1024 * 1024, $limit - $copied + 1));
                if ($chunk === false || ($chunk === '' && ! feof($input))) {
                    throw new ApplicationPdfException;
                }
                $length = strlen($chunk);
                if ($length === 0) {
                    break;
                }
                if ($copied + $length > $limit || fwrite($output, $chunk) !== $length) {
                    throw new ApplicationPdfException;
                }
                $copied += $length;
            }
        } catch (Throwable $exception) {
            @unlink($destination);
            throw $exception;
        } finally {
            fclose($input);
            fclose($output);
        }

        return $copied;
    }

    private function key(Entry $entry, array $cv, string $source, array $documents): string
    {
        $process = new Process([config('applications.python'), base_path('scripts/compose-application-pdf.py'), '--version']);
        $process->setTimeout(5);
        $process->mustRun();
        $files = [__FILE__, base_path('app/Support/ApplicationLetter.php'), base_path('app/Support/ApplicationData.php'), base_path('app/Support/CvPrivateData.php'), base_path('scripts/compose-application-pdf.py'), base_path('requirements-pdf.txt'), (new \ReflectionClass($this->postProcessor))->getFileName()];
        $files = [...$files, ...glob(base_path('resources/application-fonts/*.ttf'))];
        $fingerprintCv = $cv;
        $fingerprintCv['photo'] = null; // The immutable photo snapshot is covered by photo_digest.
        $fingerprint = [
            'schema' => 1, 'id' => $entry->id(), 'status' => $entry->get('application_status'),
            'application' => array_map(fn ($group) => ApplicationData::group($entry, $group), ApplicationData::GROUPS),
            'cv' => $this->renderer->fingerprint($fingerprintCv), 'letter' => $source,
            'documents' => array_map(fn ($document) => [$document['label'], $document['sha256']], $documents),
            'code' => array_map(fn ($file) => hash_file('sha256', $file), $files),
            'composer' => trim($process->getOutput()), 'limits' => [config('applications.max_pages'), config('applications.max_total_bytes')],
            'post_processor' => [get_class($this->postProcessor), $this->postProcessor->version()],
        ];

        return hash_hmac('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR), config('app.key'));
    }

    private function valid(string $path): bool
    {
        return is_file($path) && file_get_contents($path, false, null, 0, 5) === '%PDF-';
    }

    private function directory(string $path): string
    {
        if (! is_dir($path) && ! @mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new ApplicationPdfException;
        }
        chmod($path, 0700);

        return $path;
    }

    private function removeWork(string $work): void
    {
        foreach (glob($work.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($work);
    }

    private function pruneDirectory(string $directory, string $current): void
    {
        $before = time() - ((int) config('applications.retention_days') * 86400);
        foreach (glob($directory.'/*.pdf') ?: [] as $file) {
            if ($file !== $current && filemtime($file) < $before) {
                @unlink($file);
                @unlink($file.'.sha256');
            }
        }
    }
}
