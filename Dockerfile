FROM php:8.2-apache

RUN a2enmod rewrite

RUN docker-php-ext-install \
        mysqli \
        pdo \
        pdo_mysql

RUN apt-get update && apt-get install -y \
        libzip-dev \
        unzip \
    && docker-php-ext-install zip mbstring

COPY . /var/www/html/
WORKDIR /var/www/html/

EXPOSE 80

RUN chmod -R 775 /var/www/html

CMD ["apache2-foreground"]
