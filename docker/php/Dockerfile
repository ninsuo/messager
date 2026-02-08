FROM php:8.4-fpm-alpine AS base

RUN apk --update --no-cache add git g++ gcc libxslt-dev libmcrypt-dev icu-dev vim wget unzip \
      freetype libpng libjpeg-turbo freetype-dev libpng-dev libjpeg-turbo-dev oniguruma-dev bash \
      libsodium-dev libwebp-dev libzip-dev icu-data-full

RUN docker-php-ext-install pdo \
    && docker-php-ext-install intl \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install xsl \
    && docker-php-ext-install ctype \
    && docker-php-ext-install opcache \
    && docker-php-ext-install sodium \
    && docker-php-ext-install zip \
    && docker-php-ext-install bcmath \
    && docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install pcntl \
    && docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd

RUN cd /usr/local/etc/php/conf.d/ \
  && echo 'memory_limit = 256M' >> app.ini \
  && echo 'post_max_size = 20M' >> app.ini \
  && echo 'max_execution_time = 60' >> app.ini \
  && echo 'upload_max_filesize = 10M' >> app.ini \
  && echo 'output_buffering = 4096' >> app.ini \
  && echo 'date.timezone = UTC' >> app.ini \
  && echo 'display_errors = true' >> app.ini \
  && echo 'error_reporting = E_ALL' >> app.ini

RUN cd /usr/local/etc/php-fpm.d/ \
  && echo 'pm = dynamic' >> www.conf \
  && echo 'pm.max_children = 50' >> www.conf \
  && echo 'pm.start_servers = 5' >> www.conf \
  && echo 'pm.min_spare_servers = 3' >> www.conf \
  && echo 'pm.max_spare_servers = 10' >> www.conf \
  && echo 'pm.status_path = /status' >> www.conf

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

RUN chown 1000:1000 /var/www
USER 1000
WORKDIR /var/www

ENTRYPOINT ["php-fpm"]

FROM base AS worker

ENTRYPOINT ["php", "bin/console", "messenger:consume", "async", "-vv", "--time-limit=3600", "--sleep=0.5"]
