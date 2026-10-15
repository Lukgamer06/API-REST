#!/bin/sh
set -eu

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "$(id -u)" -eq 0 ]; then
    chown -R www-data:www-data \
        storage/app/private \
        storage/framework \
        storage/logs \
        bootstrap/cache
    chown www-data:www-data storage/app/public
fi

exec "$@"
