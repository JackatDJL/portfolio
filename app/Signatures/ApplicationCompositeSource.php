<?php

namespace App\Signatures;

use Statamic\Contracts\Entries\Entry;

interface ApplicationCompositeSource
{
    public function render(Entry $entry, bool $cached = true): string;
}
