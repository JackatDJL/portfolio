<?php

namespace App\Support;

use Illuminate\Http\Request;
use Statamic\StaticCaching\Cachers\FileCacher;

final class ProjectVariantFileCacher extends FileCacher
{
    public function hasCachedPage(Request $request)
    {
        return ! $request->query->has('hero') && parent::hasCachedPage($request);
    }
}
