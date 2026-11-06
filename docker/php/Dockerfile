FROM php:8.4-fpm-alpine

ARG UID=1000
ARG GID=1000

# pdo_mysql - MySQL; sockets - php-amqplib (RabbitMQ); pcntl - graceful stop of long-running workers;
# zip - Composer; opcache - performance.
RUN apk add --no-cache libzip unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql sockets pcntl zip opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Upload limits are taken from the environment (see docker-compose.yml and .env).
ENV PHP_UPLOAD_MAX_FILESIZE=12M \
    PHP_POST_MAX_SIZE=12M

COPY php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

# Run as the host user so files created in the bind-mounted project stay editable on the host.
RUN addgroup -g "$GID" app \
    && adduser -D -u "$UID" -G app app

USER app

WORKDIR /var/www/html
