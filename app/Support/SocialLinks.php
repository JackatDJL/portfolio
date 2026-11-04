<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Statamic\Fields\Value;

final class SocialLinks
{
    /** Returns public HTTPS platform links in a consistent format. */
    public static function normalize(mixed $rows): array
    {
        $rows = self::plainValue($rows);

        if ($rows instanceof Arrayable) {
            $rows = $rows->toArray();
        }

        if (! is_iterable($rows)) {
            return [];
        }

        $links = [];

        foreach ($rows as $row) {
            $row = self::plainValue($row);
            if ($row instanceof Arrayable) {
                $row = $row->toArray();
            }
            if (! is_array($row)) {
                continue;
            }

            $label = self::stringValue($row['label'] ?? null);
            $url = self::stringValue($row['url'] ?? null);

            if ($label === null || $url === null
                || filter_var($url, FILTER_VALIDATE_URL) === false
                || ! preg_match('#^https://#i', $url)) {
                continue;
            }

            $links[] = [
                'label' => $label,
                'url' => $url,
                'schema_same_as' => filter_var(self::plainValue($row['schema_same_as'] ?? true), FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $links;
    }

    private static function plainValue(mixed $value): mixed
    {
        return $value instanceof Value ? $value->value() : $value;
    }

    private static function stringValue(mixed $value): ?string
    {
        $value = self::plainValue($value);

        if (! is_string($value) && ! ($value instanceof \Stringable)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
