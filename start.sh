#!/bin/sh
PORT=${PORT:-80}

cat > /etc/nginx/sites-available/default << NGINX
server {
    listen $PORT;
    root /var/www/html;
    index index.php;

    client_max_body_size 30M;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
        fastcgi_send_timeout 120;
    }

    location ~ /\. {
        deny all;
    }
}
NGINX

php-fpm -D
nginx -g "daemon off;"
