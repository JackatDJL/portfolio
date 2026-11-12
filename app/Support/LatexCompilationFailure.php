<?php

namespace App\Support;

use RuntimeException;

/** A compiler failure that exposes only allowlisted, non-content diagnostics. */
final class LatexCompilationFailure extends RuntimeException
{
    private const DIAGNOSTICS = [
        'undefined_control_sequence' => '/Undefined control sequence/i',
        'missing_file' => '/(?:I can\'t find file|File .*? not found|No such file or directory)/i',
        'font_error' => '/(?:fontspec error|font .*? not found|font .*? unavailable|cannot find font)/i',
        'image_error' => '/(?:Cannot determine size of graphic|Unknown graphics extension|not a recognized image format)/i',
        'runaway_argument' => '/Runaway argument\?/i',
        'latex_error' => '/(?:^! LaTeX Error:|^! Package .* Error:)/m',
        'tex_error' => '/^! /m',
    ];

    private function __construct(
        public readonly string $diagnosticCode,
        public readonly ?int $sourceLine,
        public readonly int $pass,
    ) {
        parent::__construct('PDF compilation failed. Compiler output is suppressed to protect private fields.');
    }

    public static function fromCompilerLog(string $log, int $pass): self
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

        return new self($diagnosticCode, $sourceLine, max(1, $pass));
    }
}
