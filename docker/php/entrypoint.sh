#!/bin/sh
set -e
cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] Installing Composer dependencies into Linux volume (not Windows bind-mount)..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

exec "$@"
