<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ApplicationSignatureVersion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['verification'];

    protected function casts(): array
    {
        return ['verification' => 'encrypted:array', 'sandbox' => 'boolean'];
    }
}
