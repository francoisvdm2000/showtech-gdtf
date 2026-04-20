FROM php:8.2-fpm

# Install nginx + dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    poppler-utils \
    libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# PHP config : uploads 20M, timeout 120s, mémoire 256M
RUN echo "upload_max_filesize=20M\npost_max_size=20M\nmax_execution_time=120\nmemory_limit=256M" \
    > /usr/local/etc/php/conf.d/custom.ini

# Injecte client_max_body_size dans nginx.conf global (bloc http)
RUN sed -i '/http {/a \\tclient_max_body_size 20M;' /etc/nginx/nginx.conf

# Nginx config du site
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
