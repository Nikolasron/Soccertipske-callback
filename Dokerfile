# Use official PHP Apache image
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Copy all files to container
COPY . /var/www/html/

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set proper permissions for Render to write files
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Expose default Apache port
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
