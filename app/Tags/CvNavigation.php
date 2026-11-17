<?php

namespace App\Tags;

use App\Support\CvNavigation as Navigation;
use Statamic\Tags\Tags;

class CvNavigation extends Tags
{
    public function hasContext(): bool
    {
        return request()->query->has('cv_return');
    }

    public function index(): string
    {
        return Navigation::returnPath(request()->query('cv_return'));
    }

    public function link(): string
    {
        $path = '/'.trim(request()->path(), '/');
        if (! preg_match('#^/cv(?:/[a-z0-9-]+)?$#D', $path) && ! Navigation::applicationId($path)) {
            return e((string) $this->params->get('url'));
        }

        return e(Navigation::detailUrl((string) $this->params->get('url'), Navigation::returnPath($path)));
    }
}
