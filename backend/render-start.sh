#!/bin/sh
set -eu

export DB_DATABASE="${DB_DATABASE:-/app/database/database.sqlite}"
mkdir -p "$(dirname "$DB_DATABASE")"
touch "$DB_DATABASE"

php artisan migrate --force
php artisan db:seed --class=GameSeeder --force

php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
