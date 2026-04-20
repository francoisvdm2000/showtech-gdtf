#!/bin/sh
# Railway injecte $PORT dynamiquement
PORT=${PORT:-80}

# Remplace le port dans nginx config
sed -i "s/listen 80/listen $PORT/" /etc/nginx/sites-available/default

# Démarre php-fpm puis nginx
php-fpm -D
nginx -g "daemon off;"
