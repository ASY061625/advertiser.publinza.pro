FROM php:8.3-fpm-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
        bash git icu-dev libzip-dev oniguruma-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath intl opcache pcntl pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/publinza.ini

# Match the host user so files written in the container stay editable.
RUN addgroup -g ${GID} -S app \
    && adduser -u ${UID} -S app -G app \
    && chown -R app:app /var/www

WORKDIR /var/www/html
USER app

EXPOSE 9000
CMD ["php-fpm"]
