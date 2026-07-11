#!/bin/sh

# Generate APP_KEY if not provided
if [ -z "$APP_KEY" ]; then
    echo "No APP_KEY provided, generating one..."
    export APP_KEY=$(php artisan key:generate --show)
    echo "Generated APP_KEY: $APP_KEY"
fi

# Set APP_URL from RAILWAY_PUBLIC_DOMAIN if available
if [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
    export APP_URL="https://$RAILWAY_PUBLIC_DOMAIN"
    echo "Set APP_URL to $APP_URL"
fi

# Railway sets PORT — pass it to nginx as NGINX_PORT
export NGINX_PORT=${PORT:-80}
echo "Nginx will listen on port $NGINX_PORT"

# Substitute NGINX_PORT into nginx config
sed -i "s/\${NGINX_PORT}/$NGINX_PORT/g" /etc/nginx/sites-available/default

# Create SQLite database if it doesn't exist
if [ "$DB_CONNECTION" = "sqlite" ] && [ ! -f "$DB_DATABASE" ]; then
    echo "Creating SQLite database file..."
    touch "$DB_DATABASE"
    chown www-data:www-data "$DB_DATABASE"
    chmod 664 "$DB_DATABASE"
fi

# Ensure proper permissions on database directory
if [ "$DB_CONNECTION" = "sqlite" ]; then
    chown -R www-data:www-data /var/www/html/database
    chmod -R 775 /var/www/html/database
fi

# Run migrations
php artisan migrate --force 2>/dev/null || true

# Clear and cache config for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start PHP-FPM in background first, wait for it to be ready
/usr/local/sbin/php-fpm -D
echo "Waiting for PHP-FPM to start..."
sleep 2

# Execute the main command (supervisord handles nginx + worker)
exec "$@"
