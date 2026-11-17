<?php

namespace App\Support;

/** Builds a decorative, synthetic line motif without accepting or inspecting signature data. */
final class SignatureSecurityPattern
{
    private const PALETTES = [
        'blue' => ['#315CA8', '#8AA7D3'],
        'teal' => ['#247A80', '#87B9BA'],
        'violet' => ['#5C4B91', '#A89BCB'],
    ];

    private const DENSITIES = [
        'light' => 5,
        'balanced' => 8,
        'fine' => 11,
    ];

    /** Settings are allowlisted; this output is illustration, not an anti-copy or identity control. */
    public function render(string $style = 'guilloche', string $color = 'blue', string $density = 'balanced'): string
    {
        $style = in_array($style, ['guilloche', 'rosette', 'waves'], true) ? $style : 'guilloche';
        [$primary, $secondary] = self::PALETTES[$color] ?? self::PALETTES['blue'];
        $count = self::DENSITIES[$density] ?? self::DENSITIES['balanced'];

        $paths = match ($style) {
            'rosette' => $this->rosettes($count, $primary, $secondary),
            'waves' => $this->waves($count, $primary, $secondary, 4.0, 0.58),
            default => $this->waves($count, $primary, $secondary, 3.0, 0.68),
        };

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 720 180" data-signature-pattern role="img" aria-label="Dekoratives Linienmotiv" focusable="false">'
            .'<g fill="none" stroke-linecap="round" stroke-width="2.2" opacity="0.78">'.$paths.'</g></svg>';
    }

    private function waves(int $count, string $primary, string $secondary, float $frequency, float $opacity): string
    {
        $output = '';
        foreach ([[$primary, 1.0], [$secondary, -1.0]] as $family => [$color, $direction]) {
            for ($line = 0; $line < $count; $line++) {
                $phase = 2 * M_PI * $line / $count;
                $path = '';
                for ($step = 0; $step <= 72; $step++) {
                    $progress = $step / 72;
                    $x = 14 + $progress * 692;
                    $angle = 2 * M_PI * $frequency * $progress;
                    $y = 90
                        + 42 * sin($angle + $phase * $direction)
                        + 17 * sin($angle * 2.15 - $phase * 1.7) * $direction;
                    $path .= ($step === 0 ? 'M' : 'L').$this->number($x).' '.$this->number($y);
                }
                $output .= '<path vector-effect="non-scaling-stroke" d="'.$path.'" stroke="'.$color.'" stroke-opacity="'.$opacity.'"/>';
            }
        }

        return $output;
    }

    private function rosettes(int $count, string $primary, string $secondary): string
    {
        $output = '';
        foreach ([[$primary, 0.0], [$secondary, 0.16]] as [$color, $offset]) {
            for ($layer = 0; $layer < max(3, min(7, $count)); $layer++) {
                $phase = $offset + 2 * M_PI * $layer / max(3, min(7, $count));
                $path = '';
                for ($step = 0; $step <= 180; $step++) {
                    $angle = 2 * M_PI * $step / 180;
                    $radius = 35 + 12 * sin(8 * $angle + $phase) + 5 * cos(13 * $angle - 2 * $phase);
                    $x = 360 + $radius * cos($angle);
                    $y = 90 + $radius * 0.72 * sin($angle);
                    $path .= ($step === 0 ? 'M' : 'L').$this->number($x).' '.$this->number($y);
                }
                $output .= '<path vector-effect="non-scaling-stroke" d="'.$path.'" stroke="'.$color.'" stroke-opacity="0.62"/>';
            }
        }

        return $output;
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
