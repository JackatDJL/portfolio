<?php

namespace App\Signatures;

use Symfony\Component\Process\Process;

final class SandboxSignatureProvider implements SignatureProvider
{
    public function id(): string
    {
        return 'local-sandbox';
    }

    public function capabilities(): SignatureCapabilities
    {
        $available = app()->environment(['local', 'testing']) && config('signatures.sandbox_enabled');

        return new SignatureCapabilities($available, true, false, true, false, false, false);
    }

    public function sign(string $immutableUnsignedPdf, string $sha256, string $privateOutput): void
    {
        if (! $this->capabilities()->available || ! hash_equals($sha256, hash_file('sha256', $immutableUnsignedPdf))) {
            throw new SignatureWorkflowException('Sandbox nicht freigegeben oder Dokument verändert.');
        }
        $process = new Process([config('applications.python'), base_path('scripts/application-signatures.py'), 'sandbox', $immutableUnsignedPdf, $privateOutput]);
        try {
            $process->setTimeout((int) config('signatures.validation_timeout'))->run();
            if (! $process->isSuccessful()) {
                throw new \RuntimeException;
            }
        } catch (\Throwable) {
            throw new SignatureWorkflowException('Lokale Testsignatur fehlgeschlagen. Keine QES und kein kostenpflichtiger Auftrag.');
        }
    }
}
