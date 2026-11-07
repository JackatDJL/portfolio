<?php

namespace App\Http\Controllers;

use App\Support\ApplicationData;
use App\Support\CvPdfRenderer;
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
}
