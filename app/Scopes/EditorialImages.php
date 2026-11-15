<?php

namespace App\Scopes;

use Statamic\Query\Scopes\Scope;

final class EditorialImages extends Scope
{
    public function apply($query, $values): void
    {
        $query->where('is_image', true);
    }
}
