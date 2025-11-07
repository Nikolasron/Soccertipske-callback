# Use an official PHP image
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Copy everything into the container
COPY . /app

# Expose the Render port
EXPOSE 10000

# Start the built-in PHP server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]
