FROM php:8.2-fpm

# Install nginx + dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    poppler-utils \
    libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

# Copy PHP files
COPY . /var/www/html/
RUN mkdir -p /var/www/html/tmp \
    && chmod 777 /var/www/html/tmp \
    && chown -R www-data:www-data /var/www/html

# Start script
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
