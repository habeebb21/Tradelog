#!/bin/sh

# Generate APP_KEY if not provided
if [ -z "$APP_KEY" ]; then
    echo "Generating APP_KEY..."
    export APP_KEY=$(php artisan key:generate --show)
fi

# Set APP_URL from Railway domain if available
if [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
    export APP_URL="https://$RAILWAY_PUBLIC_DOMAIN"
    echo "APP_URL set to $APP_URL"
fi

# Fix database permissions
if [ "$DB_CONNECTION" = "sqlite" ]; then
    touch "$DB_DATABASE" 2>/dev/null || true
    chown -R www-data:www-data /var/www/html/database
    chmod -R 775 /var/www/html/database
fi

# Run migrations
php artisan migrate --force

# Cache config
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start PHP-FPM
php-fpm -D
echo "PHP-FPM started"
sleep 2

exec "$@"
