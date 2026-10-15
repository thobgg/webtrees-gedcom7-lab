#!/bin/sh
# Prepare the volumes, then run the command.
set -e

cd /var/www/html

for file in /opt/webtrees-data-template/.htaccess /opt/webtrees-data-template/index.php; do
    [ -e "data/$(basename "$file")" ] || cp "$file" data/
done

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress
fi

exec "$@"
