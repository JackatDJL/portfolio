<?php

namespace App\Http\Controllers;

use App\Support\ApplicationData;
use App\Support\CvPdfRenderer;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

final class ApplicationController extends Controller
{
    private function application(string $id)
    {
        abort_unless(User::current()?->isSuper(), 403);
        $entry = Entry::find($id);
        abort_unless($entry && $entry->collectionHandle() === 'applications', 404);

        return $entry;
    }

    public function resetDate(string $id)
    {
        $entry = $this->application($id);
        $tracking = ApplicationData::group($entry, 'tracking');
        $tracking['application_date'] = now()->toDateString();
        $tracking['date_override'] = null;
        $entry->set('tracking', ApplicationData::encrypt([$tracking]));
        $entry->save();

        return response()->json(['date' => $tracking['application_date']])->header('Cache-Control', 'private, no-store');
    }

    public function cv(string $id, CvPdfRenderer $renderer)
    {
        $file = $renderer->renderApplication($this->application($id));

        return response()->download($file, 'lebenslauf.pdf', [
            'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    public function summary(string $id)
    {
        $entry = $this->application($id);
        $recipient = ApplicationData::group($entry, 'recipient');
        $tracking = ApplicationData::group($entry, 'tracking');
        $latest = collect($tracking['correspondence'] ?? [])
            ->filter(fn ($item) => is_array($item) && ! empty($item['date']))
            ->sortByDesc('date')
            ->first();

        return response()->json([
            'status' => (string) $entry->value('application_status', 'draft'),
            'organisation' => (string) ($recipient['organisation'] ?? ''),
            'position' => (string) ($recipient['position'] ?? ''),
            'notes' => (string) ($tracking['notes'] ?? ''),
            'latest_correspondence' => $latest ? [
                'date' => (string) $latest['date'],
                'direction' => (string) ($latest['direction'] ?? ''),
                'summary' => (string) ($latest['summary'] ?? ''),
            ] : null,
            'follow_up_date' => ($tracking['follow_up_status'] ?? null) === 'planned'
                ? ($tracking['follow_up_date'] ?? null)
                : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function cvProfile(Request $request, string $id)
    {
        $application = $this->application($id);
        $settings = ApplicationData::group($application, 'cv');
        $profileId = $request->has('profile') ? $request->query('profile') : ($settings['profile'] ?? null);
        if (is_array($profileId)) {
            $profileId = reset($profileId);
        }
        if ($profileId !== null && (! is_string($profileId) || strlen($profileId) > 255)) {
            abort(422);
        }

        $profile = $profileId ? Entry::find($profileId) : null;
        abort_if($profileId && (! $profile || $profile->collectionHandle() !== 'cv_profiles'), 404);

        return response()->json([
            'selected' => $profile !== null,
            'title' => $profile ? (string) $profile->value('title', '') : null,
            'slug' => $profile ? (string) $profile->slug() : null,
            'edit_url' => $profile?->editUrl(),
            'public_url' => ($profile && $profile->published()) ? $profile->url() : null,
            'public' => (bool) ($profile && $profile->published() && $profile->url()),
            'cv_pdf_url' => route('statamic.cp.applications.cv', ['id' => $application->id()]),
        ])->header('Cache-Control', 'private, no-store');
    }
}
