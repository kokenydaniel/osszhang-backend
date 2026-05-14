#!/bin/sh

# Start PHP-FPM in background
php-fpm -D

# Run migrations (Optional: better to do this manually or in CI/CD, but for start it's okay)
# php artisan migrate --force

# Start Nginx in foreground
nginx -g "daemon off;"
