---
id: 840804f5-d413-4824-a847-769393170ed4
published: false
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
content:
  - type: paragraph
    content:
      - type: text
        text: 'Die Planung beschränkt das Projekt bewusst auf Drucken: authentifizieren, ein Dokument prüfen, Optionen aus den bekannten Druckern wählen, Auftrag abschicken und verwalten. Es ist keine Fernwartung und keine öffentliche CUPS-Installation.'
  - type: paragraph
    content:
      - type: text
        text: 'Der Stand ist frühe Entwicklung. Die Sicherheitsannahmen und die enge CUPS-Grenze sind Teil des Konzepts, keine Aussage über eine fertige Sicherheitsprüfung.'
---
