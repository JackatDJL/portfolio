<?php

namespace App\Http\Controllers;

use App\Support\CvProfileContentResolver;
use App\Support\SignatureSecurityPattern;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Statamic\Facades\GlobalSet;
use Statamic\View\View;

final class CvPageController extends Controller
{
    public function show(CvProfileContentResolver $resolver, SignatureSecurityPattern $patterns): Response
    {
        return $this->render($resolver->resolve(), $patterns);
    }

    public function profile(string $profile, CvProfileContentResolver $resolver, SignatureSecurityPattern $patterns): Response
    {
        return $this->render($resolver->resolve($profile), $patterns);
    }

    private function render(array $resolved, SignatureSecurityPattern $patterns): Response
    {
        $cv = GlobalSet::find('cv')?->inDefaultSite();
        $patternEnabled = filter_var($cv?->value('signature_pattern_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $signatureEnabled = filter_var($cv?->value('private_cv_signature_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $pattern = $patternEnabled ? $patterns->render(
            (string) $cv->value('signature_pattern_style', 'guilloche'),
            (string) $cv->value('signature_pattern_color', 'blue'),
            (string) $cv->value('signature_pattern_density', 'balanced'),
        ) : null;

        return response((new View)
            ->template($resolved['profile'] ? 'cv/profile' : 'cv/show')
            ->layout('layout')
            ->with([
                'title' => 'Lebenslauf',
                'cv_resolved' => $resolved,
                'profile_title' => $resolved['profile']['title'] ?? null,
                'recipient_name' => $resolved['profile']['recipient'] ?? null,
                'application_year' => $resolved['profile']['year'] ?? null,
                'accent_color' => $resolved['profile']['accent'] ?? null,
                'optional_context' => $resolved['profile']['context'] ?? null,
                'cv_signature_security_pattern' => $pattern,
                'cv_private_signature_enabled' => $signatureEnabled,
                'cv_signature_place' => trim((string) $cv?->value('print_place', '')) ?: (string) ($resolved['public_identity']['location'] ?? ''),
                'cv_signature_date' => trim((string) $cv?->value('print_date', '')) ?: Carbon::now()->locale('de')->translatedFormat('j. F Y'),
            ])
            ->render());
    }
}
