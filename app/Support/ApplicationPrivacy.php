<?php

namespace App\Support;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Statamic\Contracts\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Events\GlobalVariablesSaving;
use Statamic\Facades\User;

final class ApplicationPrivacy
{
    public static function boot(): void
    {
        // Run before Statamic policies, whose configure-* permissions otherwise bypass checks.
        Gate::before(function ($user, $ability, $arguments) {
            foreach ($arguments as $resource) {
                if (! is_object($resource)) {
                    continue;
                }
                $private = ($resource instanceof Entry && $resource->collectionHandle() === 'applications')
                    || (method_exists($resource, 'handle') && in_array($resource->handle(), ['applications', 'application_documents', 'application_defaults'], true))
                    || (method_exists($resource, 'containerHandle') && $resource->containerHandle() === 'application_documents')
                    || (method_exists($resource, 'container') && $resource->container()?->handle() === 'application_documents');
                if ($private) {
                    if ($ability === 'publish' && ($resource instanceof Entry || (method_exists($resource, 'handle') && $resource->handle() === 'applications'))) {
                        return false;
                    }

                    return User::fromUser($user)->isSuper();
                }
            }

            return null;
        });

        Event::listen(RequestHandled::class, function ($event) {
            $request = $event->request;
            $private = str_contains($request->path(), 'applications') || str_contains($request->path(), 'application_defaults') || str_contains($request->path(), 'application_documents')
                || $request->input('container') === 'application_documents';
            foreach ($request->route()?->parameters() ?? [] as $parameter) {
                if (is_string($parameter) && str_starts_with((string) base64_decode($parameter, true), 'application_documents::')) {
                    $private = true;
                }
            }
            if ($private) {
                $event->response->headers->set('Cache-Control', 'private, no-store');
                $event->response->headers->set('Referrer-Policy', 'no-referrer');
                $event->response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
                $event->response->headers->set('X-Content-Type-Options', 'nosniff');
            }
        });

        Event::listen(GlobalVariablesSaving::class, function ($event) {
            $variables = $event->variables;
            if ($variables->handle() !== 'application_defaults') {
                return;
            }
            foreach (['documents', 'text_components'] as $handle) {
                $value = $variables->get($handle);
                if (is_array($value)) {
                    $variables->set($handle, ApplicationData::encrypt($value));
                } else {
                    ApplicationData::decrypt($value);
                }
            }
            $components = ApplicationData::decrypt($variables->get('text_components'))[0]['items'] ?? [];
            $keys = array_column($components, 'key');
            if (count($keys) !== count(array_unique($keys))) {
                throw ValidationException::withMessages(['text_components' => 'Textbaustein-Schlüssel müssen eindeutig sein.']);
            }
        });

        Event::listen(EntrySaving::class, function (EntrySaving $event) {
            $entry = $event->entry;
            if ($entry->collectionHandle() !== 'applications') {
                return;
            }
            $entry->published(false);
            $entry->data($entry->data()->except('workflow')->all());
            // Slugs are opaque even when the CP auto-generates one from the title.
            $entry->slug($entry->id() ?: (string) Str::uuid());
            foreach (ApplicationData::GROUPS as $handle) {
                $value = $entry->get($handle);
                if (is_array($value)) {
                    $entry->set($handle, ApplicationData::encrypt($value));
                } else {
                    ApplicationData::decrypt($value);
                }
            }
            $tracking = ApplicationData::group($entry, 'tracking');
            $tracking['application_date'] ??= now()->toDateString();
            $entry->set('tracking', ApplicationData::encrypt([$tracking]));
            $recipient = ApplicationData::group($entry, 'recipient');
            if (! empty($recipient['period_start']) && ! empty($recipient['period_end']) && $recipient['period_end'] < $recipient['period_start']) {
                throw ValidationException::withMessages(['recipient.0.period_end' => 'Das Ende muss nach dem Beginn liegen.']);
            }
            $signature = ApplicationData::group($entry, 'signature');
            if (($signature['mode'] ?? 'none') === 'typed' && trim($signature['name'] ?? '') === '') {
                throw ValidationException::withMessages(['signature.0.name' => 'Bitte einen Namen für die getippte Unterschrift angeben.']);
            }
            $unknown = array_diff(array_keys($entry->data()->all()), [...ApplicationData::GROUPS, 'title', 'application_status', 'blueprint', 'updated_by', 'updated_at']);
            if ($unknown) {
                throw ValidationException::withMessages(['application' => 'Unbekannte ungeschützte Bewerbungsfelder.']);
            }
        });
    }
}
