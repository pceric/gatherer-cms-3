#!/bin/sh
set -e

echo "Creating database if required..."
php bin/console doctrine:database:create --no-interaction --if-not-exists

echo "Migrating production database..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Clearing and warming up production cache..."
php bin/console cache:clear --no-interaction --env=prod && php bin/console cache:warmup --no-interaction --env=prod

exec "$@"
