<?php
namespace App\Tags;

use Statamic\Tags\Tags;
use Statamic\Facades\Entry;

class CvTitle extends Tags
{
    public function index(): string
    {
        $request = request();
        if (!$request->is('cv', 'cv/*')) return (string) ($this->context->get('title') ?? $this->context->get('site:name') ?? 'Jack Ruder');
        $title = 'Jack Ruder · Lebenslauf';
        if (in_array($request->segment(2), ['exp', 'edu'], true)) return $title.' · '.$this->context->get('title');
        if ($slug = $request->segment(2)) {
            $profile = Entry::query()->where('collection', 'cv_profiles')->where('slug', $slug)->first();
            if ($profile) $title .= ' · '.$profile->value('title');
        }
        return $title;
    }
}
