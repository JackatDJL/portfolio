<?php

namespace App\Support;

interface ApplicationPdfPostProcessor
{
    /** Change this version whenever post-processing behavior or configuration changes. */
    public function version(): string;

    /** Final hook, after composition and before atomic cache publication. No QES is implemented. */
    public function process(string $privatePdf, array $manifest): void;
}
