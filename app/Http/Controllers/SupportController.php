<?php

namespace App\Http\Controllers;

use Statamic\Facades\GlobalSet;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Statamic\View\View;

final class SupportController extends Controller
{
    public function show()
    {
        $email = (string) (GlobalSet::findByHandle('jacks-portfolio')?->inDefaultSite()?->get('email') ?: '');
        $confirmed = [];
        $acknowledgments = [];
        foreach (Schema::hasTable('sponsorship_records') ? DB::table('sponsorship_records')->get(['funding_category', 'encrypted_payload']) : [] as $record) {
            $data = json_decode(Crypt::decryptString($record->encrypted_payload), true, 512, JSON_THROW_ON_ERROR);
            if (! empty($data['received_confirmed']) && ($data['contribution_kind'] ?? '') !== 'in_kind') {
                $confirmed[$record->funding_category] = ($confirmed[$record->funding_category] ?? 0) + (int) ($data['received_cents'] ?? 0);
                if (! empty($data['acknowledgment_permission']) && trim((string) ($data['organisation'] ?? '')) !== '') {
                    $acknowledgments[] = trim($data['organisation']);
                }
            }
        }
        $goals = collect(config('support.goals'))->map(function (array $goal) use ($confirmed): array {
            $goal['received'] = max(0, intdiv((int) ($confirmed[$goal['key']] ?? 0), 100));
            $goal['progress'] = $goal['budget'] > 0 ? min(100, (int) floor($goal['received'] / $goal['budget'] * 100)) : 0;

            return $goal;
        });

        return response((new View)
            ->template('support/index')
            ->layout('layout')
            ->with([
                'goals' => $goals,
                'acknowledgments' => collect($acknowledgments)->unique()->values(),
                'email' => $email,
                'githubSponsorsUrl' => config('support.github_sponsors_url'),
                'title' => 'Unterstützen',
            ])
            ->render());
    }
}
