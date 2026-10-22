#!/bin/sh
# Build the image webtrees7 from the committed sources, as "composer build" builds a release, entirely in containers.
#   docker/production/build.sh           # image webtrees7:<commit> and webtrees7:latest
#   docker/production/build.sh --save    # also build/production/webtrees7.tar, e.g. for a NAS
#   docker/production/build.sh --zip     # also build/production/webtrees7-<version>.zip + .sha256, a release like Greg's
# The module api4webtrees for webtrees7 (for the apps wtAnd, wtWin7, wtTux7, wtMac7) comes from a release tag of its own
# repository, thobgg/api4webtrees-gedcom7-lab, checked out next to this file as ./api4webtrees7:
#   API4WEBTREES7_DIR=./api4webtrees7 API4WEBTREES7_REF=v2.0.1   # the default; API4WEBTREES7_REF=none leaves it out
# The image says what it contains: label org.opencontainers.image.version, e.g. "2.3.0-webtrees7+71181304c7",
# and label webtrees7.modules, e.g. "api4webtrees 2.0.0 (v2.0.0, e006bb8)".
set -e
cd "$(dirname "$0")/../.."

VERSION="$(git rev-parse --short HEAD)"
RELEASE="$(sed -n "s/.*const string VERSION = '\([^']*\)' . self::STABILITY;.*/\1/p" app/Webtrees.php)$(sed -n "s/.*const string STABILITY = '\([^']*\)';.*/\1/p" app/Webtrees.php)+$VERSION"
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

# The module api4webtrees.  The apps call the route "_api4webtrees_", so the folder must be "api4webtrees".
API4WEBTREES7_DIR="${API4WEBTREES7_DIR:-./api4webtrees7}"
API4WEBTREES7_REF="${API4WEBTREES7_REF:-v2.0.1}"
MODULE=""

if [ "$API4WEBTREES7_REF" != "none" ]; then
    git -C "$API4WEBTREES7_DIR" archive --prefix=modules_v4/api4webtrees/ --format=tar "$API4WEBTREES7_REF" | tar -x -C "$OUT/webtrees"
    MODULE_VERSION="$(git -C "$API4WEBTREES7_DIR" show "$API4WEBTREES7_REF:Api4WebtreesModule.php" | grep -A2 'function customModuleVersion' | sed -n "s/.*return '\\(.*\\)';/\\1/p")"
    MODULE_COMMIT="$(git -C "$API4WEBTREES7_DIR" rev-parse --short "$API4WEBTREES7_REF^{commit}")"
    MODULE_TAG="$(git -C "$API4WEBTREES7_DIR" describe --tags --exact-match "$API4WEBTREES7_REF^{commit}" 2>/dev/null || echo untagged)"
    MODULE="api4webtrees $MODULE_VERSION ($MODULE_TAG, $MODULE_COMMIT)"

    # A release is a tag, and the tag is the version of the module
    if [ "$MODULE_TAG" != "v$MODULE_VERSION" ]; then
        echo "Note: api4webtrees $API4WEBTREES7_REF is not the release v$MODULE_VERSION" >&2
    fi
fi

cp docker/production/entrypoint.sh "$OUT/"
docker build -q -t "webtrees7:$VERSION" -t webtrees7:latest \
    --label "org.opencontainers.image.version=$RELEASE" \
    --label "org.opencontainers.image.revision=$(git rev-parse HEAD)" \
    --label "webtrees7.modules=$MODULE" \
    -f docker/production/Dockerfile "$OUT" >/dev/null
echo "webtrees7:$VERSION = $RELEASE${MODULE:+, $MODULE}"

# A release, as Greg builds one: the folder "webtrees", without the files for development
if [ "$1" = "--zip" ]; then
    ZIP="webtrees7-${RELEASE%%+*}.zip"
    rm -rf "$OUT/zip" && mkdir -p "$OUT/zip" && cp -r "$OUT/webtrees" "$OUT/zip/webtrees"
    (cd "$OUT/zip/webtrees" && rm -rf .dockerignore compose.yaml docker docs phpcs.xml.dist webpack.config.js)
    docker run --rm --user "$(id -u):$(id -g)" -v "$PWD/$OUT":/out -w /out/zip --entrypoint php webtrees7-dev -r '
        $zip = new ZipArchive();
        $zip->open("/out/" . $argv[1], ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("webtrees", FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $zip->addFile($file->getPathname(), $file->getPathname());
        }
        $zip->close();' "$ZIP"
    rm -rf "$OUT/zip"
    (cd "$OUT" && sha256sum "$ZIP" > "$ZIP.sha256" && cat "$ZIP.sha256")
fi

if [ "$1" = "--save" ]; then
    docker save -o "$OUT/webtrees7.tar" "webtrees7:$VERSION" webtrees7:latest
    ls -l "$OUT/webtrees7.tar"
fi
