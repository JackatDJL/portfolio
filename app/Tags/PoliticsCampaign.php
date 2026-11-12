<?php

namespace App\Tags;

use Carbon\CarbonImmutable;
use Statamic\Tags\Tags;

class PoliticsCampaign extends Tags
{
    public function active(): bool
    {
        $enabled = filter_var($this->context->get('campaign_enabled') ?? false, FILTER_VALIDATE_BOOLEAN);
        $start = $this->date($this->context->get('campaign_start'));
        $end = $this->date($this->context->get('campaign_end'));

        if (! $enabled || ! $start || ! $end || $start->gt($end)) {
            return false;
        }

        $today = CarbonImmutable::today(config('app.timezone', 'Europe/Berlin'));

        return $today->betweenIncluded($start->startOfDay(), $end->endOfDay());
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone', 'Europe/Berlin'));
        } catch (\Throwable) {
            return null;
        }
    }
}
