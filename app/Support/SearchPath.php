<?php

namespace App\Support;

use Statamic\Fields\Value;

final class SearchPath
{
    public static function normalize(mixed $path): ?string
    {
        if ($path instanceof Value) {
            $path = $path->value();
        }

        if ($path instanceof \Stringable) {
            $path = (string) $path;
        }

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if ($path === '/') {
            return '/';
        }

        $path = trim($path, '/');

        return $path === '' ? null : '/'.$path;
    }

    public static function normalizeList(mixed $paths): array
    {
        if ($paths instanceof Value) {
            $paths = $paths->value();
        }

        if (! is_iterable($paths)) {
            return [];
        }

        $normalized = [];

        foreach ($paths as $path) {
            $path = self::normalize($path);

            if ($path !== null) {
                $normalized[] = $path;
            }
        }

        return array_values(array_unique($normalized));
    }

    public static function isValidList(mixed $paths): bool
    {
        if ($paths instanceof Value) {
            $paths = $paths->value();
        }

        if (! is_array($paths)) {
            return false;
        }

        foreach ($paths as $path) {
            if (! is_string($path) || ! preg_match('~^/(?:[^/?#]+(?:/[^/?#]+)*)?$~D', $path)) {
                return false;
            }
        }

        return true;
    }
}
