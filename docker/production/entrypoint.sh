#!/bin/sh
# Prepare the data directory, then run the command.
set -e

cd /var/www/html

for file in /opt/webtrees-data-template/.htaccess /opt/webtrees-data-template/index.php; do
    [ -e "data/$(basename "$file")" ] || cp "$file" data/
done

mkdir -p data/media
chown -R www-data:www-data data

exec "$@"
