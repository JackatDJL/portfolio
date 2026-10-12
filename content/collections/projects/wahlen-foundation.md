---
id: 037c7fec-9f45-4ac2-8c5f-3d3f6cf5c251
published: true
blueprint: project
title: 'Wahlen Foundation'
summary: 'Ein archivierter Webversuch für digitale Abstimmungen. Der Code enthält Backend-Bausteine für Wahlentwürfe und Fragen; die Stimmabgabe wurde nicht umgesetzt.'
project_status: archived
featured: false
started_at: '2025-03-15'
used_technologies: [TypeScript, Next.js, tRPC, Drizzle]
repository_url: 'https://github.com/JackatDJL/wahlen-foundation'
topics: [civic-technology, web-development]
noindex: true
seo_title: 'Wahlen Foundation | Jack Ruder'
seo_description: 'Ein archivierter Webversuch für digitale Abstimmungen mit Backend-Bausteinen zur Wahl- und Fragenverwaltung. Die Stimmabgabe wurde nicht umgesetzt.'
content:
  - type: paragraph
    content:
      - type: text
        text: 'Wahlen Foundation war ein Versuch, eine Webanwendung für digitale Abstimmungen zu bauen. Das README beschreibt eine Trennung zwischen einem Verwaltungsbereich unter der Hauptdomain und öffentlichen Wahlseiten auf Organisations-Subdomains.'
  - type: heading
    attrs: { level: 2 }
    content:
      - type: text
        text: 'Im Code umgesetzt'
  - type: paragraph
    content:
      - type: text
        text: 'Im tRPC-Backend gibt es Funktionen, um Wahlentwürfe anzulegen, zu bearbeiten, zu veröffentlichen und zeitlich zu planen. Fragen lassen sich über eigene Endpunkte anlegen, ändern und löschen.'
  - type: heading
    attrs: { level: 2 }
    content:
      - type: text
        text: 'Was offen blieb'
  - type: paragraph
    content:
      - type: text
        text: 'Das Repository enthält keine App-Routen für die im README beschriebenen Verwaltungs- und Wahlseiten. Die öffentliche Startseite zeigt ein einfaches Next.js-Beispiel mit „Hello World“ und meldet, dass die Funktion noch nicht verfügbar ist. Die Ergebnisfunktionen und das Löschen einer Wahl sind im API-Router Platzhalter. Eine Funktion zur Stimmabgabe ist nicht implementiert.'
---
