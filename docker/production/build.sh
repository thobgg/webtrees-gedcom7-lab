#!/bin/sh
# Build the image webtrees7 from the committed sources, as "composer build" builds a release, entirely in containers.
#   docker/production/build.sh           # image webtrees7:<commit> and webtrees7:latest
#   docker/production/build.sh --save    # also build/production/webtrees7.tar, e.g. for a NAS
set -e
cd "$(dirname "$0")/../.."

VERSION="$(git rev-parse --short HEAD)"
OUT=build/production

git diff --quiet HEAD || echo "Note: uncommitted changes are not included" >&2
rm -rf "$OUT" && mkdir -p "$OUT"

# The committed sources, without the files that a release leaves out (see .gitattributes).
WORKTREE="$(mktemp -d)"
git worktree add --detach "$WORKTREE/src" HEAD >/dev/null 2>&1
git -C "$WORKTREE/src" archive --prefix=webtrees/ --format=tar HEAD > "$OUT/webtrees.tar"

# The dependencies without the development tools, and the translations.
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer \
    -v "$WORKTREE/src":/src -v "$PWD/$OUT":/out -w /src --entrypoint sh webtrees7-dev -c '
    set -e
    composer install --no-interaction --no-progress --quiet
    php index.php dev:compile-po-files >/dev/null
    composer install --no-dev --no-interaction --no-progress --quiet
    composer dump-autoload --classmap-authoritative --no-interaction --quiet
    cd /out && tar -xf webtrees.tar && rm webtrees.tar
    cp -r /src/vendor webtrees/vendor
    for FILE in /src/resources/lang/*/messages.php; do cp "$FILE" "webtrees/${FILE#/src/}"; done'

git worktree remove --force "$WORKTREE/src"
rmdir "$WORKTREE"

cp docker/production/entrypoint.sh "$OUT/"
docker build -q -t "webtrees7:$VERSION" -t webtrees7:latest -f docker/production/Dockerfile "$OUT" >/dev/null
echo "webtrees7:$VERSION"

if [ "$1" = "--save" ]; then
    docker save -o "$OUT/webtrees7.tar" "webtrees7:$VERSION" webtrees7:latest
    ls -l "$OUT/webtrees7.tar"
fi
