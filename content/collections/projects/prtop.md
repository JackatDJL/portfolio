---
id: 4739fabf-5726-4d17-ad72-5a5f574e33cd
published: true
blueprint: project
title: prtop
summary: 'Ich entwickle prtop, um Pull Requests aus mehreren Git-Forges in einer tastaturbedienbaren Terminaloberfläche zu prüfen und zu verwalten.'
project_status: active
featured: true
started_at: '2026-08-29 23:11'
featured_technology:
  - rust
  - Ratatui
  - Tokio
used_technologies:
  - Rust
  - Ratatui
  - Tokio
  - Crossterm
  - Reqwest
  - Clap
  - Serde
  - TOML
repository_url: 'https://github.com/JackatDJL/prtop'
seo_title: 'prtop: Pull Requests im Terminal | Jack Ruder'
seo_description: 'prtop bündelt Pull Requests aus GitHub, GitLab und Codeberg in einer Rust-Terminaloberfläche. Ich entwickle das Werkzeug weiter.'
noindex: false
cover: projects/prtop/prtop-live-dashboard.png
gallery:
  - projects/prtop/prtop-tokio-main-run.png
updated_by: 7098a585-4701-4963-95aa-52c0d85d3e7d
updated_at: 1791814912
topics:
  - cli
  - devtools
  - git
  - oss
  - rust
related_projects:
  - 592ac462-1e09-4f35-be25-8308b982fb2b
content:
  - type: heading
    attrs: { level: 2 }
    content:
      - type: text
        text: 'Pull Requests an einem Ort'
  - type: paragraph
    content:
      - type: text
        text: 'Ich arbeite gern im Terminal und habe durch eigene Software- und Organisationsprojekte Konten auf mehreren Git-Forges, darunter GitHub und Codeberg. Pull Requests dort einzeln im Browser zu suchen und zu verwalten, wurde schnell umständlich. Deshalb begann ich prtop: eine Terminaloberfläche, in der ich offene Pull Requests aus dem aktuellen Repository oder forgeübergreifend sammeln kann.'
  - type: heading
    attrs: { level: 2 }
    content:
      - type: text
        text: 'Was im Werkzeug steckt'
  - type: paragraph
    content:
      - type: text
        text: 'Ich baue prtop in Rust mit Ratatui. GitHub, GitLab und Forgejo beziehungsweise Codeberg unterscheiden sich bei Reviews und verfügbaren Aktionen. Dafür habe ich getrennte Anbieteradapter angelegt, statt eine einzige gemeinsame API vorauszusetzen.'
  - type: paragraph
    content:
      - type: text
        text: 'In der Detailansicht prüfe ich Änderungen, Kommentare und Reviews und sehe die zugehörigen CI-Pipelines und Jobs. Die Oberfläche kann Daten im Hintergrund laden, während ich weiter mit ihr arbeite. Der Name verbindet „PR“ für Pull Request mit „btop“.'
  - type: heading
    attrs: { level: 2 }
    content:
      - type: text
        text: 'Noch nicht fertig'
  - type: paragraph
    content:
      - type: text
        text: 'prtop ist noch in Arbeit und hat kein Release. Mein Entwicklungsumfeld bietet inzwischen ähnliche Forge-Ansichten, deshalb nutze ich prtop selbst derzeit nicht. Ich möchte das Werkzeug trotzdem zu Ende bauen, statt es halbfertig liegenzulassen.'
---
