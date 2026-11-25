---
id: 7242f0d4-c5c8-405c-a548-bac27066c533
published: false
blueprint: project
title: 'Previewmanager Extended (prex) CLI'
summary: 'Für mein Portfolio, Rosenfold und DJL Studio baute ich jeweils eigene Skripte und Agent-Skills für lokale Vorschauen. Prex zieht den gemeinsamen Teil in eine CLI: Sie vergibt freie Ports, startet vorhandene Entwicklungsbefehle und verwaltet Vorschau-Sessions projektübergreifend.'
project_status: maintained
featured: true
started_at: '2026-11-21 23:00'
cover: projects/prex/screenshot-2026-11-25-at-20-37-28-prex--previews-extended.png
hero_transition: true
noindex: false
updated_by: 7098a585-4701-4963-95aa-52c0d85d3e7d
updated_at: 1795636316
content:
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Haben Sie schon mal das Problem gehabt, dass Ihr Preview-Port belegt ist? Mir passierte dies ständig, wenn ich (pre-KI) an mehreren Projekten gleichzeitig arbeitete; jetzt wurde dies nur schlimmer. Aus Frust hab ich anfangs ein kleines Shell-script geschrieben, und irgendwann, als ich jenes Script ins vierte Projekt kopiert hab, entstand der Gedanke, das Problem ein für allemal zu beseitigen. '
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Previewmanager Extended'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Prex reserviert beim Start freie lokale TCP-Ports und gibt sie an den vorhandenen Entwicklungsbefehl weiter. Projekt-Scripts in prex.toml können Ports benennen und Startbefehle festlegen. Für mehrteilige Setups lassen sich mehrere Ports gemeinsam vergeben, zum Beispiel für ein Frontend und ein Backend. Vorschauen können im Vordergrund laufen oder als benannte Sessions gestartet, aufgelistet, geprüft und beendet werden.'
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
