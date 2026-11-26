@extends('statamic::layout')

@section('title', 'PDF Compiler')

@section('content')
<div class="app-pdf-compiler max-w-5xl mx-auto p-4 md:p-6"
    data-pdf-compiler
    data-status-url="{{ route('statamic.cp.pdf-compiler.status') }}"
    data-recompile-url="{{ route('statamic.cp.pdf-compiler.recompile') }}"
    data-clear-url="{{ route('statamic.cp.pdf-compiler.clear-cache') }}">
    <header class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold">PDF Compiler</h1>
            <p class="mt-1 max-w-2xl text-gray-500">Kompilierungen prüfen, den öffentlichen Lebenslauf neu aufbauen und private PDF-Caches verwalten.</p>
        </div>
        <button type="button" class="btn app-pdf-compiler-primary" data-pdf-recompile>Öffentlichen Lebenslauf neu kompilieren</button>
    </header>

    <p class="app-pdf-compiler-message mb-5" role="status" aria-live="polite" data-pdf-message hidden></p>

    <section class="app-pdf-compiler-status mb-6 rounded-lg border p-4" aria-live="polite">
        <h2 class="text-lg font-semibold">PDF-Caches</h2>
        <p class="mt-1 text-sm text-gray-500" data-pdf-running>Wird geladen …</p>
        <dl class="app-pdf-compiler-cache-grid mt-4" data-pdf-caches></dl>
        <div class="mt-4">
            <button type="button" class="btn" data-pdf-clear>PDF-Caches leeren</button>
            <p class="mt-2 text-sm text-gray-500">Entfernt zwischengespeicherte öffentliche Lebensläufe und Bewerbungs-PDFs. Nutzdaten bleiben erhalten.</p>
        </div>
    </section>

    <section>
        <div class="app-pdf-compiler-heading">
            <div>
                <h2 class="text-lg font-semibold">Compiler-Logs</h2>
                <p class="mt-1 text-sm text-gray-500">Aktualisiert während eines laufenden Auftrags. Angezeigt werden nur technische Diagnosen ohne Dokumentinhalte.</p>
            </div>
            <button type="button" class="btn" data-pdf-refresh>Jetzt aktualisieren</button>
        </div>
        <pre class="app-pdf-compiler-log overflow-auto whitespace-pre-wrap rounded-lg p-4 text-sm" data-pdf-logs aria-live="polite">Wird geladen …</pre>
    </section>
</div>
@endsection
