#!/bin/sh

php-fpm -D

php /var/www/artisan schedule:work --no-interaction &

nginx -g "daemon off;"
