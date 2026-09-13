# Content research: project and experience evidence

Research date: 13 September 2026. This note separates source-supported claims from proposed public copy. Local checkouts are evidence, not proof that a service is currently deployed. Do not publish credentials, internal hostnames, participant data, or security/design details from these sources.

## Recommended project inventory

| Candidate / family | Source-supported public-safe finding | Conservative status / dating | Evidence |
| --- | --- | --- | --- |
| prtop | Keyboard-first terminal monitor for pull and merge requests across multiple forges. The README documents Ratatui, normalized providers, configuration and CI views. | Active development; local Git history starts 2026-08-29. | `/home/jack/dev/prtop/README.md`; [GitHub](https://github.com/JackatDJL/prtop) |
| ai-ctx | Apache-2.0 command-line tool that assembles a project context from files, ignores and optional import traversal for browser-based AI use. | Active/maintained only if confirmed; initial local commit 2025-04-18. | `/home/jack/dev/ai-ctx/README.md`; [GitHub](https://github.com/jackatdjl/ai-ctx) |
| AtheBlues | RoboCup team at Gymnasium Athenaeum Stade; the public monorepo covers team web and technical work. The current README describes **Rescue Line**, while the 2024 archive and old team site describe **RoboCup Junior OnStage**. This demonstrates distinct seasons/iterations, not one interchangeable league label. The old site itself says the team placed second at the 2024 German Championship and qualified for the European Championship; its team-lead claim comes only from the DJL archive manifest. | Project history begins locally 2024-03-08; current local checkout has a 2025-09 commit. Use “RoboCup-Team” in summary copy; describe the documented 2024 OnStage season only in body text. Do not assign Jack a formal leadership title without confirmation. | `/home/jack/dev/AtheBlues/README.md`; `/home/jack/dev/AtheBlues/archive/onstage-24/README.md`; `/home/jack/dev/AtheBlues/apps/web-old/src/lib/blog-data.ts`; [repository](https://github.com/JackatDJL/AtheBlues); `/home/jack/dev/foundation-homepage/src/content/projects.ts` |
| Hackclub Stade | Local technical education initiative at Gymnasium Athenaeum Stade. The DJL archive identifies Jack as founder/lead and records a pause since 31 January 2025; Projektwoche confirms Hackclub’s organisational role, not those personal title/date claims. | Treat founder/lead and exact pause date as confirmation-needed. “Mitgründung und Organisation” is safer only after user confirmation. | `/home/jack/dev/foundation-homepage/src/content/projects.ts`; `/home/jack/dev/projektwoche/README.md` |
| Projektwoche | School project-week work on sustainable web development at Gymnasium Athenaeum Stade. Repository documents a website, student-project hosting and setup tooling; names Jack as lead developer, with Hackclub Stade organising/delivering. | 2025; archived/finished as a specific project-week programme. Local history begins 2025-08-02. | `/home/jack/dev/projektwoche/README.md`; [GitHub](https://github.com/DJL-Foundation/projektwoche) |
| Erstwählerforum Stade | Jack Ruder founded the civic-education event; it was organised by pupils across several Stade schools for the 13 September 2026 municipal election. The official project site describes independent information, questions and direct political exchange, and the Athenaeum report confirms the event took place at Stadeum on 2 June. Technical work can be summarized as website and event-support systems without naming internal services. | 2026, completed event; local site history starts 2026-01-23. | [Volt Deutschland profile](https://voltdeutschland.org/niedersachsen/menschen/jack-ruder); [project site](https://ewf-stade.de/); [Athenaeum report](https://athenaeum-stade.de/athe-rueckspiegel-erstwaehlerforum-im-stadeum/); [Codeberg](https://codeberg.org/erstwaehler/homepage) |
| Presentation Foundation -> Prism | One evolving presentation-material sharing project, not two portfolio entries. Older README calls it “The Presentation Foundation”; DJL archive says it was renamed Prism and a 2026 rebuild began. The project’s own old README makes unverified legal/nonprofit claims, which must not be repeated. | Paused / rebuilding, 2024–2026 only after dates confirmed from history. | `/home/jack/dev/DJL Foundation/prism/README.md`; `/home/jack/dev/foundation-homepage/src/content/projects.ts`; [Codeberg](https://codeberg.org/djl-foundation/prism) |
| SV digitisation / Schließfachmanager | Consolidated school-digitisation family. The manager is a terminal application for locker administration with lending, deposits/payments, import/export and audit logging. Do not expose operational deployment, data, or individual school details. | Experiment/archived; current manager history shows 2026 work. | `/home/jack/dev/DJL Foundation/schließfach-manager/docs/README.md`; [GitHub](https://github.com/DJL-Foundation/schlie-fach-manager) |
| Wahlen Foundation | Unfinished web voting-system attempt; README describes a multi-domain architecture and calls it a solo project. Do not market it as secure, production-ready or used for elections. | Archived, 2025 (local history begins 2025-03-15). | `/home/jack/dev/DJL Foundation/wahlen-foundation/README.md`; [GitHub](https://github.com/JackatDJL/wahlen-foundation) |
| Foundation Drive | Attempt at a Drive-like service; archive attributes discontinuation to lack of durable object storage. | Archived, 2025. | `/home/jack/dev/foundation-homepage/src/content/projects.ts`; [GitHub](https://github.com/DJL-Foundation/foundation-drive) |
| DJL ID | Experiment for a shared identity layer; archive records Better Auth as part of the experiment. Keep text high-level; never publish auth internals or deployment. | Experiment, 2026. | `/home/jack/dev/foundation-homepage/src/content/projects.ts`; [GitHub](https://github.com/DJL-Foundation/id) |
| DJL Custom Domains | Cloudflare-worker fallback-origin prototype for custom-domain routing. Its README contains sensitive operational concepts and environment-variable names; public copy should say only “experiment for shared custom-domain routing”, if included. | Unfinished/archived; local history includes March 2026. | `/home/jack/dev/DJL Foundation/custom-domains/README.md`; [GitHub](https://github.com/DJL-Foundation/custom-domains) |
| Imkerportal Ruder | A local document describes a proof-of-concept for beekeeping records and NFC-linked hive components, but the public repository has only generic starter content. | Do not publish substantive claims without approval that the local PoC material is public. | Local-only: `/home/jack/dev/imkerportal-ruder/README.md`; [GitHub](https://github.com/JackatDJL/imkerportal-ruder) |
| Printgate | Early-development self-hosted web gateway for private remote printing to printers accessible from a Linux host. It deliberately avoids exposing CUPS publicly. | Early development; local history begins 2026-08-31. | `/home/jack/dev/printgate/README.md`; [GitHub](https://github.com/JackatDJL/printgate) |
| DJL UI | Reusable UI/design-system experiment. | Archived, March 2025 according to the archive. | `/home/jack/dev/foundation-homepage/src/content/projects.ts`; [GitHub](https://github.com/JackatDJL/djl-ui) |
| NoPlus / A Cube’s Journey | Respectively unfinished FPS/open-world-loader work and unfinished Unity/game-jam story-game idea. These are useful archive/progression entries, not flagship work. | Archived (2023 / 2025 in archive). | `/home/jack/dev/foundation-homepage/src/content/projects.ts` |
| SongRaten | Music-guessing project. This is all the public repository description establishes; keep any entry short and archival. | Archived/older project; public repo created 2023-06-27. | [GitHub](https://github.com/JackatDJL/SongRaten) |
| Hamburg Hack-A-Ton Archive | Planning-stage archive/prototype, explicitly not affiliated with the City of Hamburg or Hack Foundation. | Archived; public repository created 2024-11-08. | [GitHub](https://github.com/JackatDJL/Hamburg-Hack-A-Ton-Archive) |
| Athenetz-SV-Old | Older student-council monorepo at Gymnasium Athenaeum Stade. It belongs in the SV-digitisation family, not as a separate personal project. | Older iteration; public repository created 2024-06-05. | [GitHub](https://github.com/JackatDJL/Athenetz-SV-Old) |
| DJL Foundation archive/homepage | An archive/project-organisation website rather than evidence of a registered foundation. Its own content is manifest-driven and explicitly says not to imply incorporation. | Active only if separately confirmed. | `/home/jack/dev/foundation-homepage/src/content/projects.ts`; `/home/jack/.codex/memories/MEMORY.md` (prior-run warning, re-verify before public copy) |

## Experience evidence and safe framing

- **Erstwählerforum Stade (2026):** create a project plus a separate experience entry with the source-supported role **Gründer und Mitorganisator**. The official Volt profile supports founding; the school report supports multi-school student-council organisation, so do not imply sole organisation. “Projektleitung” remains a weak source. Do not publish planning notes’ names, meetings, school-operation details, access systems or email/account architecture.
- **AtheBlues (2024):** verified public sources establish Jack as a listed team member in the OnStage season: second place at the 2024 RoboCup Junior German Championship and qualification for the European Championship. Do not call him founder, team lead or programmer. The current repository’s Rescue Line README describes a distinct later/current iteration; do not conflate it with 2024 OnStage.
- **Hackclub Stade:** founder/lead and pause date are supported only by the prior DJL archive manifest. Do not create an Experience entry with those claims without user confirmation.
- **Projektwoche:** source explicitly credits Jack as lead developer. This supports a concise experience bullet under the project, but does not establish an ongoing employment role.
- **Volt Stade:** the official Volt Deutschland profile supports **Communications Lead und Pressesprecher bei Volt Stade**, with press work, social media, website and public communication named as responsibilities. It says he was elected in early March, but gives no year. Do not add an unverified year.
- **Volt Europa / Volt Deutschland technical work:** the same public profile supports technical work with Volt EUR Tech and experience in Volt Deutschland’s structured software-development team. Use generic “technische Mitarbeit” without repository, system, security or infrastructure detail; exact timeframe/role remains unverified.
- **Student representation / committees:** no reliable public/local source was located in this pass for exact elected roles, dates or committee memberships. Do not create an entry from the seed alone.

## Deliberate consolidation and exclusions

- Consolidate **Presentation Foundation -> Prism**, not two entries.
- Consolidate **SV digitisation / Schließfachmanager**; internal mobile/site attempts may be described in the family narrative rather than listed as separate projects.
- Consolidate **Erstwählerforum website, polling, signage and identity-related repositories** under the civic project; do not publish technical subprojects as independent projects.
- Consolidate **Athenetz-SV-Old** with SV digitisation / Schließfachmanager. Athenetz-SV-Mobile has only insufficient standalone public evidence.
- Treat **DJL ID**, **DJL Custom Domains**, **Foundation Drive** and **Wahlen Foundation** as clearly labelled experiments/unfinished work.
- Keep NoPlus and A Cube’s Journey as archive/progression material, potentially drafts.
- Do not present forks, starter templates, vendor repositories, or internal Volt repositories as personal projects. Public account forks include t3code, OnBoard, first-contributions, next.js, blot and tonkeeper-web; OnBoard has no evidence here for an original independent project. `startProjects` is a discontinued bundle of small test projects and should not produce entries beyond a carefully sourced SongRaten archive entry.
- `jimcli` has no public README in the inspected material: omit it pending evidence. The public Imkerportal repository has only generic starter content despite the richer local PoC document; do not publish the local-only technical claims without approval that they are public.

## Date guardrails for draft entries

Use a date only where the cited source supports it. Public repository creation dates are acceptable conservative **project-start proxies**, not proof of conception or public launch:

- `ai-ctx`: 18 April 2025; `Wahlen Foundation`: 15 March 2025; `Imkerportal Ruder`: 13 April 2025; `Prism`: 20 February 2025; `Foundation Drive`: 12 February 2025; `A Cube’s Journey`: 2 February 2025; `NoPlus`: 2 December 2023.
- `Projektwoche`: 2 August 2025 (repository start), while the programme itself should be dated simply 2025 unless event dates are sourced.
- `Schließfachmanager`: 17 January 2026; `DJL ID`: 4 March 2026; `DJL Custom Domains`: use the local repository history only (March 2026) unless a public creation date is checked.
- `prtop`: 29 August 2026; `Printgate`: 31 August 2026.
- `Erstwählerforum`: use the independently reported event date 2 June 2026 and election context 13 September 2026; do not label it merely planned.
- `AtheBlues`: 2024 is supported for the verified OnStage season. The 2020 start in the DJL archive is not independently checked in this pass.
- Do not assign any date to Hackclub Stade’s founding, pause, Volt roles, school representation, or DJL Foundation history without user confirmation or an independent source.

## Confirmation questions before publication

1. What exact year/start date should be used for Volt Stade? The public profile supports Communications Lead und Pressesprecher but says only “Anfang März”.
2. What public-safe organisations/titles/timeframes should be used for Volt Europa and Volt Deutschland technical work, if any?
3. Confirm whether the later Rescue Line repository represents a continued AtheBlues team for the project’s status; the 2024 OnStage result is independently public.
4. Confirm Hackclub Stade founder/lead wording and the 31 January 2025 pause date.
6. Supply sources for student-representation roles, school/education dates, and any committee appointments before adding them to a CV.

## Sources inspected

- Local repositories and their Git history: `prtop`, `ai-ctx`, `AtheBlues`, `Projektwoche`, `EWF/ewf-homepage`, `imkerportal-ruder`, `printgate`, and the DJL Foundation project directories.
- Project READMEs and manifests named in the table.
- Existing site global: `/home/jack/dev/jack-homepage/content/globals/jacks-portfolio/now.yaml`.
- Existing DJL archive manifest: `/home/jack/dev/foundation-homepage/src/content/projects.ts`.
- GitHub public account/repository metadata for `JackatDJL`, including the repositories linked above (queried 12 September 2026).
