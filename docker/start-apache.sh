#!/bin/sh
set -eu

APP_PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-enabled/000-default.conf

# Render supplies PostgreSQL through environment variables. Seed the idempotent
# demo cohort during startup so test users are real persisted database records.
if [ -n "${QUIZWEB_DB_HOST:-}" ]; then
    attempt=1
    while [ "$attempt" -le 20 ]; do
        if php /var/www/html/QuizWeb/scripts/seed_sample_data.php; then
            break
        fi
        echo "Waiting for PostgreSQL before loading sample data (attempt ${attempt}/20)..."
        attempt=$((attempt + 1))
        sleep 2
    done
fi

exec apache2-foreground
