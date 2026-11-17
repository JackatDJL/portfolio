<?php

namespace App\Support;

use Statamic\StaticCaching\DefaultUrlExcluder;
use Statamic\StaticCaching\UrlExcluder;

/** Detail-page variants must not share a static response with their default URL. */
final class CvNavigationUrlExcluder implements UrlExcluder
{
    public function __construct(private readonly DefaultUrlExcluder $defaults) {}

    public function isExcluded(string $url): bool
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

        return array_key_exists('cv_return', $query)
            || array_key_exists('hero', $query)
            || $this->defaults->isExcluded($url);
    }
}
