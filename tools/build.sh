#!/usr/bin/env bash
# Build a release zip from source. Run from the repo root:  bash tools/build.sh
# Reads the version from the plugin header so the zip can never disagree with it.
set -euo pipefail
cd "$(dirname "$0")/.."
VER=$(grep -oE "define\( 'MA_VERSION', '[0-9.]+' \)" plugin/marwa-apply/marwa-apply.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")
[ -n "$VER" ] || { echo "Could not read MA_VERSION"; exit 1; }
mkdir -p dist
OUT="dist/marwa-apply-$VER.zip"
rm -f "$OUT"
( cd plugin && zip -qr "../$OUT" marwa-apply -x '*.DS_Store' -x '*/.*' )
echo "built $OUT"
