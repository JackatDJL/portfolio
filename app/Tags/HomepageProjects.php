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

                return strtotime((string) $right->value('started_at')) <=> strtotime((string) $left->value('started_at'));
            })
            ->take($limit);
    }
}
