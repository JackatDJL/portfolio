<?php

namespace App\Support;

final class ContentSyncConflictDocument
{
    private const MAX_TEXT_BYTES = 262_144;

    private const DEFAULT_CONFLICT_MARKER_SIZE = 7;

    /** @return array{binary: bool, sections: list<array{id: int, base: string|null, ours: string, theirs: string, protected: bool}>} */
    public static function inspect(string $contents, int $markerSize = self::DEFAULT_CONFLICT_MARKER_SIZE): array
    {
        $markerSize = self::validatedMarkerSize($markerSize);
        if (strlen($contents) > self::MAX_TEXT_BYTES || ! mb_check_encoding($contents, 'UTF-8')) {
            return ['binary' => true, 'sections' => []];
        }

        $lines = preg_split('/(?<=\n)/', $contents, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sections = [];
        $inside = false;
        $ours = '';
        $base = null;
        $theirs = '';
        $state = 'ours';

        foreach ($lines as $line) {
            $marker = rtrim($line, "\r\n");
            if (! $inside && self::isSectionMarker($marker, '<', $markerSize, hasLabel: true)) {
                $inside = true;
                $ours = '';
                $base = null;
                $theirs = '';
                $state = 'ours';

                continue;
            }

            if ($inside && self::isSectionMarker($marker, '|', $markerSize, hasLabel: true) && $state === 'ours') {
                $state = 'base';

                continue;
            }

            if ($inside && self::isSectionMarker($marker, '=', $markerSize, hasLabel: false) && in_array($state, ['ours', 'base'], true)) {
                $state = 'theirs';
                $base ??= null;

                continue;
            }

            if ($inside && self::isSectionMarker($marker, '>', $markerSize, hasLabel: true) && $state === 'theirs') {
                $id = count($sections);
                $private = self::containsProtectedCvField($ours.$base.$theirs);
                $sections[] = [
                    'id' => $id,
                    'base' => $base,
                    'ours' => $ours,
                    'theirs' => $theirs,
                    'protected' => $private,
                ];
                $inside = false;

                continue;
            }

            if ($inside) {
                match ($state) {
                    'ours' => $ours .= $line,
                    'base' => $base = ($base ?? '').$line,
                    'theirs' => $theirs .= $line,
                };
            }
        }

        if ($inside || $sections === []) {
            throw new ContentSyncFailure('The candidate conflict could not be read safely. It was left untouched.');
        }

        return ['binary' => false, 'sections' => $sections];
    }

    /** @param list<array{id: int, base: string|null, ours: string, theirs: string, protected: bool}> $sections */
    public static function compose(string $contents, array $sections, array $resolutions, int $markerSize = self::DEFAULT_CONFLICT_MARKER_SIZE): string
    {
        $markerSize = self::validatedMarkerSize($markerSize);
        if (count($sections) !== count($resolutions)) {
            throw new ContentSyncFailure('Choose a resolution for every conflict section. The candidate was left untouched.');
        }

        $lines = preg_split('/(?<=\n)/', $contents, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];
        $plain = '';
        $sectionId = 0;
        $inside = false;
        $state = 'ours';
        $ours = '';
        $base = null;
        $theirs = '';

        foreach ($lines as $line) {
            $marker = rtrim($line, "\r\n");
            if (! $inside && self::isSectionMarker($marker, '<', $markerSize, hasLabel: true)) {
                if ($plain !== '') {
                    $parts[] = $plain;
                    $plain = '';
                }
                $inside = true;
                $state = 'ours';
                $ours = '';
                $base = null;
                $theirs = '';

                continue;
            }

            if ($inside && self::isSectionMarker($marker, '|', $markerSize, hasLabel: true) && $state === 'ours') {
                $state = 'base';

                continue;
            }

            if ($inside && self::isSectionMarker($marker, '=', $markerSize, hasLabel: false) && in_array($state, ['ours', 'base'], true)) {
                $state = 'theirs';

                continue;
            }

            if ($inside && self::isSectionMarker($marker, '>', $markerSize, hasLabel: true) && $state === 'theirs') {
                $section = $sections[$sectionId] ?? null;
                if (! is_array($section)) {
                    throw new ContentSyncFailure('The candidate conflict changed while it was being resolved. Reload the conflict details.');
                }
                $resolution = $resolutions[$sectionId] ?? null;
                if (! is_array($resolution) || ($resolution['id'] ?? null) !== $sectionId) {
                    throw new ContentSyncFailure('The submitted conflict sections do not match the candidate. Reload the conflict details.');
                }

                $choice = $resolution['choice'] ?? null;
                if ($choice === 'ours') {
                    $parts[] = $ours;
                } elseif ($choice === 'theirs') {
                    $parts[] = $theirs;
                } elseif ($choice === 'manual' && ! $section['protected'] && is_string($resolution['text'] ?? null)) {
                    $manual = $resolution['text'];
                    if (strlen($manual) > self::MAX_TEXT_BYTES || self::containsConflictMarker($manual)) {
                        throw new ContentSyncFailure('A manual section is too large or still contains Git conflict markers. The candidate was left untouched.');
                    }
                    $parts[] = $manual;
                } else {
                    throw new ContentSyncFailure('Choose a valid resolution for every conflict section. The candidate was left untouched.');
                }

                $sectionId++;
                $inside = false;

                continue;
            }

            if ($inside) {
                match ($state) {
                    'ours' => $ours .= $line,
                    'base' => $base = ($base ?? '').$line,
                    'theirs' => $theirs .= $line,
                };
            } else {
                $plain .= $line;
            }
        }

        if ($inside || $sectionId !== count($sections)) {
            throw new ContentSyncFailure('The candidate conflict changed while it was being resolved. Reload the conflict details.');
        }
        if ($plain !== '') {
            $parts[] = $plain;
        }

        $resolved = implode('', $parts);
        if (strlen($resolved) > self::MAX_TEXT_BYTES) {
            throw new ContentSyncFailure('The resolved file is too large. The candidate was left untouched.');
        }
        if (self::containsConflictMarker($resolved)) {
            throw new ContentSyncFailure('Resolved content still contains Git conflict markers. The candidate was left untouched.');
        }

        return $resolved;
    }

    public static function displaySection(string $contents, bool $protected): string
    {
        return $protected ? '[Geschützte CV-Werte werden nicht angezeigt.]' : $contents;
    }

    public static function containsConflictMarker(string $contents, int $markerSize = self::DEFAULT_CONFLICT_MARKER_SIZE): bool
    {
        $minimumSize = min(self::DEFAULT_CONFLICT_MARKER_SIZE, self::validatedMarkerSize($markerSize));

        return preg_match('/^(?:<{'.$minimumSize.',}|\|{'.$minimumSize.',}|={'.$minimumSize.',}|>{'.$minimumSize.',})(?:\s|$)/m', $contents) === 1;
    }

    private static function isSectionMarker(string $line, string $character, int $markerSize, bool $hasLabel): bool
    {
        $pattern = '/^'.str_repeat(preg_quote($character, '/'), $markerSize).($hasLabel ? '(?:\s|$)' : '\s*$').'/';

        return preg_match($pattern, $line) === 1;
    }

    private static function validatedMarkerSize(int $markerSize): int
    {
        if ($markerSize < 1 || $markerSize > 256) {
            throw new ContentSyncFailure('The candidate conflict marker size is unsupported. It was left untouched.');
        }

        return $markerSize;
    }

    private static function containsProtectedCvField(string $contents): bool
    {
        foreach (CvPrivateData::FIELDS as $field) {
            if (preg_match('/^\s*[\'\"]?'.preg_quote($field, '/').'[\'\"]?\s*:\s*/m', $contents) === 1) {
                return true;
            }
        }

        return false;
    }
}
