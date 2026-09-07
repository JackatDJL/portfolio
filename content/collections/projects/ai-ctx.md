---
id: 592ac462-1e09-4f35-be25-8308b982fb2b
blueprint: project
title: ai-ctx
summary: 'ai-ctx ist ein kleines TypeScript-CLI, das eine lokale Codebase in brauchbaren Kontext für KI-Modelle im Browser übersetzt. Es entstand zu einer Zeit, als ich lokal entwickeln wollte, aber noch keinen guten agentischen Coding-Workflow zur Verfügung hatte.'
project_status: archived
featured: false
started_at: '2025-04-18 14:16'
ended_at: '2025-06-12 16:34'
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
seo_title: 'ai-ctx: Codebase-Kontext für Browser-KI'
seo_description: 'ai-ctx ist ein TypeScript-CLI, das lokale Projekte zu einem kompakten Kontext für KI-Modelle im Browser zusammenstellt.'
noindex: false
gallery:
  - projects/ai-ctx/effect-ai-ctx.png
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
        text: 'Das Problem vor den Agenten'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Bevor gute agentische Coding-Tools für mich sinnvoll zugänglich waren, lief KI-Unterstützung beim Programmieren oft im Browser. Der Code lag aber lokal. Also musste jedes Mal irgendwie genug Projektkontext aus dem Repository in einen Chat gelangen.'
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Meine kleine Zwischenlösung'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'ai-ctx sollte diesen Schritt automatisieren. Das CLI sammelt Projektkontext und kann eine Codebase in eine einzelne Textdatei zusammenführen, damit ein Browser-Modell nicht nur einen isolierten Codeausschnitt sieht. Die CLI selbst ist in TypeScript aufgebaut und nutzt Effect sowie @effect/cli.'
  -
    type: heading
    attrs:
      level: 2
    content:
      -
        type: text
        text: 'Warum das heute ein Archiv ist'
  -
    type: paragraph
    content:
      -
        type: text
        text: 'Mein eigener Workflow hat sich seitdem stark verändert. Agentische Coding-Tools können den lokalen Projektkontext heute direkt lesen und verändern. Dadurch ist ai-ctx für mich kaum noch nötig, aber gerade deshalb ist es ein schönes kleines Zeitdokument dafür, wie schnell sich Developer-Tooling rund um KI verändert hat.'
---
