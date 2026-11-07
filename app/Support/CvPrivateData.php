<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Statamic\Globals\Variables;

final class CvPrivateData
{
    public const FIELDS = [
        'private_email',
        'phone',
        'street',
        'house_number',
        'postal_code',
        'city',
        'country',
        'date_of_birth',
        'place_of_birth',
    ];

    public static function reveal(Variables $variables, bool $strict = false): array
    {
        $revealed = [];

        foreach (self::FIELDS as $handle) {
            $ciphertext = $variables->value($handle);
            if ($ciphertext === null || $ciphertext === '') {
                continue;
            }
            if (! is_string($ciphertext)) {
                if ($strict) {
                    throw new \RuntimeException('Configured private CV data is malformed.');
                }
                report(new \RuntimeException("The CV field [{$handle}] is not encrypted string data."));

                continue;
            }

            try {
                $revealed[$handle] = Crypt::decryptString($ciphertext);
            } catch (DecryptException) {
                if ($strict) {
                    throw new \RuntimeException('Configured private CV data cannot be decrypted.');
                }
                report(new DecryptException("The CV field [{$handle}] is not valid encrypted data."));
            }
        }

        return $revealed;
    }
}
