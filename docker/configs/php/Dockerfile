# Immagine WordPress di Forestas: PHP 8.3 + Apache, estensioni richieste da WordPress e WP-CLI.
# Il core di WordPress NON sta nell'immagine: lo scarica init-wordpress.sh nel volume.
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libzip-dev \
        libmagickwand-dev mariadb-client curl ca-certificates unzip less \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" mysqli gd intl zip exif opcache \
    && pecl install imagick \
    && docker-php-ext-enable imagick

RUN a2enmod rewrite headers

RUN curl -fsSL -o /usr/local/bin/wp \
        https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

COPY docker/configs/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/configs/php/wordpress.ini /usr/local/etc/php/conf.d/wordpress.ini
COPY docker/scripts/init-wordpress.sh /usr/local/bin/init-wordpress.sh
COPY docker/scripts/entrypoint.sh /usr/local/bin/wp-forestas-entrypoint
RUN chmod +x /usr/local/bin/init-wordpress.sh /usr/local/bin/wp-forestas-entrypoint

ENTRYPOINT ["wp-forestas-entrypoint"]
CMD ["apache2-foreground"]
