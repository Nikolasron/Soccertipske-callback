# Use official PHP image with built-in Apache
FROM php:8.2-apache

# Copy all project files into the container
COPY . /var/www/html/

# Expose the port Render assigns dynamically
EXPOSE 10000

# Start Apache web server
CMD ["apache2-foreground"]
