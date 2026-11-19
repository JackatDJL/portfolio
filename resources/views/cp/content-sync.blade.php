@extends('statamic::layout')

@section('title', 'Content Sync')

@section('content')
<div class="app-content-sync max-w-5xl mx-auto p-4 md:p-6"
    data-content-sync
    data-status-url="{{ route('statamic.cp.content-sync.status') }}"
    data-start-url="{{ route('statamic.cp.content-sync.start') }}"
    data-conflicts-url="{{ route('statamic.cp.content-sync.conflicts') }}"
    data-resolve-url="{{ route('statamic.cp.content-sync.conflicts.resolve') }}">
    <header class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold">Content Sync</h1>
            <p class="mt-1 max-w-2xl text-gray-500">Statamic-Inhalte sicher mit dem Inhalts-Repository abgleichen.</p>
        </div>
        <button type="button" class="btn app-content-sync-primary" data-sync-start>Jetzt synchronisieren</button>
    </header>

    <section class="app-content-sync-status mb-6 rounded-lg border p-4" aria-live="polite">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="font-medium" data-sync-state>Wird geladen …</p>
                <p class="mt-1 text-sm text-gray-500">Nächster regulärer Lauf in <span data-sync-countdown>–</span></p>
            </div>
            <button type="button" class="btn" data-sync-conflicts-open hidden>Konflikte prüfen</button>
        </div>
    </section>

    <section class="mb-6" data-sync-conflicts hidden>
        <div class="app-content-sync-section-heading">
            <div>
                <h2 class="text-lg font-semibold">Konflikte im Kandidaten</h2>
                <p class="mt-1 text-sm text-gray-500">Jede Änderung bleibt im isolierten Kandidaten, bis alle Abschnitte geprüft und validiert sind.</p>
            </div>
            <button type="button" class="btn" data-sync-conflicts-reload>Neu laden</button>
        </div>
        <p class="app-content-sync-message" role="status" data-sync-conflict-message hidden></p>
        <div class="grid gap-4" data-sync-conflict-list></div>
        <div class="mt-4 flex flex-col items-start gap-3 sm:flex-row sm:items-center">
            <button type="button" class="btn app-content-sync-primary" data-sync-resolve disabled>Validieren und fortsetzen</button>
            <p class="text-sm text-gray-500">Es gibt keine globale Auswahl: Entscheide für jeden Abschnitt einzeln.</p>
        </div>
    </section>

    <section>
        <div class="app-content-sync-section-heading">
            <div>
                <h2 class="text-lg font-semibold">Letzte Logs</h2>
                <p class="mt-1 text-sm text-gray-500">Aktualisiert während eines laufenden Abgleichs.</p>
            </div>
        </div>
        <pre class="app-content-sync-log overflow-auto whitespace-pre-wrap rounded-lg p-4 text-sm text-gray-200" data-sync-logs aria-live="polite"></pre>
    </section>
</div>
@endsection
