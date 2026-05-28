#!/bin/sh

# Set worker count
WORKER_COUNT=${QUEUE_WORKER_COUNT:-1}
sed -i "s/numprocs=1/numprocs=$WORKER_COUNT/" /etc/supervisor/conf.d/supervisord.conf

# Run migrations
php /var/www/html/backend/artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
