#!/bin/sh
set -e

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

# Run migrations (fail-fast: container must not start with unapplied migrations)
echo "Running migrations..."
php /var/www/html/backend/artisan migrate --force
echo "Migrations complete."

# Run seeders (idempotent — uses firstOrCreate to preserve user customizations)
php /var/www/html/backend/artisan db:seed --force

# Install crontab for Laravel scheduler (runs every minute, scheduler decides what to execute)
# Export current env so cron jobs have access to all Docker env vars
env | sed 's/=\(.*\)/="\1"/' > /etc/crontab.env
echo "* * * * * . /etc/crontab.env; cd /var/www/html/backend && php artisan schedule:run >> /dev/null 2>&1" | crontab -

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
