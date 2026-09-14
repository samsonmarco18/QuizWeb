#!/bin/sh
set -eu

APP_PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-enabled/000-default.conf

exec apache2-foreground
