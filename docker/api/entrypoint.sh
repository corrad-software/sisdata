#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    storage/app/private \
    bootstrap/cache

frontend_url="${FRONTEND_URL:-http://localhost:3000}"
host=$(php -r 'echo parse_url(getenv("FRONTEND_URL") ?: "http://localhost:3000", PHP_URL_HOST) ?: "";')

case "$frontend_url" in
    https:*) export SESSION_SECURE_COOKIE=true ;;
    *) export SESSION_SECURE_COOKIE=false ;;
esac

if [ -n "$host" ]; then
    export SANCTUM_STATEFUL_DOMAINS="$host"
    case "$host" in
        localhost|127.0.0.1) ;;
        *) export SESSION_DOMAIN="$host" ;;
    esac
fi

wait_for_db() {
    echo "Waiting for PostgreSQL..."
    i=0
    until php -r 'new PDO(sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE")), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -gt 60 ]; then
            echo "PostgreSQL did not become ready" >&2
            exit 1
        fi
        sleep 2
    done
}

prepare() {
    wait_for_db
    php artisan package:discover --ansi
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R ug+rwx storage bootstrap/cache
}

role="${1:-web}"

case "$role" in
    web)
        prepare
        php artisan migrate --force
        php artisan storage:link || true
        i=0
        until php artisan sisdata:bucket; do
            i=$((i + 1))
            if [ "$i" -gt 30 ]; then
                echo "Document bucket was not created" >&2
                exit 1
            fi
            sleep 2
        done
        if [ "${SISDATA_RUN_SEED:-false}" = "true" ]; then
            php artisan db:seed --force
        fi
        chown -R www-data:www-data storage bootstrap/cache
        exec supervisord -n -c /etc/supervisor/supervisord.conf
        ;;
    horizon)
        prepare
        exec su -p -s /bin/sh www-data -c "cd /var/www/html && php artisan horizon"
        ;;
    scheduler)
        prepare
        exec su -p -s /bin/sh www-data -c "cd /var/www/html && php artisan schedule:work"
        ;;
    *)
        exec "$@"
        ;;
esac
