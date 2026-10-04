#!/usr/bin/env bash
set -euo pipefail

remote="${CONTENT_SYNC_REMOTE:-origin}"
main_ref="${CONTENT_SYNC_MAIN_REF:-refs/remotes/${remote}/main}"
content_ref="${CONTENT_SYNC_CONTENT_REF:-refs/remotes/${remote}/content-sync}"

main_sha="$(git rev-parse --verify "${main_ref}^{commit}")"
content_sha="$(git rev-parse --verify "${content_ref}^{commit}")"

if git diff --quiet "$main_sha" "$content_sha" -- content public/assets public/documents; then
    echo "No editorial content diverges from main; nothing to squash."
    if [ -n "${GITHUB_OUTPUT:-}" ]; then
        {
            echo "changed=false"
            echo "base_sha=$main_sha"
        } >> "$GITHUB_OUTPUT"
    fi
    exit 0
fi

if ! git merge-base --is-ancestor "$main_sha" "$content_sha"; then
    echo "content-sync has not integrated the current main tip; refusing to squash a stale editorial tree. Retry after the production server reconciles main." >&2
    exit 3
fi

git switch --detach "$main_sha"
git restore --source="$content_sha" --staged --worktree -- content public/assets public/documents

if git diff --cached --quiet -- content public/assets public/documents; then
    echo "Editorial paths have no staged difference after materializing content-sync."
    if [ -n "${GITHUB_OUTPUT:-}" ]; then
        {
            echo "changed=false"
            echo "base_sha=$main_sha"
        } >> "$GITHUB_OUTPUT"
    fi
    exit 0
fi

git -c user.name='Portfolio Content Sync' \
    -c user.email='portfolio-content-sync@users.noreply.github.com' \
    commit --no-gpg-sign -m 'content: squash editorial changes from content-sync'

latest_main="$(git ls-remote --heads "$remote" refs/heads/main | awk 'NR == 1 { print $1 }')"
if [ "$latest_main" != "$main_sha" ]; then
    echo "Remote main changed during the squash; no push or PR was created. Rerun against the new main tip." >&2
    exit 2
fi

if [ -n "${GITHUB_OUTPUT:-}" ]; then
    {
        echo "changed=true"
        echo "base_sha=$main_sha"
        echo "commit_sha=$(git rev-parse HEAD)"
        echo "content_sha=$content_sha"
    } >> "$GITHUB_OUTPUT"
fi

echo "Created one editorial squash commit from content-sync."
