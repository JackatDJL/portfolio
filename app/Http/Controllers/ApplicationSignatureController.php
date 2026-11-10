<?php

namespace App\Http\Controllers;

use App\Signatures\ApplicationSignatureWorkflow;
use App\Signatures\SignatureWorkflowException;
use App\Support\ApplicationPdfException;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

final class ApplicationSignatureController extends Controller
{
    public function handle(Request $request, string $id, string $action, ApplicationSignatureWorkflow $workflow)
    {
        abort_unless(User::current()?->isSuper(), 403);
        $entry = Entry::find($id);
        abort_unless($entry && $entry->collectionHandle() === 'applications', 404);
        $actor = User::current()->id();
        try {
            if ($action === 'status') {
                return response()->json($workflow->status($id));
            }
            if ($action === 'prepare') {
                return response()->json(['id' => $workflow->prepare($entry, $actor)->id]);
            }
            $input = $request->validate(['version' => ['nullable', 'uuid'], 'sha256' => ['nullable', 'regex:/^[a-f0-9]{64}$/'], 'authorized' => $action === 'download' ? ['nullable'] : ['required', 'accepted']]);
            $version = isset($input['version']) ? $workflow->version($id, $input['version']) : null;
            if ($action === 'unsigned') {
                $workflow->select($id, null, $actor);

                return response()->json(['mode' => 'unsigned']);
            }
            abort_unless($version, 422);
            if ($action === 'download') {
                $response = response()->file($workflow->path($version, $request->boolean('signed')), ['Content-Type' => 'application/pdf']);
                $response->setContentDisposition('attachment', 'bewerbung-'.($request->boolean('signed') ? 'signiert' : 'unsigniert').'.pdf');

                return $response;
            }
            if ($action === 'import') {
                $request->validate(['pdf' => ['required', 'file', 'mimetypes:application/pdf', 'max:102400'], 'sha256' => ['required', 'regex:/^[a-f0-9]{64}$/']]);
                $workflow->import($version, $request->file('pdf')->getRealPath(), $input['sha256']);
            } elseif ($action === 'sandbox') {
                $request->validate(['sha256' => ['required', 'regex:/^[a-f0-9]{64}$/']]);
                $workflow->sandbox($version, $input['sha256']);
            } elseif ($action === 'activate') {
                $workflow->select($id, $version, $actor);
            } else {
                abort(404);
            }

            return response()->json(['ok' => true]);
        } catch (ApplicationPdfException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        } catch (SignatureWorkflowException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
    }
}
