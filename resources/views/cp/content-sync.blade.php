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
    <section><h2 class="mb-2 text-lg font-semibold">Letzte Logs</h2><pre class="max-h-[60vh] overflow-auto whitespace-pre-wrap rounded-lg bg-gray-950 p-4 text-sm text-gray-100" data-sync-logs aria-live="polite"></pre></section>
</div>
<script>
(() => {
    const root = document.querySelector('[data-content-sync]');
    if (!root || root.dataset.bound) return;
    root.dataset.bound = 'true';
    const status = root.querySelector('[data-sync-state]');
    const logs = root.querySelector('[data-sync-logs]');
    const button = root.querySelector('[data-sync-start]');
    let nextRun;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const refresh = async () => {
        const response = await fetch(root.dataset.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!response.ok) throw new Error('Status konnte nicht geladen werden.');
        const data = await response.json();
        status.textContent = data.running ? `Lauf aktiv seit ${new Date(data.started_at).toLocaleString('de-DE')}` : 'Kein Lauf aktiv';
        logs.textContent = data.logs || 'Noch keine Läufe protokolliert.';
        logs.scrollTop = logs.scrollHeight;
        nextRun = new Date(data.next_run_at);
        button.disabled = data.running;
    };
    const tick = () => {
        const seconds = Math.max(0, Math.ceil((nextRun - Date.now()) / 1000));
        root.querySelector('[data-sync-countdown]').textContent = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
    };
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const response = await fetch(root.dataset.startUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            if (!response.ok && response.status !== 409) throw new Error('Manueller Lauf konnte nicht gestartet werden.');
            await refresh();
        } catch (error) { status.textContent = error.message; button.disabled = false; }
    });
    refresh().catch(error => status.textContent = error.message);
    setInterval(() => { refresh().catch(() => {}); }, 2000);
    setInterval(tick, 250);
})();
</script>
@endsection
