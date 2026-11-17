<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Content Sync · Control Panel</title>
    <style>
        :root { color-scheme: light dark; font: 15px/1.5 system-ui, sans-serif; }
        body { margin: 0; background: #f4f5f7; color: #20242b; }
        main { max-width: 1040px; margin: 0 auto; padding: 36px 24px 64px; }
        a { color: #2558a6; }
        .sync-top { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .sync-top a { text-decoration: none; }
        h1 { margin: 24px 0 4px; font-size: clamp(1.8rem, 4vw, 2.2rem); line-height: 1.2; }
        h2 { margin: 0; font-size: 1.1rem; }
        .sync-muted { color: #68717e; }
        .sync-grid { display: grid; gap: 18px; margin-top: 24px; }
        .sync-panel { min-width: 0; padding: 20px; border: 1px solid #dce0e5; border-radius: 10px; background: #fff; }
        .sync-panel-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .sync-panel > p { max-width: 72ch; }
        .sync-status { display: inline-flex; align-items: center; gap: 8px; padding: 4px 10px; border-radius: 999px; background: #e8ebef; font-size: .8rem; font-weight: 700; }
        .sync-status::before { width: 8px; height: 8px; border-radius: 50%; background: #7d8793; content: ''; }
        .sync-status[data-status="running"] { background: #fff0d6; color: #784900; }
        .sync-status[data-status="running"]::before { background: #c17b00; }
        .sync-status[data-status="completed"] { background: #e3f4e9; color: #176036; }
        .sync-status[data-status="completed"]::before { background: #21864b; }
        .sync-status[data-status="failed"], .sync-status[data-status="interrupted"] { background: #fde7e7; color: #8b2222; }
        .sync-status[data-status="failed"]::before, .sync-status[data-status="interrupted"]::before { background: #c53838; }
        .sync-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 24px; margin: 20px 0; }
        .sync-facts div { min-width: 0; }
        .sync-facts dt { color: #68717e; font-size: .8rem; }
        .sync-facts dd { margin: 3px 0 0; font-weight: 600; overflow-wrap: anywhere; }
        button { min-height: 42px; padding: 8px 14px; border: 1px solid #2857a4; border-radius: 6px; background: #2857a4; color: #fff; font: inherit; font-weight: 650; cursor: pointer; }
        button:disabled { opacity: .55; cursor: wait; }
        .sync-message { min-height: 1.5em; margin: 10px 0 0; color: #68717e; }
        .sync-log { max-height: 26rem; margin: 16px 0 0; padding: 14px; overflow: auto; border: 1px solid #dce0e5; border-radius: 6px; background: #f8f9fa; color: #20242b; font: .85rem/1.55 ui-monospace, SFMono-Regular, Consolas, monospace; white-space: pre-wrap; overflow-wrap: anywhere; }
        @media (max-width: 38rem) { main { padding: 24px 16px 48px; } .sync-panel { padding: 16px; } .sync-facts { grid-template-columns: 1fr; gap: 12px; } }
        @media (prefers-color-scheme: dark) {
            body { background: #17191d; color: #eef0f3; }
            a { color: #9fc2ff; }
            .sync-panel { background: #22252a; border-color: #3a3e45; }
            .sync-muted, .sync-message, .sync-facts dt { color: #aab1bc; }
            .sync-status { background: #34383f; color: #eef0f3; }
            .sync-status[data-status="running"] { background: #493717; color: #f3cf82; }
            .sync-status[data-status="completed"] { background: #173b29; color: #a7e1bd; }
            .sync-status[data-status="failed"], .sync-status[data-status="interrupted"] { background: #4a2424; color: #ffc1c1; }
            .sync-log { background: #17191d; border-color: #3a3e45; color: #eef0f3; }
        }
    </style>
</head>
<body>
<main data-content-sync
      data-status-url="{{ route('statamic.cp.content-sync.status') }}"
      data-run-url="{{ route('statamic.cp.content-sync.run') }}"
      data-next-run="{{ $sync['next_run_at'] }}">
    <div class="sync-top"><a href="/cp">← Control Panel</a><span class="sync-muted">Werkzeuge</span></div>
    <h1>Content Sync</h1>
    <p class="sync-muted">Statamic-Inhalte werden alle fünf Minuten mit <code>main</code> und <code>content-sync</code> abgeglichen.</p>

    <div class="sync-grid">
        <section class="sync-panel" aria-labelledby="sync-run-heading">
            <div class="sync-panel-heading">
                <h2 id="sync-run-heading">Automatischer Lauf</h2>
                <span class="sync-status" data-sync-status data-status="{{ $sync['run']['status'] ?? 'idle' }}" role="status">
                    @switch($sync['run']['status'] ?? 'idle')
                        @case('running') Läuft @break
                        @case('completed') Abgeschlossen @break
                        @case('failed') Fehlgeschlagen @break
                        @case('interrupted') Unterbrochen @break
                        @default Noch kein Lauf erfasst
                    @endswitch
                </span>
            </div>
            <dl class="sync-facts">
                <div><dt>Nächster geplanter Lauf</dt><dd><time data-sync-countdown datetime="{{ $sync['next_run_at'] }}">wird berechnet …</time></dd></div>
                <div><dt>Letzter Start</dt><dd data-sync-started>{{ $sync['run']['started_at'] ?? 'Noch kein Lauf' }}</dd></div>
                <div><dt>Ausgelöst durch</dt><dd data-sync-source>{{ $sync['run']['source'] ?? '–' }}</dd></div>
                <div><dt>Beendet</dt><dd data-sync-finished>{{ $sync['run']['finished_at'] ?? '–' }}</dd></div>
            </dl>
            <form method="post" action="{{ route('statamic.cp.content-sync.run') }}" data-sync-form>
                @csrf
                <button type="submit" data-sync-button>Jetzt synchronisieren</button>
            </form>
            <p class="sync-message" data-sync-message aria-live="polite"></p>
        </section>

        <section class="sync-panel" aria-labelledby="sync-log-heading">
            <div class="sync-panel-heading">
                <h2 id="sync-log-heading">Protokoll des letzten Laufs</h2>
                <span class="sync-muted" data-sync-updated>Wird live aktualisiert</span>
            </div>
            <pre class="sync-log" data-sync-log role="log" aria-live="polite" aria-relevant="additions text">@forelse (($sync['run']['events'] ?? []) as $event){{ $event['at'] }} · {{ $event['message'] }}
@empty
Noch kein Sync-Lauf protokolliert.
@endforelse</pre>
        </section>
    </div>
</main>
<script src="{{ asset('/cp-content-sync.js') }}?v={{ hash_file('sha256', public_path('cp-content-sync.js')) }}" defer></script>
</body>
</html>
