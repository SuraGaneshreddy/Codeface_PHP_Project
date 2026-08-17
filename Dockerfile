# Codeface — all-in-one image: PHP 8.2 + Apache + PDO (MySQL driver; SQLite ships built-in)
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/
RUN mkdir -p /var/www/html/database/data/avatars \
    && chown -R www-data:www-data /var/www/html/database/data

EXPOSE 80
# Default: zero-config SQLite (ephemeral). For persistent MySQL pass env vars:
#   CODEFACE_DB_DRIVER=mysql CODEFACE_DB_HOST=... CODEFACE_DB_PORT=3306
#   CODEFACE_DB_USER=... CODEFACE_DB_PASS=... CODEFACE_DB_NAME=codeface
