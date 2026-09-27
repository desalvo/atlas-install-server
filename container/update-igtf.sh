#!/usr/bin/env bash
set -euo pipefail

CA_DIR=${ATLAS_IGTF_DIR:-/etc/grid-security/certificates}
STAMP="$CA_DIR/.igtf-last-refresh"
FORCE=${ATLAS_IGTF_FORCE:-0}
MAX_AGE_SECONDS=${ATLAS_IGTF_BUNDLE_MAX_AGE_SECONDS:-86400}

log() { printf 'IGTF: %s\n' "$*"; }

is_nonnegative_integer() { [[ "$1" =~ ^[0-9]+$ ]]; }
is_nonnegative_integer "$MAX_AGE_SECONDS" || {
  echo "ATLAS_IGTF_BUNDLE_MAX_AGE_SECONDS must be a non-negative integer" >&2
  exit 2
}

has_hashed_certs() {
  [[ -d "$CA_DIR" ]] && find "$CA_DIR" -maxdepth 1 -name '*.0' -print -quit | grep -q .
}

stamp_is_fresh() {
  [[ -f "$STAMP" ]] || return 1
  local now mtime age
  now=$(date +%s)
  mtime=$(stat -c %Y "$STAMP" 2>/dev/null || echo 0)
  age=$(( now - mtime ))
  (( age >= 0 && age < MAX_AGE_SECONDS ))
}

need_refresh=1
if [[ "$FORCE" != 1 ]] && stamp_is_fresh && has_hashed_certs; then
  need_refresh=0
fi

if (( need_refresh )); then
  work=$(mktemp -d)
  trap 'rm -rf "$work"' EXIT
  extract="$work/extract"
  trust="$work/trust"
  mkdir -p "$extract" "$trust"

  for profile in classic mics iota; do
    url="https://dist.igtf.net/distribution/current/accredited/igtf-preinstalled-bundle-${profile}.tar.gz"
    archive="$work/${profile}.tar.gz"
    log "downloading ${profile} trust anchors"
    curl --fail --silent --show-error --location --proto '=https' --tlsv1.2 "$url" -o "$archive"
    tar -xzf "$archive" -C "$extract"
  done

  # The preinstalled bundles may contain the trust directory below a package
  # prefix (for example .../etc/grid-security/certificates). Merge every
  # certificates directory found, rather than assuming a fixed tar layout.
  found_dir=0
  while IFS= read -r -d '' certdir; do
    found_dir=1
    rsync -a --exclude='*.r[0-9]*' "$certdir/" "$trust/"
  done < <(find "$extract" -type d -name certificates -print0)

  # Compatibility fallback for bundles that place the trust-anchor files at
  # another level. Preserve their basenames in the flat OpenSSL CA directory.
  if (( ! found_dir )); then
    while IFS= read -r -d '' certfile; do
      cp -a "$certfile" "$trust/$(basename "$certfile")"
    done < <(find "$extract" -type f \( -name '*.0' -o -name '*.pem' -o -name '*.crt' -o -name '*.cer' -o -name '*.signing_policy' -o -name '*.namespaces' -o -name '*.info' \) -print0)
  fi

  # Ensure at least one parseable CA certificate was staged before touching
  # the active trust directory. Existing hash-named certificates are valid
  # inputs too; openssl rehash will add/update symlinks where needed.
  ca_count=0
  while IFS= read -r -d '' candidate; do
    if openssl x509 -in "$candidate" -noout >/dev/null 2>&1; then
      ca_count=$((ca_count + 1))
    fi
  done < <(find "$trust" -maxdepth 1 -type f \( -name '*.0' -o -name '*.pem' -o -name '*.crt' -o -name '*.cer' \) -print0)
  (( ca_count > 0 )) || {
    echo "IGTF bundle contains no parseable CA certificates" >&2
    exit 1
  }

  # Rebuild OpenSSL subject-hash links in staging. Do not validate hash links
  # before this step: those links are a local trust-store representation.
  openssl rehash "$trust"
  find "$trust" -maxdepth 1 -name '*.0' -print -quit | grep -q . || {
    echo "IGTF trust store contains no OpenSSL hashed CA entries after rehash" >&2
    exit 1
  }

  mkdir -p "$CA_DIR"
  # Only now replace the active store. A failed download/extract/rehash leaves
  # the previously working store untouched.
  rsync -a --delete --exclude='*.r[0-9]*' "$trust/" "$CA_DIR/"
  find "$CA_DIR" -type d -exec chmod 0755 {} +
  find "$CA_DIR" -type f -exec chmod 0644 {} +
  touch "$STAMP"
  log "trust anchors refreshed (${ca_count} parseable CA certificates)"
else
  log "trust anchors are current; bundle refresh not required"
fi

# CRLs are refreshed on every invocation. fetch-crl manages the CRL files in
# the same hashed trust directory and retains the previous usable set if a
# remote endpoint is temporarily unavailable.
if command -v fetch-crl >/dev/null 2>&1; then
  if fetch-crl; then
    log "CRL refresh completed"
  else
    echo "WARNING: fetch-crl returned non-zero; existing CRLs are retained" >&2
  fi
fi

# Final invariant required by Apache SSLCACertificatePath.
find "$CA_DIR" -maxdepth 1 -name '*.0' -print -quit | grep -q . || {
  echo "IGTF trust store has no hashed CA entries" >&2
  exit 1
}
