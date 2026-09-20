#!/usr/bin/env sh
# Boot script for the Render web service: listen where Render says, bring the
# schema up to date, warm the caches, then hand over to Apache.
set -e

PORT="${PORT:-10000}"
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s|<VirtualHost \*:80>|<VirtualHost *:${PORT}>|" /etc/apache2/sites-available/000-default.conf

# Render creates the database empty, so every deploy migrates. --force because
# a production environment otherwise asks for interactive confirmation.
echo "==> Running migrations"
php artisan migrate --force

# Opt-in demo data (9 categories, 48 dishes, promo codes, a few orders).
# Seeders are idempotent, but leave this off once real orders exist.
if [ "${SEED_ON_DEPLOY}" = "true" ]; then
    echo "==> Seeding demo data"
    php artisan db:seed --force
fi

# Cached at boot rather than at build time: the config cache bakes in the
# environment variables, and those only exist once Render starts the container.
# (No view:cache — this is a JSON API with no Blade views to compile.)
echo "==> Caching config and routes"
php artisan config:cache
php artisan route:cache

exec apache2-foreground
