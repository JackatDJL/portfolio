<?php

namespace App\Signatures;

final class UnavailableSignatureProvider implements SignatureProvider
{
    public function id(): string
    {
        return 'unavailable';
    }

    public function capabilities(): SignatureCapabilities
    {
        return new SignatureCapabilities(false, false, false, false, false, false, false);
    }

    public function sign(string $immutableUnsignedPdf, string $sha256, string $privateOutput): void
    {
        throw new SignatureWorkflowException('Kein zugänglicher Live-QES-Anbieter konfiguriert. Bitte extern signieren und importieren.');
    }
}
