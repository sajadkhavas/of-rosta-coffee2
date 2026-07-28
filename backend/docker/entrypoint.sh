#!/bin/sh
set -eu

cd /var/www/html

# Each runtime container gets a private compiled-view directory. The shared
# storage volume is reserved for durable application state and must not be used
# by api, worker and scheduler processes for concurrent Blade cache writes.
VIEW_COMPILED_PATH="${VIEW_COMPILED_PATH:-/tmp/rosta-compiled-views}"
export VIEW_COMPILED_PATH
mkdir -p "$VIEW_COMPILED_PATH"

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

exec "$@"
