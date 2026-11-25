---
id: 7242f0d4-c5c8-405c-a548-bac27066c533
blueprint: project
title: 'Previewmanager Extended (prex) CLI'
summary: 'Frameworks sind darauf ausgelegt, nur einen Dev-Server laufen zu lassen. Mit mehreren Projekten gleichzeitig bin ich ständig in dasselbe Problem geraten. Am Ende hatte ich ein handgeschriebenes shell-skript in jedes Projekt kopiert, um Ports zu delegieren. Nun habe ich die Idee in ein CLI umgeschrieben.'
project_status: active
featured: false
started_at: '2026-11-21 23:00'
website_url: 'https://prex.djl.foundation'
used_technologies:
  - Effect.ts
hero_transition: true
topics:
  - cli
  - devtools
  - ai
  - oss
related_projects:
  - 4739fabf-5726-4d17-ad72-5a5f574e33cd
  - 592ac462-1e09-4f35-be25-8308b982fb2b
noindex: false
updated_by: 7098a585-4701-4963-95aa-52c0d85d3e7d
updated_at: 1795638168
cover: projects/prex/screenshot-2026-11-25-at-21-04-29-prex--previews-extended.png
content:
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Hatten Sie auch schon mal das Problem, dass alle Ihre Preview-Prozesse die gleichen Ports belegen wollten? Jenes Problem geschah mir schon in der Pre-KI-Zeit, als ich gleichzeitig an mehreren Projekten gearbeitet habe. Mittlerweile ist es noch schlimmer, weil jeder Thread sein eigenes Preview haben möchte. Das hat mich gestört und ich habe ein kleines Shell-Skript per Hand geschrieben, um die Ports zu delegieren. Als ich das dann ins vierte Projekt rüberkopiert habe, wurde mir klar, dass man das auch für die Allgemeinheit verfügbar machen muss. Und so entstand:'
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Previews Extended'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Prex reserviert beim Start freie lokale TCP-Ports und gibt sie an den vorhandenen Entwicklungsbefehl weiter. Projekt-Scripts in prex.toml können Ports benennen und Startbefehle festlegen. Für mehrteilige Setups lassen sich mehrere Ports gemeinsam vergeben, zum Beispiel für Laravel und Vite. Vorschauen können im Vordergrund laufen oder als benannte Sessions gestartet, aufgelistet, geprüft und beendet werden.'
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Ein Skill für verschiedene Projekte'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Der installierbare Prex-Agent-Skill beschreibt denselben Grundablauf für Coding-Agents: lokale Projektanweisungen lesen, den passenden Befehl starten und die Vorschau anhand ihrer URL und Session-ID zurückmelden. So bleibt die projektspezifische Einrichtung im jeweiligen Projekt, während Portvergabe und Session-Verwaltung gemeinsam nutzbar sind.'
---
