#!/bin/bash
set -e

echo "Starting OpenGRC container..."

# ---------------------------------------------------------------------------
# Application key.
#
# APP_KEY is required at boot. A container without a .env file has no key, so
# generate one and persist it inside storage/ (which is a volume in the
# documented setup). Passing APP_KEY explicitly still wins.
# ---------------------------------------------------------------------------
KEY_FILE="/var/www/html/storage/app/.app-key"

if [ -z "${APP_KEY:-}" ]; then
    if [ -f "$KEY_FILE" ]; then
        export APP_KEY="$(cat "$KEY_FILE")"
        echo "Using the application key persisted in storage/app/.app-key."
    else
        echo "Generating application key..."
        mkdir -p "$(dirname "$KEY_FILE")"
        php artisan key:generate --show > "$KEY_FILE"
        export APP_KEY="$(cat "$KEY_FILE")"
    fi
fi

chown www-data:www-data "$KEY_FILE" 2>/dev/null || true
chmod 600 "$KEY_FILE" 2>/dev/null || true

# ---------------------------------------------------------------------------
# SQLite needs an existing file before the first migration.
# ---------------------------------------------------------------------------
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    DB_FILE="${DB_DATABASE:-/var/www/html/database/opengrc.sqlite}"

    case "$DB_FILE" in
        :memory:* | "") ;;
        *)
            mkdir -p "$(dirname "$DB_FILE")"
            [ -f "$DB_FILE" ] || touch "$DB_FILE"
            chown www-data:www-data "$DB_FILE" 2>/dev/null || true
            ;;
    esac
fi

# ---------------------------------------------------------------------------
# Wait for the database by attempting migrations.
#
# This is deliberately database-agnostic: connecting and migrating is the only
# check that proves the credentials, the driver, and the schema all work. The
# previous implementation hardcoded a MySQL DSN, which broke every SQLite and
# PostgreSQL deployment.
# ---------------------------------------------------------------------------
DB_READY=0

if [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for database at ${DB_HOST}:${DB_PORT:-default}..."

    for attempt in $(seq 1 30); do
        if php artisan migrate --force --no-interaction >/dev/null 2>&1; then
            echo "Database connected and migrated."
            DB_READY=1
            break
        fi

        echo "Waiting for database... (attempt ${attempt}/30)"
        sleep 2
    done

    if [ "$DB_READY" = "0" ]; then
        echo "Warning: could not reach the database after 30 attempts. Continuing so the logs show the real error."
    fi
fi

if [ "$DB_READY" = "0" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# ---------------------------------------------------------------------------
# OAuth keys. Without them the API and MCP guards throw "Invalid key supplied"
# at request time, which looks like an application bug.
# ---------------------------------------------------------------------------
if [ ! -f storage/oauth-private.key ] && [ -z "${PASSPORT_PRIVATE_KEY:-}" ]; then
    echo "Generating Passport keys..."
    php artisan passport:keys --force
fi

# ---------------------------------------------------------------------------
# First run: seed defaults and create the admin account.
# ---------------------------------------------------------------------------
USER_COUNT=$(php -r '
require "/var/www/html/vendor/autoload.php";
$app = require "/var/www/html/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try { echo (int) Illuminate\Support\Facades\DB::table("users")->count(); } catch (Throwable $e) { echo 0; }
' 2>/dev/null)
USER_COUNT=${USER_COUNT:-0}

if [ "$USER_COUNT" = "0" ]; then
    echo "First run detected - seeding database and creating admin user..."

    php artisan db:seed --class=SettingsSeeder --force
    php artisan opengrc:create-user "${ADMIN_EMAIL:-admin@opengrc.local}" "${ADMIN_PASSWORD:-admin123}"
    php artisan db:seed --class=VendorSurveyTemplatesSeeder --force
    php artisan settings:set general.name "${APP_NAME:-OpenGRC}"
    php artisan settings:set general.url "${APP_URL:-http://localhost}"
    php artisan settings:set storage.driver private
    mkdir -p storage/app/public
fi

# ---------------------------------------------------------------------------
# Cache production configuration.
# ---------------------------------------------------------------------------
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
# Permissions, runtime directories, and the public storage link.
#
# storage/app is commonly a volume, so it arrives empty. Recreate the link on
# every start and make sure its target exists.
# ---------------------------------------------------------------------------
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null \
    || echo "Warning: could not adjust storage permissions; continuing."

mkdir -p storage/app/public storage/app/private
chown -R www-data:www-data storage/app

# Runtime directories for the read-only root filesystem. tmpfs mounts start
# empty, so Apache's log directory and the ModSecurity audit log must be created
# here rather than assumed from the image.
mkdir -p /var/log/apache2 /var/run/apache2 /run/lock/apache2 /run/php 2>/dev/null || true
touch /var/log/apache2/modsec_audit.log 2>/dev/null || true
chown -R www-data:www-data /var/log/apache2 /run/php 2>/dev/null || true

# The image ships the symlink; only create it if a bind mount removed it.
if [ ! -e public/storage ] && [ -w public ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
fi

if [ -f storage/oauth-private.key ]; then
    chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
    chmod 600 storage/oauth-private.key
    chmod 644 storage/oauth-public.key
fi

mkdir -p /run/php

# ---------------------------------------------------------------------------
# Optional queue worker.
#
# One container running everything is fine for development and small installs.
# For anything larger, run the worker as its own service and set this to false.
# ---------------------------------------------------------------------------
if [ "${RUN_QUEUE_WORKER:-false}" = "true" ]; then
    echo "Starting queue worker..."
    nohup php artisan queue:work --sleep=3 --tries=3 >> storage/logs/queue-worker.log 2>&1 &
fi

# ---------------------------------------------------------------------------
# Report environment health in the container logs. Non-fatal: a warning should
# not stop the service, but it should be visible.
# ---------------------------------------------------------------------------
echo "Running environment checks..."
php artisan opengrc:doctor || true

# ---------------------------------------------------------------------------
# Run a different role (for example the queue worker) with the same prepared
# filesystem and configuration.
# ---------------------------------------------------------------------------
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

# ---------------------------------------------------------------------------
# Start services.
# ---------------------------------------------------------------------------
echo "Starting cron..."
service cron start || echo "Warning: cron did not start; scheduled tasks will not run."

echo "Starting PHP-FPM..."
service php8.4-fpm start

echo "Starting Apache..."
exec apache2ctl -D FOREGROUND
