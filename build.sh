#!/bin/sh

set -e

# PHP for the build environment
dnf clean metadata
dnf install -y \
    php8.3 \
    php8.3-common \
    php8.3-mbstring \
    php8.3-gd \
    php8.3-bcmath \
    php8.3-xml \
    php8.3-fpm \
    php8.3-intl \
    php8.3-zip \
    wget

curl -fsSL https://bun.sh/install | bash

# Composer
EXPECTED_CHECKSUM="$(wget -q -O - https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    >&2 echo "ERROR: Invalid Composer installer checksum"
    rm composer-setup.php
    exit 1
fi

php composer-setup.php --quiet
rm composer-setup.php

# PHP dependencies
php composer.phar install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Frontend assets
bun ci
bun run build

# Statamic
php please stache:warm -n -q
php please ssg:generate
