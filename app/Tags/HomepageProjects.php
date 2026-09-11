<?php

namespace App\Tags;

use Statamic\Facades\Entry;
use Statamic\Tags\Tags;

class HomepageProjects extends Tags
{
    public function index()
    {
        $limit = max(1, min(3, $this->params->int('limit', 3)));

        return Entry::query()
            ->where('collection', 'projects')
            ->get()
            ->filter(fn ($entry) => $entry->published() && $entry->value('project_status') !== 'confidential')
            ->sort(function ($left, $right) {
                $rank = static fn ($entry) => in_array($entry->value('project_status'), ['active', 'maintained'], true) ? 0 : 1;
                $byStatus = $rank($left) <=> $rank($right);

                if ($byStatus !== 0) {
                    return $byStatus;
                }

                $date = static fn ($entry) => max(
                    strtotime((string) $entry->value('started_at')) ?: 0,
                    strtotime((string) $entry->value('ended_at')) ?: 0,
                );

                return ($date($right) <=> $date($left)) ?: strcmp($left->id(), $right->id());
            })
            ->take($limit);
    }
}
