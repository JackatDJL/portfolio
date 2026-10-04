# Production deployment on Dokploy

This guide deploys the dynamic Laravel/Statamic app as an independent Dokploy Compose application. It uses the existing single-node Swarm, Traefik, and remotely managed Cloudflare Tunnel. It does not change `rosenfold-infra` or expose public Hetzner ports 80/443.

## Runtime shape

- Dokploy builds the repository's `Dockerfile` from `main`. The image contains PHP-FPM, Nginx, the Vite build, and LuaLaTeX. It excludes editorial content, uploaded assets, publication PDFs, and the Statamic user file.
- Nginx listens on container port `8080`; only the image-backed `/index.php` is sent to PHP-FPM. Other `.php` paths return 404, and Nginx does not follow symlinks in the served tree. Supervisor runs Nginx, PHP-FPM, and cron. Cron invokes `php artisan portfolio:content-sync` every five minutes.
- The existing Laravel health route `/up` is the container health check. It only reports that the app booted.
- One replica is required because this deployment uses a local SQLite database and host bind mounts.
- `build.sh` and the existing Vercel/static SSG path remain available for static QA and deployments.

## Repository content and assets

The Statamic asset container is `assets` in `content/assets/assets.yaml`; it uses the `assets` filesystem disk rooted at `public/assets`. Its binaries and `.meta/*.yaml` sidecars are already in Git. The current tree is about 26 MB, so the content Git workflow includes all of `public/assets/**`.

The two publication PDFs under `public/documents/**` are referenced by editorial publication entries but are outside the configured Statamic asset disk. They are included in the same content sync and persistent mount. Content synchronization therefore covers:

- `content/**`, including asset-container metadata and all flat-file content;
- `public/assets/**`, including uploaded files and metadata sidecars;
- `public/documents/**`, the current editorially referenced publication PDFs.

Blueprints, templates, PHP, JavaScript, CSS, and other application files stay in the image. The Docker build context excludes all three editorial paths and `users/**`.

## Create the content branch and production Git credential

Create `content-sync` from the current `main` once, before enabling the workflow or server command. From a trusted workstation:

```sh
git fetch origin main
git push origin origin/main:refs/heads/content-sync
```

Create a dedicated Ed25519 deploy key for this repository. Add its public key under **JackatDJL/portfolio → Settings → Deploy keys** with write access enabled. This key is repository-scoped; the server command only pushes `content-sync`. Do not use a personal SSH key. Keep the private key out of Git and Dokploy environment text fields.

Create a `known_hosts` file for `github.com`, then compare its fingerprints with GitHub's published SSH host-key fingerprints before trusting it:

```sh
ssh-keyscan github.com > github_known_hosts
ssh-keygen -lf github_known_hosts
```

On the Swarm manager, create the immutable Docker secret and config from those files:

```sh
docker secret create content-sync-deploy-key /secure/path/portfolio-content-sync
docker config create github-known-hosts /secure/path/github_known_hosts
```

The Compose file references these as external Swarm objects. For key rotation, create a new versioned secret, update the Compose reference, deploy, then remove the old secret after confirming the new service is healthy. Do not replace secrets automatically.

## Prepare persistent host paths

The paths below are the host-side contract in `compose.production.yaml`:

| Host path | Container path | Purpose |
| --- | --- | --- |
| `/srv/jack-portfolio/content-repo` | `/sync` | Full Git working tree on `content-sync` |
| `/srv/jack-portfolio/content-repo/content` | `/var/www/html/content` | Live Statamic content |
| `/srv/jack-portfolio/content-repo/public/assets` | `/var/www/html/public/assets` | Writable Statamic asset container and metadata |
| `/srv/jack-portfolio/content-repo/public/documents` | `/var/www/html/public/documents` | Editorial publication PDFs |
| `/srv/jack-portfolio/state/storage` | `/var/www/html/storage` | SQLite, sessions, Stache, cache, logs, LuaLaTeX home/cache, and private CV PDF files |
| `/srv/jack-portfolio/state/users` | `/var/www/html/users` | Statamic's file-backed user, password hash, preferences, and 2FA state |

Create the directories and keep the writable bind mounts owned by UID/GID `33` (`www-data` in the image):

```sh
sudo install -d -o 33 -g 33 -m 0750 \
  /srv/jack-portfolio/content-repo \
  /srv/jack-portfolio/state/storage \
  /srv/jack-portfolio/state/users
```

Clone the full repository at `content-sync` using the dedicated deploy key. For the initial clone, temporarily make the key available to the `git` command on the host, use strict host checking and the verified `known_hosts` file, then remove that temporary key copy after Docker has stored the Swarm secret:

```sh
sudo -u '#33' env GIT_SSH_COMMAND='ssh -i /secure/path/portfolio-content-sync -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=/secure/path/github_known_hosts' \
  git clone --branch content-sync git@github.com:JackatDJL/portfolio.git \
  /srv/jack-portfolio/content-repo
```

Set the repository-local author identity used for Git merge commits and manual conflict recovery:

```sh
sudo -u '#33' git -C /srv/jack-portfolio/content-repo config user.name 'Portfolio Content Sync'
sudo -u '#33' git -C /srv/jack-portfolio/content-repo config user.email 'portfolio-content-sync@users.noreply.github.com'
```

Seed the separate user volume once from the existing Statamic account file. This file is not included in the image or content-sync commits after the mount is active:

```sh
sudo sh -c 'umask 077; git -C /srv/jack-portfolio/content-repo show origin/main:users/jack@djl.foundation.yaml > /srv/jack-portfolio/state/users/jack@djl.foundation.yaml'
sudo chown 33:33 /srv/jack-portfolio/state/users/jack@djl.foundation.yaml
sudo chmod 0600 /srv/jack-portfolio/state/users/jack@djl.foundation.yaml
```

On Fedora, Swarm service bind mounts do not apply Docker's `:z`, `:Z`, or `:ro` options. Apply a persistent SELinux file-context rule to the application data tree and restore labels; do not disable SELinux:

```sh
sudo semanage fcontext -a -t container_file_t '/srv/jack-portfolio(/.*)?'
sudo restorecon -Rv /srv/jack-portfolio
```

If that exact rule already exists, use `semanage fcontext -m` instead of `-a`. Confirm UID/GID permissions and labels before deployment. The Git key itself is delivered as a Swarm secret owned by UID 33 with mode `0400`; the public host-key list is a readable Swarm config. Neither is a writable host bind mount. Verify the deployed task sees the deploy key as readable by `www-data` and rejected by OpenSSH as too broadly accessible before enabling cron.

## Dokploy application

Create a new **Docker Compose** application for `JackatDJL/portfolio`:

1. Select GitHub as the provider, repository `JackatDJL/portfolio`, branch `main`, and Compose file `compose.production.yaml`.
2. Let Dokploy build the image from the repository's `Dockerfile`. Do not set a host-published port. The service joins the existing `dokploy-network` and listens internally on `8080`.
3. Set the Dokploy Git **Watch Paths** from [`docker/dokploy-watch-paths.json`](../docker/dokploy-watch-paths.json). This is a positive allow-list of application/build paths; it intentionally omits `content/**`, `public/assets/**`, and `public/documents/**`.
4. Keep the Compose health check enabled at `http://127.0.0.1:8080/up`. Keep replicas at one and the stop-first update order.
5. Add the external Docker secret `content-sync-deploy-key` and external config `github-known-hosts` exactly as named in the Compose file.
6. Add the environment values below in Dokploy. Do not add a production `.env` file to the repository or build context.

Required values (the Compose file sets the non-secret defaults):

| Variable | Value |
| --- | --- |
| `APP_KEY` | Stable Laravel encryption key. Store in Dokploy's secret environment storage. |
| `APP_URL` | `https://jack.djl.foundation` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `/var/www/html/storage/app/portfolio.sqlite` |
| `SESSION_DRIVER` | `file` |
| `SESSION_SECURE_COOKIE` | `true` |
| `TRUSTED_PROXIES` | `*`; safe here because the container has no published host port and accepts traffic only through Dokploy/Traefik. |
| `CONTENT_SYNC_REPOSITORY` | `/sync` |
| `CONTENT_SYNC_SSH_KEY` | `/run/secrets/portfolio_content_sync_deploy_key` |
| `CONTENT_SYNC_KNOWN_HOSTS` | `/run/configs/portfolio_github_known_hosts` |

The Compose file also sets `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `LOG_CHANNEL=stderr`, `STATAMIC_PRO_ENABLED=false`, `STATAMIC_GIT_ENABLED=false`, `CV_LUALATEX_BINARY=lualatex`, and the `content-sync` branch. Native Statamic Core login, passkeys, 2FA, and existing request rate limits remain in use.

`APP_KEY` is required for Laravel encryption, encrypted CV capability records, and Statamic's encrypted 2FA values. If existing protected CV fields were encrypted under an earlier key, deploy with that exact key. Do not rotate it casually: a different key makes existing encrypted private values and saved 2FA state unreadable. Keep one protected backup copy of the key outside this repository and Dokploy's container filesystem.

At startup, the image creates the SQLite file if absent, caches config from Dokploy's runtime environment, runs Laravel migrations, and refreshes the Statamic Stache. The entire `storage` tree and `users` directory are host-persistent, so session cookies, CV access rows, WebAuthn credentials, 2FA state, Stache files, and Laravel runtime files survive image replacement. No portfolio data is placed in Dokploy's internal PostgreSQL database.

## Routing `jack.djl.foundation`

Use the existing remotely managed Cloudflare Tunnel and Dokploy Traefik route:

1. In Dokploy, add domain `jack.djl.foundation` to the `portfolio` service on container port `8080`. Create the HTTP router only; do not request a Traefik/Let's Encrypt certificate at the origin.
2. In the existing Cloudflare Tunnel's public hostnames, add `jack.djl.foundation` with service `http://127.0.0.1:80`. The tunnel preserves the Host header for Traefik. Cloudflare terminates public HTTPS; the tunnel-to-Traefik hop stays on localhost HTTP.
3. Keep Cloudflare HTTPS enabled for visitors. `APP_URL` stays HTTPS and Laravel's secure session cookie is enabled. No Cloudflare Access or Tailscale access is required for `/cp`.

Do not open Hetzner public ports 80 or 443. The current server design sends Cloudflare Tunnel traffic to Traefik on localhost port 80.

## Content synchronization behavior

`php artisan portfolio:content-sync` runs every five minutes from the image's cron entry. To inspect a run without committing, fetching, or merging:

```sh
php artisan portfolio:content-sync --dry-run
```

The sync command:

1. Takes a non-blocking exclusive `flock`; concurrent invocations skip.
2. Checks the `/sync` repository is on `content-sync`. It stages only `content/`, `public/assets/`, and `public/documents/`, then commits those local Statamic edits before fetching either remote branch.
3. Fetches `origin/main` and `origin/content-sync` without rewriting local history.
4. Integrates both remote tips in the persistent candidate worktree under `storage/app/content-sync-candidate`, then fast-forwards the production worktree only after both merges succeed.
5. Clears and warms the Stache when incoming editorial paths changed, then pushes to `content-sync` with a normal fast-forward push.

An empty sync leaves the branch unchanged. If a remote push races with another update, Git rejects the non-fast-forward push; the local production commit stays in the persistent repository and the next scheduled attempt fetches again. The command never runs `reset --hard` or force-push.

On a merge conflict, the command stops and leaves the candidate merge, conflict list, local production commit, and both input histories available for recovery. The live content tree does not receive conflict markers. Later cron runs report the unresolved candidate and stop.

For conflict recovery, open the running application's terminal from Dokploy and inspect:

```sh
cd /var/www/html/storage/app/content-sync-candidate
git status
git diff --cc
```

Resolve each file deliberately, stage those paths, and finish the merge with `git merge --continue`. Then run `php artisan portfolio:content-sync` in the application container. It will integrate the finished candidate into the live `content-sync` worktree and push normally. Do not use `ours`/`theirs` globally, `reset --hard`, or force-push. Review conflict content in the private server terminal; do not paste private CV values into issue or CI logs.

## Daily squash workflow and build-loop prevention

`.github/workflows/content-sync-squash.yml` runs at noon `Europe/Berlin` and also supports **Actions → Daily content sync squash → Run workflow**. It compares the content/assets/documents trees at `main` and `content-sync`; code-only divergence and an empty editorial diff are no-ops. A difference becomes one squash commit containing only those editorial paths.

The workflow uses a normal push to `main` and rejects a changed main tip. If branch protection rejects that push, it pushes a separate automation branch and opens a PR; it does not bypass protection. Enable **Settings → Actions → General → Allow GitHub Actions to create and approve pull requests** if you want that fallback to create the PR. Keep required review/check rules; merge the PR through the normal protected-branch flow.

When editorial trees differ, the workflow also requires the current `main` commit to be an ancestor of `content-sync`. If the server has not reconciled a recent `main` edit yet, the job fails without creating a squash or PR; wait for the five-minute sync and run it again. A content-only no-op still exits successfully without requiring ancestry.

The workflow never writes `content-sync`: the production server is its only automated content writer. After the squash reaches `main`, the next five-minute server sync merges the new main tip back into `content-sync`. A production edit that arrives during the GitHub workflow remains on `content-sync` and is picked up in a later squash. No branch is reset or force-updated.

Dokploy watches application/build paths only, and the Docker build context excludes editorial content/assets. Therefore a content-only `main` squash does not rebuild the app image. Changes under `app/`, `resources/`, `config/`, routes, dependency manifests, Docker files, and migrations still match the Dokploy watch list and trigger normal application builds.

## Backup and restore

GitHub `main` plus `content-sync` hold the canonical content history and uploaded assets, including metadata and publication PDFs. The server repository is normally a working copy. Back up its `.git` directory too: while a conflict or push outage is being recovered, a production edit may be committed locally but not yet pushed to GitHub.

SQLite and the separate user volume are not in Git. Back them up daily to an encrypted off-host repository, and test a restore. To make a consistent SQLite backup from the running container:

```sh
docker exec <portfolio-container> sh -ec \
  'mkdir -p /var/www/html/storage/app/backups && sqlite3 /var/www/html/storage/app/portfolio.sqlite ".backup /var/www/html/storage/app/backups/portfolio-$(date -u +%F).sqlite"'
```

Include `/srv/jack-portfolio/state/users`, the SQLite backup, and `/srv/jack-portfolio/content-repo` (including `.git`) in that encrypted off-host backup. The full `state/storage` tree may also be backed up for faster recovery; it contains sensitive session and private CV files and any in-progress conflict candidate. Keep the encryption credential and `APP_KEY` in a separate secret store. GitHub content backups do not replace these state backups.

To restore, stop the Dokploy app, restore the user directory and SQLite backup to their host paths with owner UID/GID `33`, restore SELinux labels, then start the app. Restore the same `APP_KEY` before startup. Clone/fetch `content-sync` if rebuilding the repository working tree. Run `php artisan portfolio:content-sync --dry-run` and confirm the branch before enabling normal cron.

## Rollback

For an application regression, redeploy the previous successful Dokploy image/build. The persistent content repository, SQLite database, user state, and session storage remain outside that image. Do not roll back content by replacing a volume with an older image copy. Revert editorial changes through Git after reviewing them. Restore a SQLite backup only to recover corrupted or incompatible database state; keep the matching `APP_KEY`.

## PDF requirements

The image installs LuaLaTeX and the packages used by `resources/views/latex/cv.blade.php`: `fontspec`, `xcolor`, `graphicx`, `hyperref`, `tikz`, `array`, `paracol`, `tabularx`, and `needspace`. The repository's Fira Sans font files are copied into the application image. PDF source and auxiliary files stay in the permission-restricted persistent `storage/app/private/cv-latex` directory and are deleted by the renderer after each request.
