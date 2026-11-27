<?php
namespace App\Tags;

use Statamic\Tags\Tags;
use Statamic\Facades\Entry;
use Statamic\Fields\Value;

class CvTitle extends Tags
{
    /** Selects the page title, including CV-specific titles for profile and section routes. */
    public function index(): string
    {
        $request = request();
        if (!$request->is('cv', 'cv/*')) {
            $plain = static fn ($value) => $value instanceof Value ? $value->value() : $value;
            $seoTitle = $plain($this->context->get('seo_title'));
            $pageTitle = $plain($this->context->get('title'));
            $siteName = $plain($this->context->get('site:name'));
            if ($request->path() === '/' && ! $seoTitle) {
                return (string) ($siteName ?: 'Jack Ruder');
            }

            return (string) ($seoTitle ?: $pageTitle ?: $siteName ?: 'Jack Ruder');
        }
        $name = (string) ($this->context->get('site:name') ?? 'Lebenslauf');
        $title = $name.' · Lebenslauf';
        if (in_array($request->segment(2), ['exp', 'edu'], true)) return $title.' · '.$this->context->get('title');
        if ($slug = $request->segment(2)) {
            $profile = Entry::query()->where('collection', 'cv_profiles')->where('slug', $slug)->first();
            if ($profile) $title .= ' · '.$profile->value('title');
        }
        return $title;
    }
}
