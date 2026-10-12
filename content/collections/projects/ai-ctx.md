---
id: 592ac462-1e09-4f35-be25-8308b982fb2b
published: true
blueprint: project
title: ai-ctx
summary: 'ai-ctx war mein TypeScript-CLI-Prototyp für Codekontext in Browser-Chats. Umgesetzt wurden das CLI-Grundgerüst und Befehle für globale und projektbezogene Konfiguration. Datei- und Workspace-Verarbeitung blieb unvollständig.'
project_status: archived
featured: false
started_at: '2025-04-18'
featured_technology:
  - TypeScript
  - Effect
  - CLI
cover: projects/ai-ctx/effect-ai-ctx.png
used_technologies:
  - TypeScript
  - Effect
  - '@effect/cli'
  - Node.js
  - tsx
  - tsup
  - Vitest
  - pnpm
repository_url: 'https://github.com/JackatDJL/ai-ctx'
seo_title: 'ai-ctx: CLI-Prototyp für Browser-KI'
seo_description: 'Mein TypeScript-CLI-Prototyp sollte Dateien für Browser-Chats bündeln. Umgesetzt wurden das CLI-Grundgerüst sowie das Anlegen und Zurücksetzen globaler und projektbezogener Konfiguration.'
noindex: false
updated_by: 7098a585-4701-4963-95aa-52c0d85d3e7d
updated_at: 1788815940
topics:
  - oss
  - devtools
  - ai
  - cli
related_projects:
  - 4739fabf-5726-4d17-ad72-5a5f574e33cd
content:
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Kontext für Browser-Chats'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Meine Codeprojekte lagen lokal, während KI-Unterstützung für mich damals oft im Browser stattfand. ai-ctx sollte relevante Dateien zu einer Textausgabe bündeln, die ich in einen Chat übernehmen konnte.'
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Was ich umgesetzt habe'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Das CLI-Grundgerüst nutzt TypeScript, Effect und @effect/cli. ai-ctx init legte globale und projektbezogene Konfigurationsdateien an; mit ai-ctx init reset ließen sie sich auf Standardwerte zurücksetzen. Die Befehle file und workspace waren registriert, verarbeiteten im veröffentlichten Stand aber noch keine Dateien.'
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Warum ich es archiviert habe'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Später nutzte ich Coding-Agenten direkt im lokalen Repository und brauchte den Umweg nicht mehr. ai-ctx blieb ein Prototyp; die Datei- und Workspace-Verarbeitung habe ich nicht fertiggestellt.'
---
