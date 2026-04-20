FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    poppler-utils \
    libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# PHP config
RUN echo "post_max_size=30M\nmax_execution_time=120\nmemory_limit=256M" \
    > /usr/local/etc/php/conf.d/custom.ini

# Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

COPY . /var/www/html/

RUN mkdir -p /var/www/html/tmp \
    && chmod 777 /var/www/html/tmp \
    && chown -R www-data:www-data /var/www/html

COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
