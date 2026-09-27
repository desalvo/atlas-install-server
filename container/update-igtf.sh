#!/usr/bin/env bash
set -euo pipefail
CA_DIR=${ATLAS_IGTF_DIR:-/etc/grid-security/certificates}
STAMP="$CA_DIR/.igtf-last-refresh"
FORCE=${ATLAS_IGTF_FORCE:-0}

need_refresh=1
if [[ "$FORCE" != 1 && -f "$STAMP" ]] && find "$STAMP" -mtime -30 -print -quit | grep -q . \
   && find "$CA_DIR" -maxdepth 1 -type f -name '*.0' -print -quit | grep -q .; then
  need_refresh=0
fi

if (( need_refresh )); then
  work=$(mktemp -d)
  trap 'rm -rf "$work"' EXIT
  stage="$work/stage"
  mkdir -p "$stage"
  for profile in classic mics iota; do
    url="https://dist.igtf.net/distribution/current/accredited/igtf-preinstalled-bundle-${profile}.tar.gz"
    curl --fail --silent --show-error --location --proto '=https' --tlsv1.2 "$url" -o "$work/$profile.tar.gz"
    tar -xzf "$work/$profile.tar.gz" -C "$stage"
  done
  root="$stage"
  [[ -d "$stage/certificates" ]] && root="$stage/certificates"
  find "$root" -maxdepth 1 -type f -name '*.0' -print -quit | grep -q . || { echo "IGTF bundle contains no hashed certificates" >&2; exit 1; }
  mkdir -p "$CA_DIR"
  rsync -a --delete --exclude='*.r[0-9]*' "$root/" "$CA_DIR/"
  find "$CA_DIR" -type d -exec chmod 0755 {} +
  find "$CA_DIR" -type f -exec chmod 0644 {} +
  touch "$STAMP"
fi

if command -v fetch-crl >/dev/null 2>&1; then
  fetch-crl || echo "WARNING: fetch-crl returned non-zero; existing CRLs are retained" >&2
fi
