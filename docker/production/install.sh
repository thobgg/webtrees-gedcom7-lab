#!/bin/sh
# Set up webtrees7 once, in the folder of this script:  sudo sh install.sh
# Needs webtrees7.tar (from build.sh --save) and compose.yaml in the same folder.
# Optional: import/*.ged are imported as trees (only for signed-in users), import/media/* are media files.
set -e
cd "$(dirname "$0")"

# e.g. Synology: sudo does not search /usr/local/bin and /usr/syno/bin
PATH="$PATH:/usr/local/bin:/usr/syno/bin"

if [ -f data/config.ini.php ]; then
    echo "webtrees7 is already set up.  Start it with: docker compose up -d"
    exit 1
fi

if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
else
    COMPOSE="docker-compose"
fi

PORT="${WEBTREES7_PORT:-8097}"
BASE_URL="${BASE_URL:-http://$(hostname -I 2>/dev/null | cut -d' ' -f1):${PORT##*:}}"

# The database password is random; only webtrees and MariaDB need it.
(umask 077 && [ -s db_password ] || head -c 32 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 32 > db_password)
# e.g. Synology: an inherited ACL would let everyone read it
command -v synoacltool >/dev/null && synoacltool -del db_password >/dev/null 2>&1 || true
chmod 600 db_password

if [ -z "$ADMIN_USER" ]; then
    printf 'Username of the webtrees administrator [admin]: '
    read -r ADMIN_USER
    ADMIN_USER="${ADMIN_USER:-admin}"
fi
if [ -z "$ADMIN_EMAIL" ]; then
    printf 'Email of the administrator: '
    read -r ADMIN_EMAIL
fi
printf 'Password of the administrator (at least 8 characters): '
stty -echo 2>/dev/null || true
read -r ADMIN_PASSWORD
stty echo 2>/dev/null || true
echo
[ ${#ADMIN_PASSWORD} -ge 8 ] || { echo "The password is too short."; exit 1; }

[ ! -f webtrees7.tar ] || docker load -i webtrees7.tar

# e.g. Synology: docker does not create missing folders for volumes, and an inherited ACL would
# not let the containers write in them
mkdir -p data database
for DIR in data database; do
    command -v synoacltool >/dev/null && synoacltool -del "$DIR" >/dev/null 2>&1 || true
    chmod 755 "$DIR"
done
$COMPOSE up -d

wt() {
    docker exec -u www-data -w /var/www/html webtrees7 "$@"
}

wt php index.php config-ini --dbtype=mysql --dbhost=db --dbuser=webtrees --dbpass="$(cat db_password)" --dbname=webtrees --tblpfx=wt_ --base-url="$BASE_URL" >/dev/null
wt php docker/seed-database.php
wt php index.php user "$ADMIN_USER" --create --real-name=Administrator --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --quiet
wt php index.php user-setting "$ADMIN_USER" canadmin 1 --quiet
wt php index.php site-setting USE_REGISTRATION_MODULE 0 --quiet
unset ADMIN_PASSWORD

for FILE in import/*.ged; do
    [ -f "$FILE" ] || continue
    TREE="$(basename "$FILE" .ged)"
    echo "Importing $TREE ..."
    docker cp "$FILE" webtrees7:/tmp/import.ged
    wt php index.php tree "$TREE" --create --title="$TREE" --quiet
    wt php index.php tree-import "$TREE" /tmp/import.ged
    docker exec webtrees7-db sh -c 'mariadb -uwebtrees -p"$(cat /run/secrets/db_password)" webtrees -e "UPDATE wt_gedcom SET private = 1 WHERE gedcom_name = '"'$TREE'"'"'
done

if [ -d import/media ]; then
    docker cp import/media/. webtrees7:/var/www/html/data/media/
    docker exec webtrees7 chown -R www-data:www-data /var/www/html/data/media
fi

echo "webtrees7: $BASE_URL"
