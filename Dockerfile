FROM oven/bun:1.4.2 AS frontend
WORKDIR /var/www/html
COPY package.json bun.lock ./
RUN bun install --frozen-lockfile
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN bun run build

FROM docker.io/library/composer:2 AS composer-bin

FROM php:8.4-fpm-bookworm AS application
ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        cron \
        curl \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libsqlite3-dev \
        libxml2-dev \
        libzip-dev \
        nginx \
        openssh-client \
        supervisor \
        sqlite3 \
        texlive-fonts-recommended \
        texlive-latex-extra \
        texlive-latex-recommended \
        texlive-luatex \
        texlive-pictures \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_sqlite \
        sockets \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY . .
RUN mkdir -p /var/www/html/storage/app /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views \
        /var/www/html/storage/logs /var/www/html/users /run/php \
    && composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/users

COPY --from=frontend /var/www/html/public/build /var/www/html/public/build
COPY docker /tmp/portfolio-docker
RUN install -m 0644 /tmp/portfolio-docker/nginx.conf /etc/nginx/sites-available/default \
    && install -m 0644 /tmp/portfolio-docker/supervisord.conf /etc/supervisor/conf.d/portfolio.conf \
    && install -m 0644 /tmp/portfolio-docker/php.ini /usr/local/etc/php/conf.d/zz-portfolio.ini \
    && install -m 0644 /tmp/portfolio-docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-portfolio.conf \
    && install -m 0755 /tmp/portfolio-docker/entrypoint.sh /usr/local/bin/portfolio-entrypoint \
    && install -m 0644 /tmp/portfolio-docker/cron.d/content-sync /etc/cron.d/portfolio-content-sync \
    && rm -rf /tmp/portfolio-docker \
    && php -r 'exit(extension_loaded("pdo_sqlite") && extension_loaded("gd") && extension_loaded("intl") && extension_loaded("sockets") ? 0 : 1);' \
    && command -v lualatex >/dev/null \
    && kpsewhich paracol.sty >/dev/null \
    && kpsewhich fontspec.sty >/dev/null

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD curl --fail --silent --show-error http://127.0.0.1:8080/up >/dev/null || exit 1

ENTRYPOINT ["/usr/local/bin/portfolio-entrypoint"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
