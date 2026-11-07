<?php

namespace App\Support;

final class UnmodifiedApplicationPdf implements ApplicationPdfPostProcessor
{
    public function version(): string
    {
        return '1';
    }

    public function process(string $privatePdf, array $manifest): void {}
}
