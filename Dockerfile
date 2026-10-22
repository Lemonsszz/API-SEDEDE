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

# Límites de subida. El valor por defecto de PHP es upload_max_filesize=2M, muy
# por debajo de una foto de celular (3-12 MB): el archivo se descarta y el
# panel responde "failed to upload"; si además supera post_max_size (8M por
# defecto), PHP tira el cuerpo completo del POST y el formulario parece
# reiniciarse. post_max_size siempre tiene que ser mayor que
# upload_max_filesize. memory_limit se sube porque Cloudinary y la validación
# de imágenes trabajan con el archivo en memoria.
RUN printf '%s\n' \
      'upload_max_filesize = 20M' \
      'post_max_size = 25M' \
      'memory_limit = 256M' \
      'max_execution_time = 120' \
      'max_input_time = 120' \
      > /usr/local/etc/php/conf.d/zz-uploads.ini

# La imagen oficial marca el binario de FrankenPHP con la capacidad de archivo
# `cap_net_bind_service` (setcap) para poder escuchar en el puerto 80 sin ser
# root. El sandbox de Render no admite capacidades de archivo: al ejecutar el
# binario devuelve "Operation not permitted" y el contenedor muere con estado
# 126. Aquí el servidor ya escucha en $PORT (10000, que no es privilegiado),
# así que la capacidad sobra. Se copia el binario a una ruta nueva —que no
# arrastra el atributo de capacidad— y se usa esa.
RUN cp /usr/local/bin/frankenphp /usr/local/bin/frankenphp-run \
 && chmod 0755 /usr/local/bin/frankenphp-run \
 && { setcap -r /usr/local/bin/frankenphp-run || true; } \
 && ! getcap /usr/local/bin/frankenphp-run | grep -q .

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
CMD ["frankenphp-run", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]
