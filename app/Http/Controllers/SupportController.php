<?php

namespace App\Http\Controllers;

use Statamic\Facades\GlobalSet;
use Statamic\View\View;

final class SupportController extends Controller
{
    public function show()
    {
        $email = (string) (GlobalSet::findByHandle('jacks-portfolio')?->inDefaultSite()?->get('email') ?: '');
        $goals = collect(config('support.goals'))->map(function (array $goal): array {
            $goal['received'] = max(0, min((int) $goal['budget'], (int) ($goal['received'] ?? 0)));
            $goal['progress'] = $goal['budget'] > 0 ? (int) floor($goal['received'] / $goal['budget'] * 100) : 0;

            return $goal;
        });

        return response((new View)
            ->template('support/index')
            ->layout('layout')
            ->with([
                'goals' => $goals,
                'acknowledgments' => collect(),
                'email' => $email,
                'githubSponsorsUrl' => config('support.github_sponsors_url'),
                'title' => 'Unterstützen',
            ])
            ->render());
    }
}
