<?php

namespace App\Http\Controllers;

use App\Support\CvProfileContentResolver;
use Illuminate\Http\Response;
use Statamic\View\View;

final class CvPageController extends Controller
{
    public function show(CvProfileContentResolver $resolver): Response
    {
        return $this->render($resolver->resolve());
    }

    public function profile(string $profile, CvProfileContentResolver $resolver): Response
    {
        return $this->render($resolver->resolve($profile));
    }

    private function render(array $resolved): Response
    {
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
            ])
            ->render());
    }
}
