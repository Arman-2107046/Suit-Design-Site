#!/bin/sh
# Gets the app ready before nginx and PHP start serving it. Runs in the "app"
# container only (PREPARE_APP=true); the scheduler shares the image but skips it.
set -e

[ "${PREPARE_APP:-false}" = "true" ] || exit 0

cd /var/www/html

if [ ! -f .env ]; then
    echo "prepare: no .env yet, starting from .env.example"
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ]; then
    echo "prepare: installing PHP dependencies (first start takes a minute)"
    composer install --no-interaction --prefer-dist --no-progress
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "prepare: generating the application key"
    php artisan key:generate --force
fi

echo "prepare: waiting for the database"
tries=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }' 2>/dev/null; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
        echo "prepare: the database did not answer within two minutes"
        exit 1
    fi
    sleep 2
done

php artisan migrate --force
php artisan storage:link >/dev/null 2>&1 || true

# Clear anything cached from another environment (Herd), so this one's settings apply.
php artisan optimize:clear >/dev/null

echo "prepare: ready on ${APP_URL}"
