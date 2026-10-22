# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# API SEDEDE (Laravel 12) — imagen de producción
#
# FrankenPHP = un solo proceso que sirve HTTP y ejecuta PHP con FPM.
# La misma imagen sirve para el hosting gratuito de prueba (Render) y para
# el VPS final, así que la migración es solo cambiar variables de entorno.
# ---------------------------------------------------------------------------

# --- Base: FrankenPHP + las extensiones que necesita Laravel ---------------
FROM dunglas/frankenphp:php8.4-alpine AS base

# pdo_pgsql → conexión a Supabase (Postgres)
# opcache   → caché de opcode de PHP
# @composer → binario de Composer para instalar las dependencias
RUN install-php-extensions pdo_pgsql opcache @composer

# CA de certificados. En Alpine el bundle ya está en el sistema, pero se
# apunta explícitamente: sin esto PHP no puede validar el TLS de Cloudinary
# (el mismo error "cURL error 60" que se corrigió en el equipo local).
RUN echo "curl.cainfo=/etc/ssl/certs/ca-certificates.crt"  > /usr/local/etc/php/conf.d/zz-ca.ini \
 && echo "openssl.cafile=/etc/ssl/certs/ca-certificates.crt" >> /usr/local/etc/php/conf.d/zz-ca.ini

# Composer se ejecuta como root dentro de la imagen.
ENV COMPOSER_ALLOW_SUPERUSER=1

# --- Etapa 1: dependencias de PHP ------------------------------------------
# Se usa la misma base que en producción para que la versión de PHP y las
# extensiones sean idénticas en las dos etapas (Laravel 12 con Symfony 7
# exige PHP >= 8.4).
FROM base AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts: no se ejecuta `artisan package:discover` durante el build.
# --no-autoloader: el autoload se genera después, con el código de la app.
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-interaction \
      --no-progress

COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction --no-scripts

# --- Etapa 2: imagen final --------------------------------------------------
FROM base AS runtime

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor ./vendor

# Laravel escribe en storage/ y bootstrap/cache/ en tiempo de ejecución.
# Caddy (el servidor de FrankenPHP) escribe su almacenamiento en /data y /config.
RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
             /tmp/frankenphp \
 && chown -R www-data:www-data storage bootstrap/cache /tmp/frankenphp /data /config

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
