# Test image: PHP CLI with the extensions the suite needs.
#
# The production image excludes tests and dev dependencies by design. This image
# mounts the working tree so you can run the suite in a container without
# installing PHP, Composer, or extensions on the host.

FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.11 /usr/bin/composer /usr/bin/composer

WORKDIR /app
