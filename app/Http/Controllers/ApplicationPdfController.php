<?php

namespace App\Http\Controllers;

use App\Signatures\ApplicationSignatureWorkflow;
use App\Signatures\SignatureWorkflowException;
use App\Support\ApplicationCapabilities;
use App\Support\ApplicationPdfException;
use App\Support\ApplicationPdfExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

final class ApplicationPdfController extends Controller
{
    private function application(string $id)
    {
        $entry = Entry::find($id);
        abort_unless($entry && $entry->collectionHandle() === 'applications', 404);

        return $entry;
    }

    public function cp(Request $request, string $id, ApplicationPdfExporter $exporter)
    {
        abort_unless(User::current()?->isSuper(), 403);

        return $this->pdf($exporter, $this->application($id), $request->routeIs('statamic.cp.applications.preview'), $request->routeIs('statamic.cp.applications.export'));
    }

    public function recipient(Request $request, string $id, ApplicationPdfExporter $exporter)
    {
        abort_unless(ApplicationCapabilities::sessionAllows($request, $id), 404);

        $response = $this->pdf($exporter, $this->application($id), recipient: true);
        $this->application($id);
        // A revocation during a slow compile must stop the pending response too.
        abort_unless(ApplicationCapabilities::sessionAllows($request, $id), 404);

        return $response;
    }

    private function pdf(ApplicationPdfExporter $exporter, $entry, bool $preview = false, bool $download = false, bool $recipient = false)
    {
        try {
            $path = $exporter->render($entry, cached: ! $preview);
            if (! $preview) {
                $path = app(ApplicationSignatureWorkflow::class)->delivery($entry, $path, $recipient);
            }
        } catch (ValidationException $exception) {
            $message = ($preview || $download) ? implode("\n", Arr::flatten($exception->errors())) : 'Die Bewerbung ist derzeit nicht verfügbar. Bitte die absendende Person kontaktieren.';

            return response($message, ($preview || $download) ? 422 : 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
        } catch (SignatureWorkflowException $exception) {
            return response($recipient ? 'Die Bewerbung ist derzeit nicht verfügbar.' : $exception->getMessage(), 409);
        } catch (ApplicationPdfException $exception) {
            // Do not report underlying parser/compiler exceptions or source documents.
            return response($exception->getMessage(), 503);
        }
        $response = response()->file($path, ['Content-Type' => 'application/pdf']);
        $response->setContentDisposition($download ? 'attachment' : 'inline', 'bewerbung.pdf');
        if ($preview) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    public function grants(string $id)
    {
        abort_unless(User::current()?->isSuper(), 403);
        $this->application($id);

        return response()->json(['grants' => ApplicationCapabilities::grants($id)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function issueGrant(Request $request, string $id)
    {
        abort_unless(User::current()?->isSuper(), 403);
        $this->application($id);
        $input = Validator::make($request->all(), [
            'label' => ['required', 'string', 'min:1', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ])->validate();
        $issued = ApplicationCapabilities::issue($id, $input['label'], $input['expires_at'] ?? null);

        return response()->json([
            'url' => url('/applications/'.$id).'#application='.$issued['token'],
            'grant' => collect(ApplicationCapabilities::grants($id))->firstWhere('id', $issued['id']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function issueLegacyGrant(string $id)
    {
        abort_unless(User::current()?->isSuper(), 403);
        $this->application($id);
        $issued = ApplicationCapabilities::issue($id, 'Empfängerlink (Legacy)');

        return response()->json(['url' => url('/applications/'.$id).'#application='.$issued['token']])
            ->header('Cache-Control', 'private, no-store');
    }

    public function revokeGrant(string $id, string $grantId)
    {
        abort_unless(User::current()?->isSuper(), 403);
        $this->application($id);
        abort_unless(\Illuminate\Support\Str::isUuid($grantId), 404);
        $revoked = ApplicationCapabilities::revoke($id, $grantId);

        return response()->json(['revoked' => $revoked])
            ->header('Cache-Control', 'private, no-store');
    }

    public function revokeAllGrants(string $id)
    {
        abort_unless(User::current()?->isSuper(), 403);
        $this->application($id);

        return response()->json(['revoked' => ApplicationCapabilities::revokeAll($id)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function landing(Request $request, string $id)
    {
        $this->application($id);

        return response()->view('applications.access', ['id' => $id, 'authorized' => ApplicationCapabilities::sessionAllows($request, $id)]);
    }

    public function exchange(Request $request)
    {
        $origin = $request->header('Origin');
        abort_if(($origin && $origin !== $request->getSchemeAndHttpHost()) || $request->header('Sec-Fetch-Site') === 'cross-site', 403);
        $validator = Validator::make($request->only(['id', 'token']), ['id' => ['required', 'uuid'], 'token' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/']]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Ungültige Zugriffsdaten.'], 422);
        }
        $input = $validator->validated();
        $this->application($input['id']);
        $hash = hash('sha256', $input['token']);
        $grantId = ApplicationCapabilities::grantIdForHash($input['id'], $hash);
        abort_unless($grantId, 404);
        $request->session()->regenerate();
        // A recipient session is scoped to one application at a time. Other grants
        // and normal CP/login session values are not affected.
        $request->session()->forget('application_grants');
        $request->session()->put('application_grants.'.hash('sha256', $input['id']), $grantId);
        ApplicationCapabilities::recordAccess($input['id'], $hash);
        abort_unless(ApplicationCapabilities::sessionAllows($request, $input['id']), 404);

        return response()->json(['authorized' => true]);
    }
}
