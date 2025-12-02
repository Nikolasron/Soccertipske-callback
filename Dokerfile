# Use official PHP image with Apache
FROM php:8.2-apache

# Enable rewrite module
RUN a2enmod rewrite

# Copy all project files into container
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
