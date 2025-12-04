FROM php:8.2-apache

WORKDIR /var/www/html

COPY . /var/www/html/

RUN a2enmod rewrite

# Install cURL extension for PHP
RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Permissions
RUN touch unlock_tracker.json \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 unlock_tracker.json

EXPOSE 80

CMD ["apache2-foreground"]
