#!/bin/sh
set -e
cd /var/www/html

# vendor lives in a named volume — install once if empty
if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] Installing Composer dependencies into volume..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

exec "$@"
