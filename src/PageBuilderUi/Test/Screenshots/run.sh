#!/bin/sh
# Screenshots of the panel, the tree, the stage and the edit form, without a live admin: compiles the backend
# theme's styles, and core Page Builder's admin LESS with this module's, renders the module's real templates and
# mixins next to a stand-in stage and form, and photographs them in headless Chrome. Needs Docker.
#
#   MAGENTO_ROOT=/path/to/magento sh Test/Screenshots/run.sh [out dir] [scenario...]
#
# MAGENTO_ROOT defaults to four levels above the module (app/code/MageOS/PageBuilderUi); it needs vendor/ and
# lib/web. The screenshots land in the out dir (default: ./screenshots).
#
# The containers run as the current user, so they write the work and out dirs with the user's own permissions;
# the images are pinned to digests, and the LESS compiler to an exact version, installed without its scripts.
set -e

NODE_IMAGE=node:22-alpine@sha256:fe4f6d74e20370acb04ad889077d14d0f64a9c8ea6a42bc60cddf1e844699914
PUPPETEER_IMAGE=ghcr.io/puppeteer/puppeteer:24.10.0@sha256:bc20e7cbe2eca9c38e78d2f4a5ee7202aa489c2ebe9f3e7315e8b0322e3bc555

HERE=$(cd "$(dirname "$0")" && pwd)
MODULE=$(cd "$HERE/../.." && pwd)
MAGENTO_ROOT=$(cd "${MAGENTO_ROOT:-$MODULE/../../../..}" && pwd)
OUT=$(mkdir -p "${1:-screenshots}" && cd "${1:-screenshots}" && pwd)
[ $# -gt 0 ] && shift
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

cp "$HERE/index.html" "$HERE/harness.js" "$HERE/shot.js" "$HERE/styles.less" "$WORK/"
mkdir -p "$WORK/static/fonts"
# Container paths: Magento at /m, the module at /ui.
ln -s /m/vendor/magento/module-page-builder/view/adminhtml/web "$WORK/static/Magento_PageBuilder"
ln -s /m/vendor/magento/theme-adminhtml-backend/web/fonts/admin-icons "$WORK/static/fonts/admin-icons"
ln -s /m/lib/web/fonts/opensans "$WORK/static/fonts/opensans"
ln -s /m/lib/web/knockoutjs/knockout.js "$WORK/knockout.js"
ln -s /m/lib/web/jquery/jquery.min.js "$WORK/jquery.min.js"
ln -s /ui/view/adminhtml/web "$WORK/ui"
# backend.css asks for these next to it.
ln -s static/fonts "$WORK/fonts"
ln -s /m/vendor/magento/theme-adminhtml-backend/web/images "$WORK/images"

# The backend theme's styles.less, over lib/web/css as Magento's fallback merges them (without the modules'
# @magento_import sources); then core Page Builder's and the module's (styles.less).
docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -v "$MAGENTO_ROOT":/m:ro -v "$MODULE":/ui:ro \
    -v "$WORK":/h "$NODE_IMAGE" sh -c '
    cd /tmp && npm install --silent --no-save --no-package-lock --ignore-scripts less@4.2.0 >/dev/null &&
    /tmp/node_modules/.bin/lessc --math=always /h/styles.less /h/styles.css &&
    mkdir -p /tmp/backend && cp -r /m/lib/web/css /tmp/backend/css &&
    cp -r /m/vendor/magento/theme-adminhtml-backend/web/css/. /tmp/backend/css/ &&
    cd /tmp/backend/css &&
    /tmp/node_modules/.bin/lessc --math=always --include-path=/m/lib/web styles.less /h/backend.css
' 2>&1 | grep -v 'math=always is deprecated' || true
[ -f "$WORK/styles.css" ] && [ -f "$WORK/backend.css" ] || { echo 'LESS did not compile' >&2; exit 1; }

# HOME for Chrome's profile; the browser stays where the image installed it.
docker run --rm --init --user "$(id -u):$(id -g)" -e HOME=/tmp \
    -e PUPPETEER_CACHE_DIR=/home/pptruser/.cache/puppeteer -e NODE_PATH=/home/pptruser/node_modules \
    -v "$MAGENTO_ROOT":/m:ro -v "$MODULE":/ui:ro -v "$WORK":/h -v "$OUT":/out \
    "$PUPPETEER_IMAGE" timeout 180 node /h/shot.js /out "$@"
