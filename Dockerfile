# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Imagen del API SEDEDE (Laravel 12)
#
# Se usa FrankenPHP: un solo proceso que sirve HTTP y ejecuta PHP con FPM.
# Es la opción que recomienda Laravel para Docker y sirve igual en el hosting
# gratuito de prueba (Render/Railway) y en el VPS final.
# ---------------------------------------------------------------------------

# --- Etapa 1: dependencias de PHP ------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY . .

RUN composer install \
      --no-dev \
      --prefer-dist \
      --no-interaction \
      --no-progress \
      --optimize-autoloader

# --- Etapa 2: imagen final --------------------------------------------------
FROM dunglas/frankenphp:php8.4-alpine AS runtime

# pdo_pgsql → conexión a Supabase (Postgres)
# opcache   → caché de opcode de PHP
# pcntl     → workers de cola
# intl      → formato de fechas y números
RUN install-php-extensions pdo_pgsql opcache pcntl intl

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor

# Laravel necesita escribir en storage/ y bootstrap/cache/ en tiempo de ejecución
RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
             /tmp/frankenphp \
 && chown -R www-data:www-data storage bootstrap/cache /tmp/frankenphp

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
