<!doctype html>
<html lang="de" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Mediennachweise · Vorschau</title>
    <style>
        :root {
            color-scheme: light dark;
            --page: #fbfefa;
            --surface: #f3f7f2;
            --surface-strong: #e9f0e8;
            --text: #060f03;
            --muted: #474c46;
            --border: #ccd7ce;
            --accent: #5adbbd;
            --font: "Fira Sans", "Geist", system-ui, sans-serif;
            background: var(--page);
            color: var(--text);
            font-family: var(--font);
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--page); color: var(--text); }
        a { color: inherit; }
        .preview-header { border-bottom: 1px solid var(--border); background: var(--page); }
        .preview-header__inner { width: min(100%, 96rem); margin: auto; padding: 1rem clamp(1.25rem, 4vw, 3rem); display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
        .preview-brand { font-size: 1.25rem; font-weight: 800; text-decoration: none; letter-spacing: -.04em; }
        .preview-tag { border: 1px solid var(--border); border-radius: 999px; padding: .35rem .75rem; color: var(--muted); font-size: .75rem; font-weight: 700; }
        main { width: min(100%, 96rem); margin: auto; padding: clamp(1.5rem, 4vw, 3.5rem) clamp(1.25rem, 4vw, 3rem) 4rem; }
        .intro { max-width: 58rem; margin-bottom: 2rem; }
        .eyebrow { color: var(--muted); font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: .5rem 0 .75rem; font-size: clamp(2rem, 5vw, 4rem); line-height: 1.02; letter-spacing: -.045em; }
        .intro p { max-width: 70ch; margin: 0; font-size: 1.05rem; line-height: 1.55; color: var(--muted); }
        .fixture-note { margin-top: 1rem; padding: .85rem 1rem; border-left: 4px solid var(--accent); background: var(--surface); font-size: .9rem; line-height: 1.45; }
        .samples { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; }
        .sample { min-width: 0; padding: 1rem; border: 1px solid var(--border); border-radius: 1rem; background: var(--surface); }
        .sample__head { display: flex; justify-content: space-between; align-items: baseline; gap: .75rem; margin-bottom: .85rem; }
        .sample h2 { margin: 0; font-size: 1.15rem; line-height: 1.2; }
        .sample__link { color: var(--muted); font: .72rem ui-monospace, monospace; overflow-wrap: anywhere; }
        .media-figure { min-width: 0; margin: 0; }
        .media-frame { position: relative; min-width: 0; overflow: hidden; border-radius: .75rem; background: var(--surface-strong); }
        .media-frame > img { display: block; width: 100%; height: auto; object-fit: cover; }
        .media-preview__frame--wide > img { aspect-ratio: 16/9; }
        .media-preview__frame--school > img { object-position: 50% 58%; }
        .media-preview__frame--code > img { aspect-ratio: 16/6; object-fit: contain; background: #101713; }
        .media-overlays { position: absolute; z-index: 2; right: .65rem; bottom: .65rem; left: .65rem; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: end; gap: .5rem; pointer-events: none; }
        .media-overlay__ai { display: block; grid-column: 1; justify-self: start; width: fit-content; max-width: 100%; padding: 0; background: transparent; }
        .media-overlays img.media-overlay__ai-icon { display: block; width: auto; max-width: 5.4rem; height: 1.8rem; flex: none; object-fit: contain; }
        .media-overlay__credit { display: inline-flex; grid-column: 2; justify-self: end; width: fit-content; max-width: 100%; align-items: center; padding: .32rem .48rem; border-radius: .2rem; font-size: .7rem; font-weight: 600; line-height: 1.3; text-align: right; overflow-wrap: anywhere; box-decoration-break: clone; pointer-events: auto; }
        .media-overlay__credit--dark { color: #fff; background: rgb(0 0 0 / .76); }
        .media-overlay__credit--light { color: #101410; background: rgb(255 255 255 / .92); }
        .media-overlay__credit a { color: inherit; text-decoration: underline; text-underline-offset: .15em; }
        .sample__caption { display: flex; justify-content: space-between; gap: .75rem; margin-top: .75rem; color: var(--muted); font-size: .78rem; line-height: 1.4; }
        .cv-portrait { width: min(100%, 22rem); margin-inline: auto; border-radius: .75rem; overflow: hidden; }
        .cv-portrait .media-frame { height: auto; overflow: visible; }
        .media-preview__frame--portrait > img { aspect-ratio: 1; object-position: 50% 30%; border-radius: .65rem .65rem 0 0; }
        .cv-portrait .media-overlays { position: relative; z-index: auto; inset: auto; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: .1rem; padding: .3rem; background: var(--surface-strong); }
        .cv-portrait .media-overlays img.media-overlay__ai-icon { max-width: 100%; height: 1rem; }
        .cv-portrait .media-overlay__credit { width: 100%; padding: .15rem .2rem; font-size: .55rem; line-height: 1.15; }
        .cv-portrait .media-overlay__ai { align-self: center; }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) { --page:#07100c; --surface:#101a15; --surface-strong:#18251e; --text:#f5faf6; --muted:#aab6ae; --border:#27372e; --accent:#66e1c4; }
        }
        @media (max-width: 760px) {
            .samples { grid-template-columns: 1fr; }
            .sample { padding: .75rem; }
            .media-overlays { right: .4rem; bottom: .4rem; left: .4rem; gap: .25rem; }
            .media-overlays img.media-overlay__ai-icon { height: 1.45rem; max-width: 4.35rem; }
            .media-overlay__credit { padding: .25rem .32rem; font-size: .62rem; }
            .sample__link { max-width: 45%; }
        }
    </style>
</head>
<body>
<header class="preview-header"><div class="preview-header__inner"><a class="preview-brand" href="/">Jack Ruder</a><span class="preview-tag">Lokale Medienvorschau</span></div></header>
<main>
    <header class="intro">
        <div class="eyebrow">Visual Test · nicht veröffentlicht</div>
        <h1>Bildnachweise im Medienrahmen</h1>
        <p>Das KI-Symbol wird als offizielles EU-SVG allein am Bild angezeigt. Unten rechts bleibt der vorhandene Urheber-, Quellen- und Lizenztext erhalten.</p>
        <div class="fixture-note"><strong>Testdaten:</strong> Die Symbole in diesem Preview sind manuelle Fixtures und keine Aussage über die gezeigten Dateien. Die gespeicherten AI-Metadaten der Fotos bleiben unverändert. Die Education-Attribution ist unverändert übernommen.</div>
    </header>
    <div class="samples">
        @foreach ($samples as $sample)
            <article class="sample {{ !empty($sample['portrait']) ? 'sample--portrait' : '' }}">
                <div class="sample__head"><h2>{{ $sample['title'] }}</h2><a class="sample__link" href="{{ $sample['page'] }}">{{ $sample['page'] }}</a></div>
                @if (!empty($sample['portrait']))<div class="cv-portrait">@endif
                <figure class="media-figure">
                    <div class="media-frame {{ $sample['frame'] }}">
                        <img src="{{ $sample['image'] }}" alt="{{ $sample['alt'] }}" loading="eager" decoding="async">
                        <div class="media-overlays">{!! $sample['overlay'] !!}</div>
                    </div>
                </figure>
                @if (!empty($sample['portrait']))</div>@endif
                <div class="sample__caption"><span>SVG: {{ $sample['icon_appearance'] }} · Bildnachweis: {{ $sample['credit_appearance'] }}</span></div>
            </article>
        @endforeach
    </div>
</main>
</body>
</html>
