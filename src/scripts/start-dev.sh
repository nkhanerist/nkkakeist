#!/usr/bin/env bash

set -euo pipefail

cd /var/www/html

mkdir -p storage/framework

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ] || [ ! -f storage/framework/.composer.lock.sha ] || \
    [ "$(sha256sum composer.lock | awk '{print $1}')" != "$(cat storage/framework/.composer.lock.sha 2>/dev/null || true)" ]; then
    composer install
    sha256sum composer.lock | awk '{print $1}' > storage/framework/.composer.lock.sha
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

if [ ! -d node_modules ] || [ ! -f storage/framework/.package-lock.sha ] || \
    [ "$(sha256sum package-lock.json | awk '{print $1}')" != "$(cat storage/framework/.package-lock.sha 2>/dev/null || true)" ]; then
    npm install
    sha256sum package-lock.json | awk '{print $1}' > storage/framework/.package-lock.sha
fi

php artisan migrate --force

npm run dev > storage/logs/vite.log 2>&1 &
VITE_PID=$!

for _ in $(seq 1 30); do
    if [ -f public/hot ]; then
        break
    fi
    sleep 1
done

php artisan serve --host=0.0.0.0 --port=8000 &
PHP_PID=$!

cleanup() {
    kill "${VITE_PID}" "${PHP_PID}" 2>/dev/null || true
}

trap cleanup EXIT INT TERM

wait -n "${VITE_PID}" "${PHP_PID}"
