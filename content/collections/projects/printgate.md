---
id: 840804f5-d413-4824-a847-769393170ed4
published: true
blueprint: project
title: Printgate
summary: 'Printgate ist ein kleines, selbst gehostetes Web-Gateway für Drucker an einem Linux-Rechner. Es soll private Fernzugriffe auf vorhandene CUPS-Drucker ermöglichen, ohne die CUPS-Verwaltung ins Internet zu stellen.'
project_status: experiment
featured: false
started_at: '2026-08-31'
used_technologies: [PHP, Laravel, CUPS, Tailscale]
repository_url: 'https://github.com/JackatDJL/printgate'
topics: [infrastructure, privacy, web-development]
noindex: true
seo_title: 'Printgate | Jack Ruder'
seo_description: 'Printgate ist ein selbst gehostetes Laravel-Gateway für Druckaufträge an vorhandene CUPS-Drucker.'
content:
  - type: paragraph
    content:
      - type: text
        text: 'Printgate ist auf einen klaren Ablauf zugeschnitten: anmelden, ein Dokument auswählen, Optionen eines vorhandenen Druckers wählen, den Auftrag abschicken und seinen Status sehen. Die CUPS-Verwaltung bleibt dabei auf dem Linux-Rechner.'
  - type: paragraph
    content:
      - type: text
        text: 'Das Projekt befindet sich in einer frühen Entwicklungsphase. Laravel bildet die Webanwendung, CUPS führt die Druckaufträge aus, und Tailscale verbindet die beteiligten Geräte im privaten Netz.'
---
