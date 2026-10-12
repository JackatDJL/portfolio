---
id: 4739fabf-5726-4d17-ad72-5a5f574e33cd
published: true
blueprint: project
title: prtop
summary: 'prtop bündelt Pull Requests und Merge Requests aus GitHub, GitLab und Forgejo/Codeberg in einer Rust-TUI. Je nach Anbieter gibt es darin Kommentar-, Review-, CI- und Merge-Abläufe.'
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
seo_description: 'Rust-TUI für Pull Requests und Merge Requests in GitHub, GitLab und Forgejo/Codeberg. Mit Detailansichten, Kommentaren, Reviews und CI-Abläufen je nach Anbieter.'
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
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Warum prtop im Terminal läuft'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Ich wollte Pull Requests nicht in mehreren Browser-Tabs und mit unterschiedlichen Oberflächen verwalten. prtop fasst offene Pull Requests und Merge Requests in einer gemeinsamen Terminalansicht zusammen, die sich per Tastatur bedienen lässt. Beim Start erkennt die App das Git-Repository im aktuellen Verzeichnis und öffnet es, wenn die Forge konfiguriert ist.'
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Welche Abläufe vorhanden sind'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Der Quellstand enthält ein Dashboard sowie Detailansichten für Pull Requests, Pipelines und Jobs. Die Detailansicht lädt Kommentare und Reviews. Je nach Anbieter und Berechtigung kann man Kommentare posten, Reviews abgeben und Anfragen erstellen, bearbeiten, schließen, wieder öffnen oder mergen. prtop blendet nicht unterstützte Schreibaktionen aus. Die Adapter enthalten auch Endpunkte zum Bearbeiten und Löschen von Kommentaren; die App bindet diese Aktionen noch nicht in die Oberfläche ein.'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Im CI-Bereich lassen sich Pipelines und Jobs öffnen. Logs können in unterstützten Adaptern durchsucht und bei laufenden Jobs verfolgt werden. Retry, Abbruch und manuelle Jobstarts erscheinen nur, wenn die Forge sie unterstützt. GitHub, GitLab und Forgejo haben eigene Adapter; ihre verfügbaren Details und Aktionen unterscheiden sich.'
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Startmodi und Stand'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Ohne Argument öffnet prtop das erkannte Projekt; mit . oder einem Repository-Pfad lässt sich die Auswahl ausdrücklich setzen. --global zeigt alle konfigurierten Projekte. --demo startet mit festen Beispieldaten und ohne Forge-Verbindung, getrennt vom Livebetrieb. Ein GitHub-Release ist bislang nicht veröffentlicht.'
---
