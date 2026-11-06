<?php

namespace App\Signatures;

interface SignatureValidator
{
    /** Returns integrity/binding/trust/qualification separately; no provider or filename can confer QES. */
    public function validate(string $unsignedPdf, string $signedPdf): array;
}
