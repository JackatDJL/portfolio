<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Statamic\Facades\Entry;
use Statamic\View\View;
use Throwable;

final class ContactController extends Controller
{
    private const TOPICS = [
        'projekt' => 'Projektanfrage',
        'sponsoring' => 'Sponsoring',
        'politik' => 'Politik',
        'allgemein' => 'Allgemeiner Kontakt',
    ];

    public function show(Request $request): Response
    {
        $topic = $request->query('thema');

        return $this->render($request, ['topic' => is_string($topic) && isset(self::TOPICS[$topic]) ? $topic : 'allgemein']);
    }

    public function send(Request $request): Response|RedirectResponse
    {
        $this->publishedEntry();
        if (! $this->configured()) {
            return $this->render($request, [], ['Der Nachrichtenversand ist derzeit nicht eingerichtet. Es wurde nichts versendet.'], 503);
        }

        $validator = Validator::make($request->only(['topic', 'name', 'email', 'message']), [
            'topic' => ['required', 'string', Rule::in(array_keys(self::TOPICS))],
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
            'message' => ['required', 'string', 'min:10', 'max:6000'],
        ], [
            'topic.*' => 'Bitte wähle ein Thema aus.',
            'name.*' => 'Bitte gib deinen Namen mit höchstens 120 Zeichen an.',
            'email.*' => 'Bitte gib eine gültige E-Mail-Adresse an.',
            'message.*' => 'Bitte schreibe eine Nachricht mit 10 bis 6000 Zeichen.',
        ]);

        // Render errors directly: no message, address or name is flashed to the session.
        if ($validator->fails()) {
            return $this->render($request, $this->safeInput($request), $validator->errors()->all(), 422);
        }

        if (! $this->validTiming($request) || ! in_array($request->input('website'), [null, ''], true)) {
            return $this->render($request, $this->safeInput($request), ['Bitte prüfe das Formular und versuche es erneut.'], 422);
        }

        $data = $validator->validated();
        try {
            Mail::mailer(config('contact.mailer'))->to(config('contact.recipient'))->send(
                new ContactMessage(self::TOPICS[$data['topic']], $data['name'], $data['email'], $data['message'])
            );
        } catch (Throwable) {
            // Transport exceptions may contain addresses or message content. Do not log them.
            return $this->render($request, $this->safeInput($request), ['Der Versand ist fehlgeschlagen. Bitte versuche es später erneut.'], 503);
        }

        return redirect('/kontakt')->with('contact_sent', true);
    }

    private function publishedEntry()
    {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'kontakt')->first();
        abort_unless($entry && $entry->published(), 404);

        return $entry;
    }

    private function configured(): bool
    {
        $mailer = config('contact.mailer');
        $transport = is_string($mailer) ? config("mail.mailers.{$mailer}.transport") : null;

        // Explicit real transport only. Log/array/failover could disclose or silently discard messages.
        return config('contact.enabled') === true
            && filter_var(config('contact.recipient'), FILTER_VALIDATE_EMAIL) !== false
            && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false
            && in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'mailgun', 'postmark', 'resend'], true);
    }

    private function validTiming(Request $request): bool
    {
        if (! is_string($request->input('form_token'))) {
            return false;
        }

        try {
            $value = json_decode(Crypt::decryptString($request->input('form_token')), true, flags: JSON_THROW_ON_ERROR);
            $age = time() - $value['time'];

            return $age >= 2 && $age <= 7200
                && hash_equals(hash('sha256', $request->session()->token()), $value['session']);
        } catch (Throwable) {
            return false;
        }
    }

    private function safeInput(Request $request): array
    {
        $values = [];
        foreach (['topic' => 20, 'name' => 120, 'email' => 254, 'message' => 6000] as $field => $limit) {
            $value = $request->input($field);
            $values[$field] = is_string($value) ? mb_substr($value, 0, $limit) : '';
        }

        return $values;
    }

    private function render(Request $request, array $input = [], array $errors = [], int $status = 200): Response
    {
        $entry = $this->publishedEntry();
        $topics = [];
        foreach (self::TOPICS as $value => $label) {
            $topics[] = ['value' => $value, 'label' => $label, 'selected' => ($input['topic'] ?? 'allgemein') === $value];
        }
        $token = Crypt::encryptString(json_encode(['time' => time(), 'session' => hash('sha256', $request->session()->token())], JSON_THROW_ON_ERROR));

        return response((new View)->template('contact/index')->layout('layout')->cascadeContent($entry)->with([
            'noindex' => true,
            'title' => $entry->value('title'),
            'contact_intro' => $entry->value('intro'),
            'contact_privacy' => $entry->value('privacy_note'),
            'contact_enabled' => $this->configured(),
            'contact_sent' => $request->session()->get('contact_sent', false),
            'contact_errors' => $errors,
            'contact_has_errors' => $errors !== [],
            'contact_input' => $input,
            'contact_topics' => $topics,
            'contact_token' => $token,
        ])->render(), $status)->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, follow',
        ]);
    }
}
