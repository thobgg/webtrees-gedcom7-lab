#!/bin/sh
# Check GEDCOM 7.0 in a throwaway webtrees.  "docker/check-gedcom7.sh editor" checks only the trees and the editor.
set -e

cd "$(dirname "$0")/.."

docker compose --profile check run --rm check sh -c "docker/check-gedcom7/setup.sh >/dev/null && php docker/check-gedcom7/check.php $1"
docker compose --profile check down
