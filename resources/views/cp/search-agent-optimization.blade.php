<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Search Agent Optimization · Control Panel</title>
    <style>
        :root { color-scheme: light dark; font: 15px/1.5 system-ui, sans-serif; }
        body { margin: 0; background: #f4f5f7; color: #20242b; }
        main { max-width: 1120px; margin: 0 auto; padding: 36px 24px 64px; }
        a { color: #2558a6; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .top a { text-decoration: none; }
        h1 { margin: 24px 0 4px; font-size: 30px; line-height: 1.2; }
        .muted { color: #68717e; }
        .counts { display: flex; gap: 10px; flex-wrap: wrap; margin: 22px 0; }
        .count, .panel { background: #fff; border: 1px solid #dce0e5; border-radius: 9px; }
        .count { padding: 10px 14px; }
        .count strong { margin-right: 5px; }
        .panel { padding: 18px; }
        .filters, .bulk { display: flex; align-items: end; gap: 12px; flex-wrap: wrap; }
        .filters { margin-bottom: 16px; }
        label { display: grid; gap: 5px; font-weight: 600; }
        input[type="search"], select, button { min-height: 40px; border: 1px solid #c4cbd3; border-radius: 6px; padding: 7px 10px; font: inherit; background: #fff; color: #20242b; }
        button { cursor: pointer; color: #fff; background: #2857a4; border-color: #2857a4; font-weight: 650; }
        .status { margin: 0 0 16px; padding: 10px 12px; border-radius: 6px; background: #e5f5e9; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        th, td { text-align: left; padding: 11px 9px; border-top: 1px solid #e2e5e9; vertical-align: middle; }
        th { color: #68717e; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        td:first-child, th:first-child { width: 36px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 20px; font-size: 12px; font-weight: 700; }
        .indexed { background: #e3f4e9; color: #176036; }
        .noindex { background: #fff0d6; color: #784900; }
        .empty { padding: 24px 8px; text-align: center; color: #68717e; }
        code { overflow-wrap: anywhere; }
        @media (prefers-color-scheme: dark) {
            body { background: #17191d; color: #eef0f3; }
            .count, .panel { background: #22252a; border-color: #3a3e45; }
            input[type="search"], select { background: #17191d; color: #eef0f3; border-color: #555b65; }
            th, td { border-color: #3a3e45; }
            .muted { color: #aab1bc; }
        }
    </style>
</head>
<body>
<main>
    <div class="top"><a href="/cp">← Control Panel</a><span class="muted">Jack Ruder · Website settings</span></div>
    <h1>Search Agent Optimization</h1>
    <p class="muted">Indexierungsstatus veröffentlichter Seiten verwalten. Änderungen wirken auf Meta-Robots und die Sitemap.</p>

    <div class="counts" aria-label="Zusammenfassung">
        <div class="count"><strong>{{ $counts['all'] }}</strong> Seiten gesamt</div>
        <div class="count"><strong>{{ $counts['indexed'] }}</strong> indexierbar</div>
        <div class="count"><strong>{{ $counts['noindex'] }}</strong> auf noindex</div>
    </div>

    <section class="panel">
        @if (session('status')) <p class="status" role="status">{{ session('status') }}</p> @endif
        @if ($errors->any()) <p class="status" role="alert">Bitte markiere mindestens eine Seite und wähle eine gültige Aktion.</p> @endif

        <form class="filters" method="get" action="{{ route('statamic.cp.search-agent-optimization.index') }}">
            <label>Ansicht
                <select name="filter" onchange="this.form.submit()">
                    <option value="all" @selected($filter === 'all')>Alle Seiten ({{ $counts['all'] }})</option>
                    <option value="indexed" @selected($filter === 'indexed')>Indexierbar ({{ $counts['indexed'] }})</option>
                    <option value="noindex" @selected($filter === 'noindex')>noindex ({{ $counts['noindex'] }})</option>
                </select>
            </label>
            <label>Seiten suchen
                <input type="search" name="q" value="{{ $query }}" placeholder="Titel, Sammlung oder URL">
            </label>
            <button type="submit">Filtern</button>
        </form>

        <form method="post" action="{{ route('statamic.cp.search-agent-optimization.update') }}">
            @csrf
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="hidden" name="q" value="{{ $query }}">
            <div class="bulk">
                <label>Ausgewählte Seiten
                    <select name="action" required>
                        <option value="index">Indexierung erlauben</option>
                        <option value="noindex">Auf noindex setzen</option>
                    </select>
                </label>
                <button type="submit">Auswahl aktualisieren</button>
                <span class="muted">Einträge auswählen und gemeinsam ändern.</span>
            </div>

            <table>
                <thead><tr><th><input type="checkbox" aria-label="Alle sichtbaren Seiten auswählen" onclick="document.querySelectorAll('[name=\'items[]\']').forEach((box) => box.checked = this.checked)"></th><th>Seite</th><th>Sammlung</th><th>Status</th><th>URL</th></tr></thead>
                <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td><input type="checkbox" name="items[]" value="{{ $entry['id'] }}" aria-label="{{ $entry['title'] }} auswählen"></td>
                        <td><strong>{{ $entry['title'] }}</strong></td>
                        <td>{{ $entry['collection'] }}</td>
                        <td><span class="badge {{ $entry['noindex'] ? 'noindex' : 'indexed' }}">{{ $entry['noindex'] ? 'noindex' : 'indexierbar' }}</span></td>
                        <td><a href="{{ $entry['url'] }}" target="_blank" rel="noopener"><code>{{ $entry['url'] }}</code></a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Keine Seiten für diese Auswahl gefunden.</td></tr>
                @endforelse
                </tbody>
            </table>
        </form>
    </section>

    <p class="muted">Die `llms.txt`-Dateien und weitere globale SEO-Angaben bearbeitest du unter <a href="/cp/globals">Globals → Search Agent Optimization</a>.</p>
</main>
</body>
</html>
