<?php

namespace App\Support;

use RuntimeException;

/** A compiler failure that exposes only allowlisted, non-content diagnostics. */
final class LatexCompilationFailure extends RuntimeException
{
    private const DIAGNOSTICS = [
        'compiler_timeout' => '/__compiler_timeout__/i',
        'compiler_process_error' => '/__compiler_process_error__/i',
        'compiler_runtime_error' => '/__compiler_runtime_error__/i',
        'undefined_control_sequence' => '/Undefined control sequence/i',
        'missing_file' => '/(?:I can\'t find file|File .*? not found|No such file or directory)/i',
        'font_error' => '/(?:fontspec error|font .*? not found|font .*? unavailable|cannot find font)/i',
        'image_error' => '/(?:Cannot determine size of graphic|Unknown graphics extension|not a recognized image format)/i',
        'runaway_argument' => '/Runaway argument\?/i',
        'latex_fatal_error' => '/(?:Emergency stop|Fatal error occurred, no output PDF file produced)/i',
        'latex_error' => '/(?:^! LaTeX Error:|^! Package .* Error:)/m',
        'tex_error' => '/^! /m',
    ];

    private const LABELS = [
        'compiler_timeout' => 'Zeitlimit überschritten',
        'compiler_process_error' => 'Compiler konnte nicht gestartet werden',
        'compiler_runtime_error' => 'PDF-Kompilierung konnte nicht vorbereitet werden',
        'undefined_control_sequence' => 'unbekannter LaTeX-Befehl',
        'missing_file' => 'Datei fehlt oder ist nicht lesbar',
        'font_error' => 'Schriftart nicht verfügbar',
        'image_error' => 'Bilddatei kann nicht gelesen werden',
        'runaway_argument' => 'LaTeX-Argument nicht abgeschlossen',
        'latex_fatal_error' => 'LuaLaTeX hat den Lauf abgebrochen',
        'latex_error' => 'LaTeX-Paketfehler',
        'tex_error' => 'TeX-Fehler',
        'compiler_error' => 'nicht klassifizierter Compilerfehler',
    ];

    private function __construct(
        public readonly string $diagnosticCode,
        public readonly ?int $sourceLine,
        public readonly int $pass,
        public readonly ?int $exitCode,
        public readonly string $reference,
    ) {
        $details = $sourceLine === null ? '' : '; Zeile '.$sourceLine;
        $exit = $exitCode === null ? '' : '; Exit-Code '.$exitCode;
        parent::__construct(sprintf(
            'LuaLaTeX fehlgeschlagen: %s%s; Durchlauf %d%s. Referenz: %s.',
            self::LABELS[$diagnosticCode] ?? self::LABELS['compiler_error'],
            $details,
            $pass,
            $exit,
            $reference,
        ));
    }

    public static function fromCompilerLog(string $log, int $pass, ?int $exitCode = null, ?string $reference = null): self
    {
        $diagnosticCode = 'compiler_error';
        foreach (self::DIAGNOSTICS as $code => $pattern) {
            if (preg_match($pattern, $log) === 1) {
                $diagnosticCode = $code;
                break;
            }
        }

        $sourceLine = preg_match('/^l\.(\d+)(?:\s|$)/m', $log, $match) === 1
            ? (int) $match[1]
            : null;

        return new self($diagnosticCode, $sourceLine, max(1, $pass), $exitCode, self::reference($reference));
    }

    public static function processFailure(string $diagnosticCode, int $pass, ?int $exitCode = null, ?string $reference = null): self
    {
        if (! in_array($diagnosticCode, ['compiler_timeout', 'compiler_process_error', 'compiler_runtime_error'], true)) {
            $diagnosticCode = 'compiler_runtime_error';
        }

        return new self($diagnosticCode, null, max(1, $pass), $exitCode, self::reference($reference));
    }

    /** @return list<string> */
    public static function diagnosticCodes(): array
    {
        return array_keys(self::LABELS);
    }

    private static function reference(?string $reference): string
    {
        return is_string($reference) && preg_match('/^[a-f0-9]{12}$/D', $reference)
            ? $reference
            : bin2hex(random_bytes(6));
    }
}
