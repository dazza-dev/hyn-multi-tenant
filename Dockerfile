# Development and CI image for running the test suite.
#
# The PHP version is a build argument so the same image definition can be used
# across the whole support matrix:
#
#   docker compose build --build-arg PHP_VERSION=8.2
#
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpq-dev \
        libpng-dev \
        default-mysql-client \
        postgresql-client \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_pgsql \
        zip \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php", "-v"]
