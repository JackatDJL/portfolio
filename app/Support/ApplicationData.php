<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\GlobalSet;

final class ApplicationData
{
    public const GROUPS = ['recipient', 'letter', 'cv', 'documents', 'signature', 'tracking'];

    public static function encrypt(array $value): string
    {
        return Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR));
    }

    public static function decrypt(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        // Invalid ciphertext must fail closed rather than silently erase private data.
        return json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function group(Entry $entry, string $handle): array
    {
        return self::decrypt($entry->get($handle))[0] ?? [];
    }

    public static function documents(Entry $entry): array
    {
        $selection = self::group($entry, 'documents');
        if (($selection['mode'] ?? 'inherit') === 'override') {
            return $selection['items'] ?? [];
        }
        $defaults = GlobalSet::find('application_defaults')?->inDefaultSite();

        return self::decrypt($defaults?->get('documents'))[0]['items'] ?? [];
    }

    public static function textComponents(): array
    {
        $defaults = GlobalSet::find('application_defaults')?->inDefaultSite();

        return self::decrypt($defaults?->get('text_components'))[0]['items'] ?? [];
    }

    /** References remain live: changes to a reusable component affect its uses. */
    public static function coverLetter(Entry $entry): array
    {
        $components = collect(self::textComponents())->keyBy('key');

        return collect(self::group($entry, 'letter')['body'] ?? [])->flatMap(function ($node) use ($components) {
            if (($node['type'] ?? null) !== 'set') {
                return [$node];
            }
            $values = $node['attrs']['values'] ?? [];
            if (($values['type'] ?? null) !== 'component') {
                throw ValidationException::withMessages(['letter.body' => 'Nicht unterstützter Textbaustein im Anschreiben. Bitte den Block entfernen oder einen gespeicherten Textbaustein wählen.']);
            }
            $component = $components->get($values['component'] ?? '');
            if (! $component) {
                throw new \RuntimeException('Referenced application text component is missing.');
            }

            return $component['body'] ?? [];
        })->all();
    }

    public static function date(Entry $entry): string
    {
        $tracking = self::group($entry, 'tracking');

        return $tracking['date_override'] ?? $tracking['application_date'] ?? now()->toDateString();
    }
}
