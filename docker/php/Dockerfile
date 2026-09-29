# syntax=docker/dockerfile:1
#
# One image family for every environment (ADR-0012):
#   development  → used by compose.yaml (code is bind-mounted, runs as your host user)
#   production   → used by compose.prod.yaml on the AWS VM (code and assets baked in)
#
# Base image: serversideup/php (PHP-FPM + NGINX on Debian trixie, unprivileged www-data,
# NGINX listening on 8080). Pinned to a stable release; Dependabot proposes upgrades.

############################################
# Base: PHP 8.5 + the extensions this app needs on top of the image defaults
# (the image already ships pdo_pgsql, pcntl, zip, opcache, redis).
############################################
FROM serversideup/php:8.5-fpm-nginx-trixie-v4.5.1 AS base

USER root
# intl  → Spanish number/date formatting (Number::format, Carbon locales)
# gd    → image thumbnails for phones (ADR-0007)
# exif  → read photo orientation before generating thumbnails
RUN install-php-extensions intl gd exif
USER www-data

############################################
# Development: same image, but PHP runs with your host UID/GID so files created
# inside the container (migrations, logs, vendor/) stay owned by you on Linux/WSL2.
############################################
FROM base AS development

ARG USER_ID=1000
ARG GROUP_ID=1000

USER root
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} \
 && docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service nginx
USER www-data

############################################
# Production build stages
############################################
FROM base AS vendor
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

FROM node:24-trixie-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
# Tailwind and Flux read Blade views and CSS from vendor/.
COPY --from=vendor /var/www/html/vendor ./vendor
# Vite bakes VITE_* values into the JavaScript at build time; .env is not in the build context
# (.dockerignore), so compose.prod.yaml passes them as build arguments. They are public identifiers that end up in
# the browser anyway (REVERB_APP_SECRET never enters the image), so Docker's SecretsUsedInArgOrEnv warning is expected.
ARG VITE_APP_NAME="SETI Activos"
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME
RUN npm run build

############################################
# Production: code, vendor and built assets baked into the image; no bind mounts.
############################################
FROM base AS production

ENV PHP_OPCACHE_ENABLE=1 \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_STORAGE_LINK=false

COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction
