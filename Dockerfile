# Use the official PHP Apache image
FROM php:8.2-apache

# Enable mod_rewrite (optional, useful for pretty URLs)
RUN a2enmod rewrite

# Copy your app into the container
COPY public/ /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port (Render will use this automatically)
EXPOSE 80
