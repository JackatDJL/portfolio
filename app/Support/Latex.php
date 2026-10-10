<?php

namespace App\Support;

final class Latex
{
    public static function escape(?string $value): string
    {
        return strtr((string) $value, [
            '\\' => '\\textbackslash{}',
            '&' => '\\&',
            '%' => '\\%',
            '$' => '\\$',
            '#' => '\\#',
            '_' => '\\_',
            '{' => '\\{',
            '}' => '\\}',
            '~' => '\\textasciitilde{}',
            '^' => '\\textasciicircum{}',
        ]);
    }

    public static function accessibleAccent(string $accent): string
    {
        $hex = ltrim(trim($accent), '#');
        if (preg_match('/^[a-fA-F0-9]{3}$/', $hex)) {
            $hex = implode('', array_map(fn (string $part) => $part.$part, str_split($hex)));
        }
        if (! preg_match('/^[a-fA-F0-9]{6}$/', $hex)) {
            $hex = '5ADBBD';
        }

        $channels = array_map('hexdec', str_split($hex, 2));
        $contrastAt = static function (float $scale) use ($channels): float {
            $rgb = array_map(fn (int $channel) => ($channel * $scale) / 255, $channels);
            $linear = array_map(
                fn (float $channel) => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4,
                $rgb,
            );
            $luminance = (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);

            return 1.05 / ($luminance + 0.05);
        };

        if ($contrastAt(1) >= 4.5) {
            return strtoupper($hex);
        }

        $low = 0.0;
        $high = 1.0;
        for ($iteration = 0; $iteration < 28; $iteration++) {
            $middle = ($low + $high) / 2;
            if ($contrastAt($middle) >= 4.5) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        do {
            $adjusted = strtoupper(implode('', array_map(
                fn (int $channel) => str_pad(dechex((int) floor($channel * $low)), 2, '0', STR_PAD_LEFT),
                $channels,
            )));
            $low -= 0.001;
        } while (self::contrastAgainstWhite($adjusted) < 4.5 && $low > 0);

        return $adjusted;
    }

    public static function contrastAgainstWhite(string $hex): float
    {
        $channels = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
        $linear = array_map(static function (int $channel): float {
            $rgb = $channel / 255;

            return $rgb <= 0.04045 ? $rgb / 12.92 : (($rgb + 0.055) / 1.055) ** 2.4;
        }, $channels);
        $luminance = (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);

        return 1.05 / ($luminance + 0.05);
    }
}
