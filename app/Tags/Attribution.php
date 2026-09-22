<?php

namespace App\Tags;

use Statamic\Tags\Tags;

class Attribution extends Tags
{
    public function index(): string
    {
        return self::linkedText((string) $this->params->get('text', ''));
    }

    /** Preserve the editor's attribution, linking only explicit HTTP(S) sources. */
    public static function linkedText(string $text): string
    {
        $parts = preg_split('~(https?://[^\s<>]+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        return implode('', array_map(static function (string $part): string {
            if (preg_match('~^https?://~i', $part)) {
                $url = rtrim($part, '.,;)');
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    return '<a href="'.e($url).'">Quelle</a>'.e(substr($part, strlen($url)));
                }
            }

            return e($part);
        }, $parts ?: []));
    }
}
