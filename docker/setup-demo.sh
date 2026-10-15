#!/bin/sh
# Set up a fresh webtrees with an administrator and the Falkenrath demo tree.
# Usage (with the container running): docker compose exec webtrees docker/setup-demo.sh
set -e

cd /var/www/html

BASE_URL="${BASE_URL:-http://localhost:8080}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin}"
FALKENRATH_GED="${FALKENRATH_GED:-/falkenrath/versionen/falkenrath-1.7.ged}"

if [ -f data/config.ini.php ]; then
    echo "data/config.ini.php exists already - to start again: docker compose down --volumes"
    exit 1
fi

touch data/webtrees.sqlite
php index.php config-ini --dbtype=sqlite --dbname=webtrees --base-url="$BASE_URL"

php docker/seed-database.php

php index.php user "$ADMIN_USER" --create --real-name=Administrator --email=admin@example.com --password="$ADMIN_PASSWORD"
php index.php user-setting "$ADMIN_USER" canadmin 1

php index.php tree falkenrath --create --title=Falkenrath
php index.php tree-import falkenrath "$FALKENRATH_GED"

mkdir -p data/media
cp /falkenrath/media/* data/media/

echo "Ready: $BASE_URL - user $ADMIN_USER, password $ADMIN_PASSWORD"
