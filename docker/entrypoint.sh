#!/bin/sh

# ─── Wait for MySQL to be ready ────────────────────────────────────
echo "Waiting for MySQL to be ready..."
until php -r "
  \$conn = @fsockopen(
    getenv('DB_HOST'),
    getenv('DB_PORT') ?: 3306,
    \$errno,
    \$errstr,
    3
  );
  if (\$conn) { fclose(\$conn); exit(0); } exit(1);
"; do
  echo "MySQL not ready yet... retrying in 3s"
  sleep 3
done
echo "MySQL is ready!"

# Fix storage permissions FIRST before anything runs
chown -R www-data:www-data /var/www/html/backend/storage
chmod -R 775 /var/www/html/backend/storage

# Set worker count
WORKER_COUNT=${QUEUE_WORKER_COUNT:-1}
sed -i "s/numprocs=1/numprocs=$WORKER_COUNT/" /etc/supervisor/conf.d/supervisord.conf

# Run migrations
php /var/www/html/backend/artisan migrate --force


# Run seeders
php /var/www/html/backend/artisan db:seed --force

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
