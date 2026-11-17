---
name: homepage-preview
description: Operate the Homepage repository's local Laravel and Vite preview when asked to start, inspect, read logs from, or stop one.
---

# Homepage Preview

Use the repo's named local preview manager for Homepage runtime previews. Before operating it, read [`docs/local-dev-previews.md`](../../../docs/local-dev-previews.md) for the current command syntax and behavior.

## Operate a preview

- Run commands from the Homepage repository root. The manager supports Linux only.
- Start a preview only when the user asks for one or the task needs local runtime proof. Check `git status --short --branch` and inspect the named preview's status first if it may already be running.
- Use `bun run preview -- up NAME` to start it. The default name is the current branch. `status`, `logs`, and `down` take the same name; the status output also provides an opaque-ID cleanup command.
- Report the returned local URLs, source SHA/branch, and health. Label this evidence as a local preview; it says nothing about Vercel, hosting, or production.
- Keep a preview running when the user asked to start or keep one. For temporary runtime proof, stop only the preview started for that task and report its cleanup.

## Boundaries

The manager runs Laravel and Vite on loopback using free ports. It uses a per-preview Vite hot file and prevents Bun/Vite from loading project `.env` values into browser code; Laravel still reads the worktree's `.env` as usual. Named previews share the same worktree, content, storage, and Laravel database configuration, so they are process/port isolation rather than data isolation.

It does not run migrations, seeders, Tailscale sharing, public hosting, or deployments. If startup fails, the manager retains its state and redacted logs; use the diagnostic path or preview ID in the error output to inspect them. Source or option changes under an existing name require stopping that preview before starting it again. Do not stop a preview that predates the current task unless the user asks for it.
