<?php

namespace App\Signatures;

final readonly class SignatureCapabilities
{
    public function __construct(
        public bool $available,
        public bool $remoteSigning,
        public bool $qualifiedSignatures,
        public bool $sandbox,
        public bool $documentUpload,
        public bool $hashSigning,
        public bool $longTermValidation,
    ) {}
}
