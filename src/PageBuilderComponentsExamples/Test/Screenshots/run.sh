#!/bin/sh
# Screenshots of the example components with the module's own stylesheet (css/components.css), no theme: renders
# their real templates with sample content (render.php) and photographs each component in headless Chrome
# (shot.js). Needs PHP with GD, and Docker.
#
#   MAGENTO_ROOT=/path/to/magento sh Test/Screenshots/run.sh [out dir]
#
# MAGENTO_ROOT (for vendor/autoload.php) defaults to five levels above the module
# (app/code/MageOS/PageBuilderComponentsExamples). The screenshots land in the out dir, by default the module's
# docs/screenshots.
#
# The container runs as the current user, so it reads the work dir and writes the out dir with the user's own
# permissions; the image is pinned to a digest.
set -e

PUPPETEER_IMAGE=ghcr.io/puppeteer/puppeteer:24.10.0@sha256:bc20e7cbe2eca9c38e78d2f4a5ee7202aa489c2ebe9f3e7315e8b0322e3bc555

HERE=$(cd "$(dirname "$0")" && pwd)
MODULE=$(cd "$HERE/../.." && pwd)
MAGENTO_ROOT=$(cd "${MAGENTO_ROOT:-$MODULE/../../../..}" && pwd)
OUT=$(mkdir -p "${1:-$MODULE/docs/screenshots}" && cd "${1:-$MODULE/docs/screenshots}" && pwd)
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

php "$HERE/render.php" "$WORK/page" "$MAGENTO_ROOT/vendor/autoload.php" >/dev/null
cp "$MODULE/view/frontend/web/css/components.css" "$WORK/page/components.css"

# HOME for Chrome's profile; the browser stays where the image installed it.
docker run --rm --init --user "$(id -u):$(id -g)" -e HOME=/tmp \
    -e PUPPETEER_CACHE_DIR=/home/pptruser/.cache/puppeteer -e NODE_PATH=/home/pptruser/node_modules \
    -v "$HERE":/h:ro -v "$WORK/page":/page:ro -v "$OUT":/out "$PUPPETEER_IMAGE" timeout 180 node /h/shot.js /page /out
