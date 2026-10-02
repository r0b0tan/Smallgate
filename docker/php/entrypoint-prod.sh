#!/bin/sh
# Production entrypoint for the app, worker and migrate services.
#
# Configuration comes from the environment (env_file in compose.prod.yaml), so
# it can only be cached once the container runs -- never at build time, which
# would bake secrets into the image.
set -eu

php artisan config:cache --no-ansi -q
php artisan route:cache --no-ansi -q
php artisan view:cache --no-ansi -q

exec "$@"
