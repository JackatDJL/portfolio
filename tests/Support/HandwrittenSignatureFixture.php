<?php

namespace Tests\Support;

/** Generates a synthetic test squiggle in memory; no real signature asset is stored in Git. */
final class HandwrittenSignatureFixture
{
    public static function png(int $variant = 0): string
    {
        $width = 480;
        $height = 140;
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\0";
            for ($x = 0; $x < $width; $x++) {
                $curve = 69 + (int) round(22 * sin($x / 27)) + (int) round(8 * sin($x / 8));
                $second = 75 + (int) round(19 * sin(($x + 40) / 35));
                $ink = ($x > 22 && $x < 440 && abs($y - $curve) <= 2)
                    || ($x > 90 && $x < 265 && abs($y - $second) <= 1)
                    || ($x > 300 && $x < 400 && abs($y - (29 + (int) round($x / 16))) <= 2);
                $colour = $variant === 0 ? "\x17\x31\x2a" : "\x1d\x2b\x74";
                $raw .= $ink ? $colour."\xff" : "\0\0\0\0";
            }
        }

        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNC5', $width, $height, 8, 6, 0, 0, 0))
            .$chunk('IDAT', gzcompress($raw, 9))
            .$chunk('IEND', '');
    }

    public static function strokes(): array
    {
        return [[['x' => 0.1, 'y' => 0.5], ['x' => 0.5, 'y' => 0.4], ['x' => 0.9, 'y' => 0.6]]];
    }
}
