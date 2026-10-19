#!/bin/bash
set -e

cd /var/www/html

# Not ready until this boot has migrated (the queue and scheduler wait for the marker).
rm -f /ready/migrated

# Docker's restart manager ignores depends_on after a host reboot, so wait for the db here.
echo "[deploy] waiting for db..."
until mysqladmin ping -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" --silent; do
    sleep 2
done

app-bootstrap --boot

touch /ready/migrated

echo "[deploy] starting php-fpm"
exec php-fpm
