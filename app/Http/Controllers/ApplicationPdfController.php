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

    public function issue(string $id)
    {
        abort_unless(User::current()?->isSuper(), 403);
        $this->application($id);
        $token = ApplicationCapabilities::issue($id);

        return response()->json(['url' => url('/applications/'.$id).'#application='.$token]);
    }

    public function revoke(string $id)
    {
        abort_unless(User::current()?->isSuper(), 403);
        $this->application($id);
        ApplicationCapabilities::revoke($id);

        return response()->json(['revoked' => true]);
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
        abort_unless(ApplicationCapabilities::allowsHash($input['id'], $hash), 404);
        $request->session()->regenerate();
        $request->session()->put('application_grants.'.hash('sha256', $input['id']), $hash);

        return response()->json(['authorized' => true]);
    }
}
