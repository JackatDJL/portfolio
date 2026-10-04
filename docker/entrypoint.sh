#!/bin/sh
set -eu

cd /var/www/html

if [ "${APP_ENV:-}" != "production" ]; then
    echo "APP_ENV must be production for this image." >&2
    exit 1
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY must be configured before the application can start." >&2
    exit 1
fi

if [ ! -d /sync/.git ] && [ ! -f /sync/.git ]; then
    echo "The persistent content repository must be mounted at /sync." >&2
    exit 1
fi

if ! find /var/www/html/users -maxdepth 1 -type f -name '*.yaml' -print -quit | grep -q .; then
    echo "Seed the persistent Statamic users directory before starting the application." >&2
    exit 1
fi

mkdir -p \
    storage/app/private \
    storage/app/private/latex-home \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/locks \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/statamic
chown -R www-data:www-data storage users

database_path="${DB_DATABASE:-storage/app/portfolio.sqlite}"
case "$database_path" in
    /*) ;;
    *) database_path="/var/www/html/$database_path" ;;
esac
mkdir -p "$(dirname "$database_path")"
if [ ! -e "$database_path" ]; then
    install -o www-data -g www-data -m 0660 /dev/null "$database_path"
fi
chown www-data:www-data "$database_path"

runuser -u www-data -- php artisan config:clear --no-interaction
runuser -u www-data -- php artisan config:cache --no-interaction
runuser -u www-data -- php artisan migrate --force --no-interaction
runuser -u www-data -- php artisan statamic:stache:refresh --no-interaction

exec "$@"
