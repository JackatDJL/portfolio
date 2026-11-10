<?php

namespace App\Signatures;

interface SignatureProvider
{
    public function id(): string;

    public function capabilities(): SignatureCapabilities;

    /** Called only by a CSRF-protected, explicitly authorized action for this exact digest. */
    public function sign(string $immutableUnsignedPdf, string $sha256, string $privateOutput): void;
}
