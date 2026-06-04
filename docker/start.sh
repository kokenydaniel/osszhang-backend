#!/bin/sh

mkdir -p /var/www/storage/app/private /var/www/storage/app/public /var/www/storage/framework/cache /var/www/storage/framework/sessions /var/www/storage/framework/views /var/www/storage/logs
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

php-fpm -D

nginx -g "daemon off;"
