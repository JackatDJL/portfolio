---
id: 9348f50d-2ff5-4b61-bbc0-4a9c0d18b6d7
published: true
blueprint: project
title: 'Imkerportal Ruder'
summary: 'Ein lokaler Next.js- und Convex-Prototyp für Bienenvölker und Beutenteile. Kontrollen, Behandlungen, Honigernten und NFC-Verweise wurden nicht als durchgängige Funktionen umgesetzt.'
project_status: experiment
featured: false
started_at: '2025-04-13'
used_technologies: [TypeScript, Next.js, Convex, Clerk, Tailwind CSS]
topics: [web-development]
noindex: true
seo_title: 'Imkerportal Ruder | Jack Ruder'
seo_description: 'Ein lokaler Next.js- und Convex-Prototyp für Völker und Beutenteile. Erfassungsabläufe für Kontrollen, Behandlungen und Ernten sowie NFC-Verweise fehlen.'
content:
  - type: paragraph
    content:
      - type: text
        text: 'Die Planung sah vor, Bienenvölker, Beutenteile, Kontrollen, Behandlungen und Honigernten digital zu dokumentieren. NFC-Tags sollten zu den passenden Datensätzen führen. Diese Funktionen standen zunächst auf dem Plan.'
  - type: heading
    attrs: { level: 2 }
    content:
      - type: text
        text: 'Was im lokalen Code steht'
  - type: paragraph
    content:
      - type: text
        text: 'Der lokale Quellstand enthält Convex-Datenmodelle und Speicherfunktionen für Völker und Beutenteile. Formulare legen beide Datensatztypen an. Eine Volks-Detailseite lädt die Daten und zeigt einen interaktiven Beutenstapel, dessen Reihenfolge sich speichern lässt. Die Übersichtsseiten für Völker und Beutenteile zeigen noch Platzhalter.'
  - type: paragraph
    content:
      - type: text
        text: 'Kontrollen, Behandlungen und Ernten sind als Datenmodelle angelegt, aber passende Erfassungsseiten und Speicherfunktionen fehlen. Die geplante NFC-Anbindung und Weiterleitung von Tag-IDs zu Datensätzen stehen nur in README.md und idea.md; im Code ist dafür keine Implementierung vorhanden.'
  - type: paragraph
    content:
      - type: text
        text: 'Der öffentliche GitHub-Stand zeigt weiterhin das generische Create T3 App-Gerüst. Er enthält nicht den weiterentwickelten lokalen Arbeitsstand.'
---
