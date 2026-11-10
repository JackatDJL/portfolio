<?php

namespace App\Signatures;

use App\Models\ApplicationSignatureVersion as Version;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry;
use Symfony\Component\Process\Process;

/** Append-only artifacts; signing is never part of rendering or cache generation. */
final class ApplicationSignatureWorkflow
{
    public function __construct(private ApplicationCompositeSource $source, private SignatureValidator $validator, private SignatureProvider $provider) {}

    public function prepare(Entry $entry, string $actor): Version
    {
        $source = $this->source->render($entry);
        $check = new Process([config('applications.python'), base_path('scripts/application-signatures.py'), 'check-unsigned', $source]);
        $check->setTimeout((int) config('signatures.validation_timeout'))->run();
        if (! $check->isSuccessful()) {
            throw new SignatureWorkflowException('PDF enthält vorhandene Signaturen oder nicht unterstützte Formularfelder. Vor externer Signierung prüfen.');
        }
        $version = new Version(['id' => (string) Str::uuid(), 'application_id' => $entry->id(), 'unsigned_sha256' => hash_file('sha256', $source), 'state' => 'prepared', 'created_by' => $actor]);
        $directory = $this->directory($version);
        if (! mkdir($directory, 0700, true)) {
            throw new SignatureWorkflowException('Privater Speicher nicht verfügbar.');
        }
        $this->copy($source, $directory.'/unsigned.pdf');
        if (! hash_equals($version->unsigned_sha256, hash_file('sha256', $directory.'/unsigned.pdf'))) {
            throw new SignatureWorkflowException('Dokument während der Vorbereitung verändert.');
        }
        $version->save();

        return $version;
    }

    public function version(string $application, string $id): Version
    {
        return Version::where('application_id', $application)->findOrFail($id);
    }

    public function path(Version $version, bool $signed = false): string
    {
        $path = $this->directory($version).($signed ? '/signed.pdf' : '/unsigned.pdf');
        $expected = $signed ? $version->signed_sha256 : $version->unsigned_sha256;
        if (! $expected || ! is_file($path) || ! hash_equals($expected, hash_file('sha256', $path))) {
            throw new SignatureWorkflowException('Archiv fehlt oder wurde verändert.');
        }

        return $path;
    }

    public function import(Version $version, string $uploaded, string $digest, bool $sandbox = false): Version
    {
        return Cache::lock('application-signature:'.$version->id, 120)->block(5, function () use ($version, $uploaded, $digest, $sandbox) {
            $version->refresh();
            if ($version->state !== 'prepared' || ! hash_equals($version->unsigned_sha256, $digest)) {
                throw new SignatureWorkflowException('Version oder Freigabe stimmt nicht überein.');
            }
            if (! is_file($uploaded) || filesize($uploaded) > config('signatures.max_bytes')) {
                throw new SignatureWorkflowException('Datei zu groß.');
            }
            $target = $this->directory($version).'/candidate.pdf';
            $this->copy($uploaded, $target);
            try {
                $report = $this->validator->validate($this->path($version), $target);
                $signed = $this->directory($version).'/signed.pdf';
                if (file_exists($signed) || ! rename($target, $signed)) {
                    throw new SignatureWorkflowException('Archiv nicht verfügbar.');
                }
                $version->fill(['state' => 'validated', 'signed_sha256' => hash_file('sha256', $signed), 'verification' => $report, 'sandbox' => $sandbox, 'provider' => $sandbox ? $this->provider->id() : 'external'])->save();
            } finally {
                if (is_file($target)) {
                    unlink($target);
                }
            }

            return $version;
        });
    }

    public function sandbox(Version $version, string $digest): Version
    {
        if (! $this->provider->capabilities()->sandbox || ! $this->provider->capabilities()->available || $version->state !== 'prepared') {
            throw new SignatureWorkflowException('Sandbox nicht verfügbar.');
        }
        $target = $this->directory($version).'/sandbox-candidate-'.Str::uuid().'.pdf';
        try {
            $this->provider->sign($this->path($version), $digest, $target);

            return $this->import($version, $target, $digest, true);
        } finally {
            if (is_file($target)) {
                unlink($target);
            }
        }
    }

    public function select(string $application, ?Version $version, string $actor): void
    {
        if ($version && ($version->application_id !== $application || $version->state !== 'validated')) {
            throw new SignatureWorkflowException('Keine validierte Version.');
        }
        if ($version) {
            $this->path($version, true);
        }
        DB::table('application_signature_settings')->updateOrInsert(['application_id' => $application], ['mode' => $version ? ($version->sandbox ? 'remote' : 'external') : 'unsigned', 'active_version_id' => $version?->id, 'updated_by' => $actor, 'updated_at' => now(), 'created_at' => now()]);
    }

    public function delivery(Entry $entry, string $unsigned, bool $recipient): string
    {
        $setting = DB::table('application_signature_settings')->where('application_id', $entry->id())->first();
        if (! $setting || $setting->mode === 'unsigned') {
            return $unsigned;
        }
        $version = $this->version($entry->id(), $setting->active_version_id);
        if ($version->state !== 'validated' || ($recipient && $version->sandbox) || ! hash_equals($version->unsigned_sha256, hash_file('sha256', $unsigned))) {
            throw new SignatureWorkflowException('Signierte Version veraltet oder nicht zur Weitergabe freigegeben. Bitte neu vorbereiten oder ausdrücklich unsigniert auswählen.');
        }

        return $this->path($version, true);
    }

    public function status(string $application): array
    {
        $versions = Version::where('application_id', $application)->latest()->limit(20)->get()->map(fn ($v) => ['id' => $v->id, 'sha256' => $v->unsigned_sha256, 'state' => $v->state, 'sandbox' => $v->sandbox, 'verification' => $v->verification]);
        $setting = DB::table('application_signature_settings')->where('application_id', $application)->first();

        return ['mode' => $setting?->mode ?? 'unsigned', 'active_version_id' => $setting?->active_version_id, 'versions' => $versions, 'provider' => $this->provider->id(), 'capabilities' => $this->provider->capabilities()];
    }

    private function directory(Version $version): string
    {
        return rtrim(config('signatures.root'), '/').'/'.hash('sha256', $version->application_id).'/'.$version->id;
    }

    private function copy(string $source, string $target): void
    {
        $output = fopen($target, 'xb');
        if (! $output) {
            throw new SignatureWorkflowException('Privater Speicher nicht verfügbar.');
        }
        chmod($target, 0600);
        $input = fopen($source, 'rb');
        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw new SignatureWorkflowException('Kopieren fehlgeschlagen.');
            }
        } finally {
            fclose($input);
            fclose($output);
        }
    }
}
