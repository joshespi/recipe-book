#!/bin/bash
set -e

cd /var/www/html

# Docker's restart manager ignores depends_on after a host reboot, so wait for the db here.
echo "[deploy] waiting for db..."
until mysqladmin ping -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" --silent; do
    sleep 2
done

# Cleared first so the healthcheck can't see a marker from a previous boot.
rm -f storage/framework/migrated

app-bootstrap --boot

touch storage/framework/migrated

echo "[deploy] starting php-fpm"
exec php-fpm
