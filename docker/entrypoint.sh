#!/bin/sh
# Entrypoint del contenedor de la API.
#
# Se ejecuta en cada arranque: cachea la configuración y las rutas para que
# Laravel no tenga que recompilarlas en cada petición. Los dos cacheos son
# opcionales: si fallan (por ejemplo, por una variable de entorno incompleta)
# la aplicación sigue funcionando sin caché, así que no son fatales.
set -e

cd /app

php artisan config:cache || echo "aviso: no se pudo cachear la configuracion, se sigue sin cache"
php artisan route:cache  || echo "aviso: no se pudo cachear las rutas, se sigue sin cache"

exec "$@"
