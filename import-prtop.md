---
id: 4739fabf-5726-4d17-ad72-5a5f574e33cd
published: false
blueprint: project
title: prtop
summary: prtop ist eine keyboard-first Terminal-Oberfläche für Pull Requests und Merge Requests über mehrere Git-Plattformen
  hinweg. Das Ziel ist ein gemeinsamer Workflow für GitHub, GitLab und Forgejo beziehungsweise Codeberg, ohne für jeden Anbieter
  ein anderes Tool zu brauchen.
project_status: active
featured: true
startet_at: '2026-08-29 23:11'
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
repository_url: https://github.com/JackatDJL/prtop
seo_title: "prtop: Multi-Forge PR/MR Terminal UI"
seo_description: prtop ist mein Rust-basiertes Terminal-Tool für Pull Requests und Merge Requests auf GitHub, GitLab und Forgejo/Codeberg.
noindex: false
content:
- type: heading
  attrs:
    level: 2
  content:
  - type: text
    text: Warum ich prtop angefangen habe
- type: paragraph
  content:
  - type: text
    text: Ich wollte Pull Requests nicht in mehreren Browser-Tabs und mit unterschiedlichen Oberflächen verwalten. Gerade
      sobald neben GitHub auch GitLab, Forgejo oder Codeberg im eigenen Workflow auftauchen, fehlt ein gemeinsames Werkzeug,
      das sich im Terminal zu Hause fühlt.
- type: heading
  attrs:
    level: 2
  content:
  - type: text
    text: Der Ansatz
- type: paragraph
  content:
  - type: text
    text: prtop ist deshalb als keyboard-first TUI für ein dauerhaftes tmux-Pane gedacht. Die Oberfläche basiert auf Ratatui.
      Die Forge-spezifische Logik wird hinter gemeinsamen Modellen und Provider-Grenzen versteckt, damit GitHub, GitLab und
      Forgejo möglichst gleich behandelt werden können.
- type: paragraph
  content:
  - type: text
    text: Zur aktuellen Basis gehören ein asynchrones Dashboard, lokale Konfiguration und Cache, Keyboard- und Mausnavigation
      sowie Adapter für mehrere Forge-Anbieter. Kommentare, Reviews, CI-Informationen und weitere Schreibaktionen sollen innerhalb
      desselben Workflows stattfinden, statt wieder in die jeweilige Weboberfläche zu zwingen.
- type: heading
  attrs:
    level: 2
  content:
  - type: text
    text: Status
- type: paragraph
  content:
  - type: text
    text: Das Projekt ist aktiv in Entwicklung. Mir geht es dabei nicht darum, die Weboberflächen der Git-Anbieter vollständig
      nachzubauen, sondern die Dinge, die ich bei Pull Requests ständig brauche, schnell und forge-übergreifend ins Terminal
      zu holen.
---
