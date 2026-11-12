# Local development previews

`bun run preview -- up [name]` starts a named Laravel and Vite preview for this
worktree. The default name is the current Git branch. Both servers bind only to
`127.0.0.1`; the manager picks free ports and stores state and redacted logs in
`${XDG_CACHE_HOME:-~/.cache}/jack-homepage/dev-previews`. Set
`HOMEPAGE_DEV_PREVIEW_CACHE` to use another cache directory.

```sh
bun run preview -- up review-1
bun run preview -- status review-1
bun run preview -- logs review-1
bun run preview -- down review-1
```

The status includes the Git SHA, branch, dirty-worktree marker, worktree digest,
local URLs, process health, PIDs and cleanup command. Repeating `up` reuses a
healthy preview when its source matches. Changed source requires `down` before
another `up` with the same name.

The manager passes a small environment allowlist to child processes. Laravel
loads this worktree's `.env` as usual. Vite uses an empty env directory so
project `.env` values, including `VITE_*` variables, are not copied into the
browser. A per-preview Laravel hot file keeps this server independent from the
usual `public/hot` file. Logs are redacted before being written. State and logs
are private to the local user.

This first port covers local Web previews on Linux. It does not configure
Tailscale, publish a URL, or start application-specific containers. Stop a
preview before switching the source under its name.
