# Use an official PHP + Apache image
FROM php:8.2-apache

# Enable Apache Rewrite Engine
RUN a2enmod rewrite

# Copy project files into container
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80 for web traffic
EXPOSE 80
