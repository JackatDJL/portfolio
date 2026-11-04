<?php
namespace App\Tags;

use Statamic\Tags\Tags;
use Statamic\Facades\Entry;

class CvTitle extends Tags
{
    /** Selects the page title, including CV-specific titles for profile and section routes. */
    public function index(): string
    {
        $request = request();
        if (!$request->is('cv', 'cv/*')) {
            if ($request->path() === '/' && ! $this->context->get('seo_title')) {
                return (string) ($this->context->get('site:name') ?: 'Jack Ruder');
            }

            return (string) ($this->context->get('seo_title') ?: $this->context->get('title') ?: $this->context->get('site:name') ?: 'Jack Ruder');
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
