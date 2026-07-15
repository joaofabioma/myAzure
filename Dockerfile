FROM php:8.5-fpm-alpine

RUN apk add --no-cache nginx oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        oniguruma-dev \
    && docker-php-ext-install mbstring \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./
RUN apk add --no-cache --virtual .composer-deps git unzip \
    && composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && apk del .composer-deps

COPY . .
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

RUN composer dump-autoload -o \
    && php -r "include 'inc/const.php'; file_put_contents('VERSION', VERSION);" \
    && mkdir -p data logs /var/lib/php/sessions \
    && chown -R www-data:www-data data logs /var/lib/php/sessions \
    && chmod 775 data logs

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["nginx-fpm"]
