#!/usr/bin/env sh
set -e

php -r "if (PHP_VERSION_ID < 80401) { fwrite(STDERR, 'CourtConnect requires PHP 8.4.1 or higher. Start it with docker compose up --build, or update your PHP runtime.'.PHP_EOL); exit(1); }"

if [ ! -f .env ]; then
    cp .env.example .env
fi

composer install --no-interaction --prefer-dist

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
fi

php artisan migrate --seed --force

exec "$@"
