
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql mysqli curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && rm -f /var/www/html/config.local.php \
    && chown -R www-data:www-data /var/www/html

ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["docker-entrypoint.sh"]
