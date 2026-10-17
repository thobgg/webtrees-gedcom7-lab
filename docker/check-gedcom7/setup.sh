#!/bin/sh
# Set up the throwaway webtrees of docker/check-gedcom7.sh: an administrator and the test trees.
set -e

cd /var/www/html

# The GEDCOM 7.0 test files of FamilySearch, at a fixed commit.  They are downloaded once, into build/.
GEDCOM_IO_COMMIT=a7b136b777add6fe5796bcc49f61dfa1631c0ec2
GEDCOM_IO_FILES="age date escapes extension-record extensions filename-1 lang long-url maximal70 maximal70-lds
maximal70-memories1 maximal70-memories2 maximal70-tree1 maximal70-tree2 minimal70 notes-1 obje-1 remarriage1
remarriage2 same-sex-marriage voidptr xref"
GEDCOM_IO_DIR=build/gedcom7-check/gedcom.io-$GEDCOM_IO_COMMIT

# The GEDCOM 7.0 specification, as data
REGISTRIES_COMMIT=2a387616f63aab91f3ff486051a8f9ed211d7870
REGISTRIES_FILE=build/gedcom7-check/g7validation-$REGISTRIES_COMMIT.json

download() {
    [ -s "$2" ] || php -r 'copy($argv[1], $argv[2]) || exit(1);' "$1" "$2"
}

mkdir -p "$GEDCOM_IO_DIR"

download "https://raw.githubusercontent.com/FamilySearch/GEDCOM-registries/$REGISTRIES_COMMIT/generated_files/g7validation.json" "$REGISTRIES_FILE"

for FILE in $GEDCOM_IO_FILES; do
    download "https://raw.githubusercontent.com/FamilySearch/GEDCOM.io/$GEDCOM_IO_COMMIT/testfiles/gedcom70/$FILE.ged" "$GEDCOM_IO_DIR/$FILE.ged"
done

php index.php config-ini --dbtype=mysql --dbhost=checkdb --dbuser=webtrees --dbpass=webtrees --dbname=webtrees --base-url=http://localhost:8080 >/dev/null
php docker/seed-database.php >/dev/null

php index.php user admin --create --real-name=Administrator --email=admin@example.com --password=admin --quiet
php index.php user-setting admin canadmin 1
php index.php user-setting admin auto_accept 1

import() {
    php index.php tree "$1" --create --title="$1" --quiet
    php index.php tree-import "$1" "$2" >/dev/null
    echo "Imported $2 as $1"
}

import falkenrath /falkenrath/versionen/falkenrath-1.7.ged
import falkenrath7 tests/data/falkenrath-gedcom7.ged
import demo tests/data/demo.ged

for FILE in $GEDCOM_IO_FILES; do
    import "$FILE" "$GEDCOM_IO_DIR/$FILE.ged"
done

mkdir -p data/media
cp /falkenrath/media/* data/media/
