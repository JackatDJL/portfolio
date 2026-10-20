---
id: 6c8e1c32-6c99-4b25-96a1-464ae8fc4706
published: true
blueprint: project
title: 'SV-Digitalisierung und Schließfachmanager'
summary: 'Aus meinen früheren Ideen zur Digitalisierung der Schülervertretung entwickelte ich einen Schließfachmanager. Das Terminalprogramm verwaltet Fächer, Ausleihen, Kautionen und Zahlungen.'
project_status: experiment
featured: false
repository_url: 'https://github.com/DJL-Foundation/schlie-fach-manager'
cover: 'projects/sv-digitalisierung/schliessfach-manager-dashboard.webp'
started_at: '2026-01-17'
topics: [cli, education]
used_technologies: [TypeScript, Bun, SQLite]
seo_title: 'SV-Digitalisierung und Schließfachmanager | Jack Ruder'
seo_description: 'Ich entwickelte einen Schließfachmanager für die Schülervertretung. Die Terminalanwendung verwaltet Ausleihen, Kautionen, Zahlungen und Datenexporte.'
noindex: true
content:
  - type: paragraph
    content:
      - type: text
        text: 'Der Schließfachmanager entstand aus meinen früheren Ideen zur Digitalisierung der Schülervertretung. Ich wollte eine Anwendung bauen, mit der die SV Schließfächer und Ausleihen an einem Ort verwalten könnte, statt einzelne Vorgänge über Listen und Absprachen zu verteilen.'
  - type: heading
    attrs: { level: 2 }
    content:
      - type: text
        text: 'Fächer verleihen und zurücknehmen'
  - type: paragraph
    content:
      - type: text
        text: 'Ich setzte die Kernanwendung als Terminalprogramm in TypeScript um. Sie verwaltet Schließfächer, Verleih, Rückgabe und Verlängerung. Auch Schäden, Kautionen und Zahlungen lassen sich erfassen. Ein Änderungsprotokoll hält die Vorgänge fest.'
  - type: paragraph
    content:
      - type: text
        text: 'Zum Projekt gehören außerdem eine interaktive Terminaloberfläche, Hilfeseiten, Tests und Datenimport aus JSON und TOML. Die Anwendung kann Berichte in JSON, CSV, TOML und Markdown exportieren.'
---
