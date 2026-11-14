<?php

namespace App\Http\Controllers;

use App\Support\ApplicationData;
use App\Support\CvNavigation;
use App\Support\CvPdfRenderer;
use App\Support\CvProfileContentResolver;
use App\Support\SignatureSecurityPattern;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\View\View;

final class CvPageController extends Controller
{
    public function show(CvProfileContentResolver $resolver, SignatureSecurityPattern $patterns): Response
    {
        return $this->render($resolver->resolve(), $patterns);
    }

    public function profile(Request $request, string $profile, CvProfileContentResolver $resolver, SignatureSecurityPattern $patterns): Response
    {
        // Check if this is an application slug
        $application = Entry::query()->where('collection', 'applications')->where('slug', $profile)->first();
        if ($application) {
            abort_unless(CvNavigation::authorized($request, '/cv/'.$profile), 404);
            return $this->render($resolver->resolveForApplication($application), $patterns, $application);
        }
        
        // Check if there's an authorized application that references this CV profile
        $cvProfile = Entry::query()->where('collection', 'cv_profiles')->where('slug', $profile)->first();
        if ($cvProfile && ApplicationCapabilities::hasAuthorizedApplication($request)) {
            $authorizedAppId = ApplicationCapabilities::authorizedApplicationId($request);
            if ($authorizedAppId) {
                $authorizedApp = Entry::find($authorizedAppId);
                if ($authorizedApp && $authorizedApp->collectionHandle() === 'applications') {
                    // Check if this application references the CV profile
                    $appCv = ApplicationData::group($authorizedApp, 'cv');
                    if (! empty($appCv['profile']) && $appCv['profile'][0] === $cvProfile->id()) {
                        abort_unless(CvNavigation::authorized($request, '/cv/'.$profile), 404);
                        return $this->render($resolver->resolveForApplication($authorizedApp), $patterns, $authorizedApp);
                    }
                }
            }
        }
        
        // Otherwise, treat it as a CV profile slug
        return $this->render($resolver->resolve($profile), $patterns);
    }

    public function application(Request $request, string $id, CvProfileContentResolver $resolver, SignatureSecurityPattern $patterns): Response
    {
        $application = Entry::find($id);
        abort_unless($application, 404);
        abort_unless(CvNavigation::authorized($request, '/cv/'.$application->slug()), 404);

        return $this->render($resolver->resolveForApplication($application), $patterns, $application);
    }

    public function applicationPdf(Request $request, string $id, CvPdfRenderer $renderer)
    {
        $application = Entry::find($id);
        abort_unless($application, 404);
        $path = '/cv/'.$application->slug();
        abort_unless(CvNavigation::authorized($request, $path), 404);
        $pdf = $renderer->renderApplication($application);
        if (! CvNavigation::authorized($request, $path)) {
            @unlink($pdf);
            abort(404);
        }

        return response()->file($pdf, ['Content-Type' => 'application/pdf'])->deleteFileAfterSend();
    }

    private function render(array $resolved, SignatureSecurityPattern $patterns, $application = null): Response
    {
        $cv = GlobalSet::find('cv')?->inDefaultSite();
        $patternEnabled = filter_var($cv?->value('signature_pattern_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $signatureEnabled = filter_var($cv?->value('private_cv_signature_enabled', false), FILTER_VALIDATE_BOOLEAN);
        if ($application) {
            $signatureEnabled = (ApplicationData::group($application, 'signature')['mode'] ?? 'none') !== 'none';
            $patternEnabled = $patternEnabled && $signatureEnabled;
        }
        $pattern = $patternEnabled ? $patterns->render(
            'guilloche',
            (string) $cv->value('signature_pattern_color', 'blue'),
            (string) $cv->value('signature_pattern_density', 'balanced'),
        ) : null;

        return response((new View)
            ->template(! $application && $resolved['profile'] ? 'cv/profile' : 'cv/show')
            ->layout('layout')
            ->with([
                'title' => 'Lebenslauf',
                'cv_resolved' => $resolved,
                'profile_title' => $resolved['profile']['title'] ?? null,
                'recipient_name' => $resolved['profile']['recipient'] ?? null,
                'application_year' => $resolved['profile']['year'] ?? null,
                'accent_color' => $resolved['application_accent'] ?? $resolved['profile']['accent'] ?? null,
                'optional_context' => $resolved['profile']['context'] ?? null,
                'cv_signature_security_pattern' => $pattern,
                'cv_signature_public_example' => $cv?->value('signature_public_source', 'font') === 'example',
                'cv_private_signature_enabled' => $signatureEnabled,
                'cv_signature_place' => trim((string) $cv?->value('print_place', '')) ?: (string) ($resolved['public_identity']['location'] ?? ''),
                'cv_signature_date' => $application ? Carbon::parse(ApplicationData::date($application))->locale('de')->translatedFormat('j. F Y') : (trim((string) $cv?->value('print_date', '')) ?: Carbon::now()->locale('de')->translatedFormat('j. F Y')),
            ])
            ->render());
    }
}
