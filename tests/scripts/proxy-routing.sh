#!/usr/bin/env bash
set -euo pipefail

# Exercise the production Nginx/PHP image behind the same Traefik entrypoint
# behavior as the Cloudflare Tunnel. The image is supplied by the caller.
if [[ $# -ne 1 ]]; then
    echo "Usage: $0 APPLICATION_IMAGE" >&2
    exit 2
fi

app_image=$1
repo_dir=$(git rev-parse --show-toplevel)
test_dir=$(mktemp -d "${TMPDIR:-/tmp}/portfolio-proxy-routing.XXXXXX")
suffix=${test_dir##*.}
network="portfolio-proxy-$suffix"
app="portfolio-proxy-app-$suffix"
control="portfolio-proxy-control-$suffix"
traefik="portfolio-proxy-traefik-$suffix"

cleanup() {
    podman rm -f "$traefik" "$control" "$app" >/dev/null 2>&1 || true
    podman network rm "$network" >/dev/null 2>&1 || true
    rm -rf -- "$test_dir"
}
trap cleanup EXIT
trap 'exit 130' INT TERM

chmod 755 "$test_dir"
git -C "$repo_dir" archive HEAD content public/assets public/documents | tar -xf - -C "$test_dir"
chmod -R a+rX "$test_dir/content" "$test_dir/public"
mkdir "$test_dir/control"
cat > "$test_dir/control/index.php" <<'PHP'
<?php
header('Content-Type: text/plain');
echo ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'missing').'/'.($_SERVER['HTTP_X_FORWARDED_PORT'] ?? 'missing');
PHP
cat > "$test_dir/routes.yml" <<'YAML'
http:
  routers:
    portfolio:
      rule: Host(`jack.djl.foundation`)
      entryPoints: [web]
      service: portfolio
    control:
      rule: Host(`control.test`)
      entryPoints: [web]
      service: control
  services:
    portfolio:
      loadBalancer:
        servers:
          - url: http://app:8080
    control:
      loadBalancer:
        servers:
          - url: http://control:9080
YAML

podman network create "$network" >/dev/null
podman run -d --rm --name "$app" --network "$network" --network-alias app \
    -e APP_ENV=production -e APP_DEBUG=false \
    -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    -e APP_URL=https://jack.djl.foundation -e 'TRUSTED_PROXIES=*' \
    -e DB_CONNECTION=sqlite -e DB_DATABASE=/var/www/html/storage/app/probe.sqlite \
    -e SESSION_DRIVER=file -e CACHE_STORE=file -e STATAMIC_STACHE_WATCHER=auto \
    -v "$test_dir/content:/var/www/html/content:ro,Z" \
    -v "$test_dir/public/assets:/var/www/html/public/assets:ro,Z" \
    -v "$test_dir/public/documents:/var/www/html/public/documents:ro,Z" \
    --entrypoint /bin/sh "$app_image" \
    -c 'mkdir -p storage/app/private/latex-home && chown -R www-data:www-data storage/app/private && touch storage/app/probe.sqlite && chown www-data:www-data storage/app/probe.sqlite && runuser -u www-data -- php artisan config:cache --no-interaction && runuser -u www-data -- php artisan migrate --force --no-interaction && runuser -u www-data -- php artisan statamic:stache:refresh --no-interaction && php-fpm -D && exec nginx -g "daemon off;"' >/dev/null
podman run -d --rm --name "$control" --network "$network" --network-alias control \
    -v "$test_dir/control:/probe:ro,Z" --entrypoint php "$app_image" \
    -S 0.0.0.0:9080 -t /probe >/dev/null
podman run -d --rm --name "$traefik" --network "$network" -p 127.0.0.1::8082 \
    -v "$test_dir/routes.yml:/etc/traefik/routes.yml:ro,Z" \
    docker.io/library/traefik:v3.6.7 --entrypoints.web.address=:8082 \
    --providers.file.filename=/etc/traefik/routes.yml >/dev/null
address=$(podman port "$traefik" 8082/tcp | head -1)
port=${address##*:}
base="http://127.0.0.1:$port"

for attempt in {1..60}; do
    if [[ $(curl -sS --max-time 2 -H 'Host: jack.djl.foundation' -o /dev/null -w '%{http_code}' "$base/up" 2>/dev/null || true) == 200 ]]; then
        break
    fi
    if [[ $attempt -eq 60 ]]; then echo 'Application did not become ready' >&2; exit 1; fi
    sleep 1
done

check_status() {
    local path=$1 expected=$2 actual
    actual=$(curl -sS --max-time 60 -H 'Host: jack.djl.foundation' -H 'X-Forwarded-Proto: https' -o /dev/null -w '%{http_code}' "$base$path")
    printf '%s: %s (expected %s)\n' "$path" "$actual" "$expected"
    [[ $actual == "$expected" ]]
}

check_status / 200
check_status /projekte/prtop 200
check_status /projekte 200
check_status /cv 200
check_status /cv/pdf 200
check_status /up 200
check_status /projekte/not-a-published-project 404
private_status=$(curl -sS --max-time 10 -H 'Host: jack.djl.foundation' -H 'X-Forwarded-Proto: https' \
    -H 'Content-Type: application/json' -d '{"path":"/cv"}' -o /dev/null -w '%{http_code}' "$base/cv/private-data")
printf 'Private CV without authorization: %s (expected 404)\n' "$private_status"
[[ $private_status == 404 ]]
curl -sS --max-time 60 -H 'Host: jack.djl.foundation' -H 'X-Forwarded-Proto: https' -o "$test_dir/cv.pdf" "$base/cv/pdf"
[[ $(head -c 4 "$test_dir/cv.pdf") == '%PDF' ]]
control_headers=$(curl -sS --max-time 10 -H 'Host: control.test' -H 'X-Forwarded-Proto: https' "$base/")
printf 'Unrelated service forwarded protocol/port: %s\n' "$control_headers"
[[ $control_headers == 'http/80' ]]
