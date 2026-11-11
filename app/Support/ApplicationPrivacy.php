<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Statamic\Contracts\Entries\Entry;
use Statamic\Events\AssetDeleting;
use Statamic\Events\AssetSaved;
use Statamic\Events\EntrySaving;
use Statamic\Events\GlobalVariablesSaving;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;

final class ApplicationPrivacy
{
    public static function boot(): void
    {
        Event::listen(AssetDeleting::class, function (AssetDeleting $event): void {
            $asset = $event->asset;
            if ($asset->containerHandle() !== 'application_documents') {
                return;
            }
            $path = ltrim($asset->path(), '/');
            $entries = Entries::query()->where('collection', 'applications')->whereStatus('any')->get();
            foreach ($entries as $entry) {
                if (self::referencesDocument($entry->get('documents'), $path)) {
                    throw ValidationException::withMessages(['file' => 'Dieses PDF ist einer Bewerbung zugeordnet. Entfernen Sie die Anlage aus der Bewerbung, speichern Sie sie und löschen Sie das PDF danach.']);
                }
            }
            $defaults = GlobalSet::find('application_defaults')?->inDefaultSite();
            if ($defaults && self::referencesDocument($defaults->get('documents'), $path)) {
                throw ValidationException::withMessages(['file' => 'Dieses PDF ist als Standardanlage ausgewählt. Entfernen Sie es aus den Bewerbungsstandards, bevor Sie es löschen.']);
            }
        });
        // Statamic updates ordinary asset fields when an asset is renamed or moved,
        // but application references are encrypted and therefore need this private
        // path update before a generated export can resolve its selected documents.
        Event::listen(AssetSaved::class, function (AssetSaved $event): void {
            $asset = $event->asset;
            if ($asset->containerHandle() !== 'application_documents') {
                return;
            }

            $oldPath = $asset->getOriginal('path');
            $newPath = $asset->path();
            if (! $oldPath || $oldPath === $newPath) {
                return;
            }

            foreach (Entries::query()->where('collection', 'applications')->whereStatus('any')->get() as $entry) {
                try {
                    $groups = ApplicationData::decrypt($entry->get('documents'));
                } catch (DecryptException) {
                    // Already-unreadable records cannot be made usable by renaming an asset.
                    continue;
                }
                if (self::replaceDocumentPaths($groups, $oldPath, $newPath)) {
                    $entry->set('documents', ApplicationData::encrypt($groups));
                    $entry->save();
                }
            }

            $defaults = GlobalSet::find('application_defaults')?->inDefaultSite();
            if (! $defaults) {
                return;
            }
            try {
                $groups = ApplicationData::decrypt($defaults->get('documents'));
            } catch (DecryptException) {
                return;
            }
            if (self::replaceDocumentPaths($groups, $oldPath, $newPath)) {
                $defaults->set('documents', ApplicationData::encrypt($groups));
                $defaults->save();
            }
        });
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
            $entry->data($entry->data()->except(['workflow', 'electronic_signatures'])->all());
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
            $signatureMode = $signature['mode'] ?? 'none';
            if (! in_array($signatureMode, ['none', 'typed', 'handwritten'], true)) {
                throw ValidationException::withMessages(['signature.0.mode' => 'Bitte eine gültige Signaturart auswählen.']);
            }
            if ($signatureMode === 'typed' && trim($signature['name'] ?? '') === '') {
                throw ValidationException::withMessages(['signature.0.name' => 'Bitte einen Namen für die getippte Unterschrift angeben.']);
            }
            if ($signatureMode === 'handwritten') {
                try {
                    $signatureAvailable = app(PrivateHandwrittenSignature::class)->available();
                } catch (\Throwable) {
                    $signatureAvailable = false;
                }
                if (! $signatureAvailable) {
                    throw ValidationException::withMessages(['signature.0.mode' => 'Speichern Sie zuerst eine private Handschrift unter Globals → Bewerbungsvorgaben → Handschrift.']);
                }
                if (! is_numeric($signature['width_mm'] ?? 46) || $signature['width_mm'] < 30 || $signature['width_mm'] > 75) {
                    throw ValidationException::withMessages(['signature.0.width_mm' => 'Die Signaturbreite muss zwischen 30 und 75 mm liegen.']);
                }
                if (! is_numeric($signature['baseline_mm'] ?? 0) || $signature['baseline_mm'] < -3 || $signature['baseline_mm'] > 3) {
                    throw ValidationException::withMessages(['signature.0.baseline_mm' => 'Der Grundlinien-Versatz muss zwischen -3 und 3 mm liegen.']);
                }
            }
            $unknown = array_diff(array_keys($entry->data()->all()), [...ApplicationData::GROUPS, 'title', 'application_status', 'blueprint', 'updated_by', 'updated_at']);
            if ($unknown) {
                throw ValidationException::withMessages(['application' => 'Unbekannte ungeschützte Bewerbungsfelder.']);
            }
        });
    }

    private static function referencesDocument(mixed $encrypted, string $path): bool
    {
        foreach (ApplicationData::decrypt($encrypted)[0]['items'] ?? [] as $item) {
            $reference = $item['file'] ?? null;
            if (is_array($reference)) {
                $reference = reset($reference);
            }
            if (! is_string($reference) || $reference === '') {
                continue;
            }
            $reference = ltrim((string) (str_contains($reference, '::') ? substr($reference, strrpos($reference, '::') + 2) : $reference), '/');
            if (hash_equals($path, $reference)) {
                return true;
            }
        }

        return false;
    }

    private static function replaceDocumentPaths(array &$groups, string $oldPath, string $newPath): bool
    {
        $changed = false;
        foreach ($groups as &$group) {
            if (! is_array($group) || ! is_array($group['items'] ?? null)) {
                continue;
            }
            foreach ($group['items'] as &$item) {
                if (! is_array($item)) {
                    continue;
                }
                $reference = $item['file'] ?? null;
                $multiple = is_array($reference);
                $references = $multiple ? $reference : [$reference];
                $itemChanged = false;

                foreach ($references as &$value) {
                    if (! is_string($value) || $value === '') {
                        continue;
                    }
                    $prefix = '';
                    $path = $value;
                    if (str_contains($value, '::')) {
                        [$container, $path] = explode('::', $value, 2);
                        if ($container !== 'application_documents') {
                            continue;
                        }
                        $prefix = $container.'::';
                    }
                    $leadingSlash = str_starts_with($path, '/') ? '/' : '';
                    if (hash_equals($oldPath, ltrim($path, '/'))) {
                        $value = $prefix.$leadingSlash.$newPath;
                        $changed = true;
                        $itemChanged = true;
                    }
                }
                unset($value);

                if ($itemChanged) {
                    $item['file'] = $multiple ? $references : $references[0];
                }
            }
            unset($item);
        }
        unset($group);

        return $changed;
    }
}
