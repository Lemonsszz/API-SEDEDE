#!/bin/sh
set -e

# El caché de configuración y de rutas se genera en CADA arranque y no durante
# el build de la imagen: las variables de entorno las inyecta el hosting y no
# existen todavía cuando se construye el contenedor.
#
# route:cache requiere que las rutas no usen closures; por eso la ruta
# /api/usuario vive en AuthController y no como función anónima en routes/api.php.

php artisan config:cache
php artisan route:cache

exec "$@"
