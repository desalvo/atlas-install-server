#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
VERSION=${1:-$(cat "$ROOT/VERSION")}
OUT=${2:-"$ROOT/dist"}
NAME="atlas-install-server-${VERSION}"
mkdir -p "$OUT"
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
mkdir -p "$tmp/$NAME"
rsync -a \
  --exclude '.git' \
  --exclude 'dist' \
  --exclude '.env' \
  --exclude 'secrets/*' \
  "$ROOT/" "$tmp/$NAME/"
tar -C "$tmp" -czf "$OUT/$NAME.tar.gz" "$NAME"
sha256sum "$OUT/$NAME.tar.gz" > "$OUT/$NAME.tar.gz.sha256"
printf 'Created %s\n' "$OUT/$NAME.tar.gz"
