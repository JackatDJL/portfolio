---
id: 592ac462-1e09-4f35-be25-8308b982fb2b
published: true
blueprint: project
title: ai-ctx
summary: 'Ich baute ai-ctx, um lokalen Codekontext in Browser-Chats zu übernehmen. Das CLI konnte globale und projektbezogene Einstellungen verwalten; Datei- und Workspace-Übergabe blieb offen.'
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
seo_description: 'Mein TypeScript-CLI-Prototyp bündelte Konfiguration für Code in Browser-Chats. Datei- und Workspace-Verarbeitung blieb unvollständig.'
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
        text: 'Ich baute das Befehlsgerüst mit TypeScript, Effect und @effect/cli. „ai-ctx init“ richtete globale und projektbezogene Einstellungen ein; „ai-ctx init reset“ stellte die Standardwerte wieder her. Die Datei- und Workspace-Befehle waren noch nicht bis zur tatsächlichen Verarbeitung umgesetzt.'
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
        text: 'Ungefähr zur gleichen Zeit begann ich, mich mit Agenten auseinanderzusetzen, die direkt im lokalen Projekt arbeiten. Bei mir war das anfangs GitHub Copilot, später kamen Codex, OpenCode und andere Werkzeuge dazu. Für eine Frage zu meinem Code musste ich dadurch nicht mehr das ganze Projekt in Gemini mit seinem großen kostenlosen Kontextfenster hochladen. Ich konnte einen Agenten direkt im Terminal nach einer Lösung fragen. Damit fiel mein eigener Bedarf für ai-ctx weg, bevor ich die Datei- und Workspace-Verarbeitung fertiggestellt hatte.'
---
