<?php

namespace App\Http\Controllers;

use App\Support\ApplicationData;
use App\Support\PrivateHandwrittenSignature;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;

final class ApplicationHandwrittenSignatureController extends Controller
{
    public function show(Request $request, PrivateHandwrittenSignature $signature)
    {
        $this->authorizeSuperUser();
        if ($request->route('source') === 'example') {
            $signature = PrivateHandwrittenSignature::example();
        }

        return response()->json(['available' => $signature->exists(), 'source' => $signature->editorData()])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, PrivateHandwrittenSignature $signature)
    {
        $this->authorizeSuperUser();
        if ($request->route('source') === 'example') {
            $signature = PrivateHandwrittenSignature::example();
        }
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

    public function destroy(Request $request, PrivateHandwrittenSignature $signature)
    {
        $this->authorizeSuperUser();
        if ($request->route('source') === 'example') {
            $signature = PrivateHandwrittenSignature::example();
        }
        foreach ($request->route('source') === 'example' ? [] : Entry::query()->where('collection', 'applications')->whereStatus('any')->get() as $application) {
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

    public function exampleImage()
    {
        $cv = GlobalSet::find('cv')?->inDefaultSite();
        abort_unless($cv && filter_var($cv->value('signature_pattern_enabled'), FILTER_VALIDATE_BOOLEAN) && $cv->value('signature_public_source') === 'example', 404);
        $png = PrivateHandwrittenSignature::example()->imageBytes();
        abort_if($png === null, 404);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    private function authorizeSuperUser(): void
    {
        abort_unless(User::current()?->isSuper(), 403);
    }
}
