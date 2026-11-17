#!/bin/bash

cd /var/www/html || exit 1

/usr/bin/flock -s -F /var/www/html/storage/logs/content-sync-control.log.lock \
    /bin/bash -o pipefail -c 'php artisan portfolio:content-sync 2>&1 | tee -a /var/www/html/storage/logs/content-sync-control.log >/dev/null'
sync_status=$?

php artisan portfolio:content-sync:compact-log >/dev/null 2>&1
compact_status=$?

if [ "$sync_status" -ne 0 ]; then
    exit "$sync_status"
fi

exit "$compact_status"
