<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/** Stores reusable handwritten signature source only as Laravel-encrypted private data. */
final class PrivateHandwrittenSignature
{
    private const MAX_PNG_BYTES = 2_000_000;

    private const MAX_STROKES = 200;

    private const MAX_POINTS = 10_000;

    public function exists(): bool
    {
        $path = $this->path();
        if (is_link($path)) {
            throw new RuntimeException('Der private Signaturpfad ist ungültig.');
        }
        if (! is_file($path)) {
            return false;
        }
        $this->assertPrivateDirectory(dirname($path));

        return true;
    }

    public function available(): bool
    {
        return $this->read() !== null;
    }

    public function editorData(): ?array
    {
        $source = $this->read();
        if ($source === null) {
            return null;
        }

        return [
            'image' => 'data:image/png;base64,'.base64_encode($source['png']),
            'strokes' => $source['strokes'],
        ];
    }

    public function save(string $dataUrl, array $strokes = []): void
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#D', $dataUrl, $matches)
            || strlen($matches[1]) > (int) ceil(self::MAX_PNG_BYTES * 4 / 3)) {
            throw new RuntimeException('Bitte ein gültiges PNG-Bild mit höchstens 2 MB speichern.');
        }
        $png = base64_decode($matches[1], true);
        if (! is_string($png) || strlen($png) > self::MAX_PNG_BYTES) {
            throw new RuntimeException('Das Signaturbild ist ungültig oder zu groß.');
        }
        $this->validatePng($png);
        $strokes = $this->validateStrokes($strokes);
        $encrypted = Crypt::encryptString(json_encode([
            'version' => 1,
            'png' => base64_encode($png),
            'strokes' => $strokes,
        ], JSON_THROW_ON_ERROR));
        $directory = $this->directory();
        $temporary = $directory.'/source.'.bin2hex(random_bytes(12)).'.tmp';
        if (file_put_contents($temporary, $encrypted, LOCK_EX) !== strlen($encrypted)) {
            @unlink($temporary);
            throw new RuntimeException('Die private Signatur konnte nicht gespeichert werden.');
        }
        chmod($temporary, 0600);
        if (! rename($temporary, $this->path())) {
            @unlink($temporary);
            throw new RuntimeException('Die private Signatur konnte nicht veröffentlicht werden.');
        }
        chmod($this->path(), 0600);
    }

    /** Materialize one private PNG only inside a caller-created 0700 work directory. */
    public function materialize(string $destination): ?array
    {
        $source = $this->read();
        if ($source === null) {
            return null;
        }
        $directory = dirname($destination);
        $this->assertPrivateDirectory($directory);
        $output = @fopen($destination, 'xb');
        if ($output === false) {
            throw new RuntimeException('Die private Signatur konnte nicht für den PDF-Export vorbereitet werden.');
        }
        try {
            if (fwrite($output, $source['png']) !== strlen($source['png'])) {
                @unlink($destination);
                throw new RuntimeException('Die private Signatur konnte nicht für den PDF-Export vorbereitet werden.');
            }
            chmod($destination, 0600);
        } finally {
            fclose($output);
        }

        return ['path' => $destination, 'sha256' => hash('sha256', $source['png'])];
    }

    public function delete(): void
    {
        if (is_link($this->path())) {
            throw new RuntimeException('Der private Signaturpfad ist ungültig.');
        }
        if (is_file($this->path()) && ! unlink($this->path())) {
            throw new RuntimeException('Die private Signatur konnte nicht gelöscht werden.');
        }
    }

    private function read(): ?array
    {
        if (! $this->exists()) {
            return null;
        }
        if (is_link($this->path())) {
            throw new RuntimeException('Der private Signaturpfad ist ungültig.');
        }
        $encrypted = file_get_contents($this->path());
        if (! is_string($encrypted)) {
            throw new RuntimeException('Die private Signatur ist nicht lesbar.');
        }
        $data = json_decode(Crypt::decryptString($encrypted), true, 32, JSON_THROW_ON_ERROR);
        $png = is_array($data) && ($data['version'] ?? null) === 1
            ? base64_decode((string) ($data['png'] ?? ''), true)
            : false;
        if (! is_string($png) || strlen($png) > self::MAX_PNG_BYTES) {
            throw new RuntimeException('Die gespeicherte private Signatur ist ungültig.');
        }
        $this->validatePng($png);

        return ['png' => $png, 'strokes' => $this->validateStrokes($data['strokes'] ?? [])];
    }

    private function validatePng(string $png): void
    {
        $image = @getimagesizefromstring($png);
        if (! is_array($image) || ($image['mime'] ?? null) !== 'image/png'
            || ($image[0] ?? 0) < 80 || ($image[1] ?? 0) < 24
            || $image[0] > 4096 || $image[1] > 2048) {
            throw new RuntimeException('Die Signatur muss ein PNG mit mindestens 80 × 24 und höchstens 4096 × 2048 Pixeln sein.');
        }
    }

    private function validateStrokes(array $strokes): array
    {
        if (count($strokes) > self::MAX_STROKES) {
            throw new RuntimeException('Zu viele Signaturstriche.');
        }
        $points = 0;
        foreach ($strokes as $stroke) {
            if (! is_array($stroke) || ! array_is_list($stroke) || count($stroke) < 2) {
                throw new RuntimeException('Die Signaturstriche sind ungültig.');
            }
            foreach ($stroke as $point) {
                $points++;
                if (! is_array($point) || ! is_numeric($point['x'] ?? null) || ! is_numeric($point['y'] ?? null)
                    || $point['x'] < 0 || $point['x'] > 1 || $point['y'] < 0 || $point['y'] > 1) {
                    throw new RuntimeException('Die Signaturpunkte sind ungültig.');
                }
                if ($points > self::MAX_POINTS) {
                    throw new RuntimeException('Die Signatur enthält zu viele Punkte.');
                }
            }
        }

        return $strokes;
    }

    private function path(): string
    {
        $root = trim((string) config('applications.handwritten_signature_path'));
        if ($root === '' || $root === DIRECTORY_SEPARATOR) {
            throw new RuntimeException('Der private Signaturpfad ist nicht konfiguriert.');
        }

        return rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'source.enc';
    }

    private function directory(): string
    {
        $directory = dirname($this->path());
        if (is_link($directory) || (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory))) {
            throw new RuntimeException('Der private Signaturordner ist nicht verfügbar.');
        }
        $this->assertPrivateDirectory($directory);
        chmod($directory, 0700);

        return $directory;
    }

    private function assertPrivateDirectory(string $directory): void
    {
        if (is_link($directory) || ! is_dir($directory)) {
            throw new RuntimeException('Der private Signaturordner ist nicht verfügbar.');
        }
        $resolved = realpath($directory);
        $public = realpath(public_path());
        if ($resolved === false || ($public && ($resolved === $public || str_starts_with($resolved, $public.DIRECTORY_SEPARATOR)))) {
            throw new RuntimeException('Der private Signaturordner darf nicht im öffentlichen Webroot liegen.');
        }
    }
}
