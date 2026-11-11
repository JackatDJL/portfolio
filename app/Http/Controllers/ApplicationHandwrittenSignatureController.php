<?php

namespace App\Http\Controllers;

use App\Support\ApplicationData;
use App\Support\PrivateHandwrittenSignature;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

final class ApplicationHandwrittenSignatureController extends Controller
{
    public function show(PrivateHandwrittenSignature $signature)
    {
        $this->authorizeSuperUser();

        return response()->json(['available' => $signature->exists(), 'source' => $signature->editorData()])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, PrivateHandwrittenSignature $signature)
    {
        $this->authorizeSuperUser();
        $data = $request->validate([
            'image' => ['required', 'string', 'max:2800000'],
            'strokes' => ['sometimes', 'array', 'max:200'],
        ]);
        try {
            $signature->save($data['image'], $data['strokes'] ?? []);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['image' => $exception->getMessage()]);
        }

        return response()->json(['saved' => true])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function destroy(PrivateHandwrittenSignature $signature)
    {
        $this->authorizeSuperUser();
        foreach (Entry::query()->where('collection', 'applications')->whereStatus('any')->get() as $application) {
            try {
                $settings = ApplicationData::group($application, 'signature');
            } catch (\Throwable) {
                // An unreadable record may still depend on this shared source.
                return response()->json(['message' => 'Die Signatur wird möglicherweise von einer Bewerbung verwendet. Entschlüsseln und prüfen Sie die Bewerbungsdaten zuerst.'], 409)
                    ->header('Cache-Control', 'private, no-store');
            }
            if (($settings['mode'] ?? 'none') === 'handwritten') {
                return response()->json(['message' => 'Diese Signatur wird in einer Bewerbung verwendet. Deaktivieren Sie sie dort, bevor Sie die Vorlage löschen.'], 409)
                    ->header('Cache-Control', 'private, no-store');
            }
        }
        try {
            $signature->delete();
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['signature' => $exception->getMessage()]);
        }

        return response()->json(['deleted' => true])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    private function authorizeSuperUser(): void
    {
        abort_unless(User::current()?->isSuper(), 403);
    }
}
