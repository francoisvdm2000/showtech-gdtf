FROM php:8.2-apache

# Install pdftotext and zip support
RUN apt-get update && apt-get install -y \
    poppler-utils \
    libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules (sans MPM car déjà inclus dans l'image)
RUN a2enmod rewrite headers

# Copy PHP files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/tmp \
    && chmod 777 /var/www/html/tmp

EXPOSE 80
