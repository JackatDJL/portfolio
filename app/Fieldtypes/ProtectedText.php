<?php

namespace App\Fieldtypes;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Statamic\Fields\Fieldtype;

class ProtectedText extends Fieldtype
{
    protected static $title = 'Geschützter Text';

    protected $component = 'text';

    protected $icon = 'text-formatting-all-caps';

    public function process($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($this->decrypt($value) !== null) {
            return $value;
        }

        return Crypt::encryptString((string) $value);
    }

    public function preProcess($value)
    {
        return $this->decrypt($value) ?? '';
    }

    private function decrypt($value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
