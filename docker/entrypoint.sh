#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    if [ -f .env-example ]; then
        cp .env-example .env
        echo "AVISO: .env criado a partir de .env-example — edite EMAIL_DEV e AUTH_MODE no host e reinicie."
    else
        echo "ERRO: .env ausente. Copie .env-example para .env antes de subir o container."
        exit 1
    fi
fi

php -r "include 'inc/const.php'; file_put_contents('VERSION', VERSION);"

mkdir -p data logs /var/lib/php/sessions
chown -R www-data:www-data data logs /var/lib/php/sessions 2>/dev/null || true
chmod 775 data logs 2>/dev/null || true

if [ "$1" = "nginx-fpm" ]; then
    shift
    php-fpm -D
    exec nginx -g 'daemon off;'
fi

exec "$@"
