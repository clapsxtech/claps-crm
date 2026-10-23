#!/bin/sh
set -eu

cd /var/www/html

if [ -n "${GOOGLE_CREDENTIALS_BASE64:-}" ]; then
    printf '%s' "$GOOGLE_CREDENTIALS_BASE64" | base64 -d > storage/credentials.json
    chmod 600 storage/credentials.json
    chown www-data:www-data storage/credentials.json
fi

if [ -n "${MYSQL_SSL_CA_BASE64:-}" ]; then
    printf '%s' "$MYSQL_SSL_CA_BASE64" | base64 -d > /tmp/mysql-ca.pem
    chmod 600 /tmp/mysql-ca.pem
    chown www-data:www-data /tmp/mysql-ca.pem
    export MYSQL_ATTR_SSL_CA=/tmp/mysql-ca.pem
fi

mkdir -p public/uploads storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ -n "${R2_ACCOUNT_ID:-}" ] && [ -n "${R2_BUCKET_NAME:-}" ]; then
    /usr/local/bin/tigrisfs \
        --endpoint "https://${R2_ACCOUNT_ID}.r2.cloudflarestorage.com" \
        --region auto \
        -o allow_other \
        --uid 33 \
        --gid 33 \
        -f "$R2_BUCKET_NAME" public/uploads &
    fuse_pid=$!
    attempt=0
    until mountpoint -q public/uploads; do
        if ! kill -0 "$fuse_pid" 2>/dev/null; then
            echo "R2 uploads mount exited before becoming ready" >&2
            exit 1
        fi
        attempt=$((attempt + 1))
        if [ "$attempt" -ge 30 ]; then
            echo "Timed out mounting the R2 uploads bucket" >&2
            exit 1
        fi
        sleep 1
    done
fi

mkdir -p public/uploads/profile_photo
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan view:cache

exec apache2-foreground
