#!/bin/sh
set -eu

APP_PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-enabled/000-default.conf

# Provision accounts inside Render's PostgreSQL, before accepting traffic.
if [ "${RENDER:-false}" = "true" ]; then
    ready=false
    attempt=1
    while [ "$attempt" -le 20 ]; do
        if php /var/www/html/QuizWeb/scripts/bootstrap_render.php; then
            ready=true
            break
        fi
        echo "Waiting for PostgreSQL before loading sample data (attempt ${attempt}/20)..."
        attempt=$((attempt + 1))
        sleep 2
    done
    if [ "$ready" != "true" ]; then
        echo "Render PostgreSQL provisioning failed; refusing to start an incomplete deployment." >&2
        exit 1
    fi
fi

exec apache2-foreground
