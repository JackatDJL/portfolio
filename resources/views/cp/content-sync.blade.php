@extends('statamic::layout')

@section('title', 'Content Sync')

@section('content')
<div class="max-w-5xl mx-auto p-6" data-content-sync data-status-url="{{ route('statamic.cp.content-sync.status') }}" data-start-url="{{ route('statamic.cp.content-sync.start') }}">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Content Sync</h1><p class="mt-1 text-gray-500">Abgleich lokaler Statamic-Inhalte mit Git</p></div>
        <button type="button" class="btn-primary" data-sync-start>Jetzt synchronisieren</button>
    </header>
    <section class="mb-6 rounded-lg border border-gray-300 p-4 dark:border-gray-700">
        <p class="font-medium" data-sync-state>Wird geladen …</p>
        <p class="mt-1 text-sm text-gray-500">Nächster regulärer Lauf in <span data-sync-countdown>–</span></p>
    </section>
    <section><h2 class="mb-2 text-lg font-semibold">Letzte Logs</h2><pre class="max-h-[60vh] overflow-auto whitespace-pre-wrap rounded-lg bg-gray-950 p-4 text-sm text-gray-200" data-sync-logs aria-live="polite"></pre></section>
</div>
@endsection
