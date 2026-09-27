#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
cd "$ROOT"

fail=0
VERSION=$(cat VERSION)
APP_DIR="var/www/html/atlas_install-${VERSION}"

printf '== Repository/build-context source tree ==\n'
if [[ ! -d "$APP_DIR" ]]; then
  printf 'Required application source directory missing: %s\n' "$APP_DIR" >&2
  fail=1
fi
if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  if git check-ignore -q "$APP_DIR"; then
    printf 'Application source is excluded by .gitignore: %s\n' "$APP_DIR" >&2
    fail=1
  fi
fi
if grep -Eq '^[[:space:]]*/?var/?[[:space:]]*$' .dockerignore; then
  printf '.dockerignore excludes var/, which removes the application source from Docker build context.\n' >&2
  fail=1
fi

printf '== Shell syntax ==\n'
while IFS= read -r -d '' f; do
  printf '  %s\n' "$f"
  bash -n "$f" || fail=1
done < <(find . -type f \( -name '*.sh' -o -path './install-atlas-rhel10.sh' \) -print0)


printf '== Kubernetes wizard/templates ==\n'
for f in \
  scripts/atlas-install-k8s-wizard.sh \
  kubernetes/templates/atlas-install-container.yaml.tpl \
  kubernetes/templates/maintenance-cronjobs.yaml.tpl; do
  if [[ ! -s "$f" ]]; then
    printf 'Required Kubernetes wizard asset missing/empty: %s\n' "$f" >&2
    fail=1
  fi
done
for token in NAMESPACE APP_NAME IMAGE PUBLIC_HOSTNAME INGRESS_CLASS STORAGE_SIZE; do
  if ! grep -q "{{${token}}}" kubernetes/templates/atlas-install-container.yaml.tpl; then
    printf 'Main Kubernetes template missing placeholder: %s\n' "$token" >&2
    fail=1
  fi
done
if grep -Rqs 'desalvo/atlas-install-server:latest' kubernetes/*.yaml; then
  printf 'Kubernetes release manifests must not use :latest.\n' >&2
  fail=1
fi


printf '== IGTF refresh hardening ==\n'
if ! grep -q 'openssl rehash' container/update-igtf.sh; then
  printf 'IGTF updater must rebuild OpenSSL hash links.\n' >&2
  fail=1
fi
if ! grep -q 'ATLAS_IGTF_BUNDLE_MAX_AGE_SECONDS' container/update-igtf.sh; then
  printf 'IGTF updater must support periodic trust-bundle refresh age.\n' >&2
  fail=1
fi
if ! grep -q 'ATLAS_IGTF_REFRESH_SECONDS' container/entrypoint.sh; then
  printf 'Container entrypoint must run periodic IGTF refresh.\n' >&2
  fail=1
fi
if ! grep -Fq 'IGTF trust store contains no OpenSSL hashed CA entries after rehash' container/update-igtf.sh; then
  printf 'IGTF updater must validate hash entries after staging/rehash.\n' >&2
  fail=1
fi

printf '== Wizard idempotency guards ==\n'
for token in \
  'Update existing bootstrap secret?' \
  'Update existing TLS secret?' \
  'Change the stored host certificate/key paths?' \
  'ATLAS_UPDATE_BOOTSTRAP_SECRET' \
  'ATLAS_UPDATE_TLS_SECRET' \
  'TLS secret content already matches'; do
  if ! grep -Fq "$token" scripts/atlas-install-k8s-wizard.sh; then
    printf 'Wizard idempotency behavior missing: %s\n' "$token" >&2
    fail=1
  fi
done

printf '== PHP syntax ==\n'
if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' f; do
    php -l "$f" >/dev/null || fail=1
  done < <(find "$APP_DIR" -type f -name '*.php' -print0)
else
  printf '  php not installed on host; PHP lint will run in the container CI job.\n'
fi

printf '== Secret hygiene ==\n'
if grep -RIlE --exclude-dir=.git --exclude-dir=secrets \
  'BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY' . | grep -q .; then
  grep -RIlE --exclude-dir=.git --exclude-dir=secrets \
    'BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY' . >&2 || true
  printf 'Packaged private key material found.\n' >&2
  fail=1
fi

if find . -type f \( -name '*.env' -o -name '.env' \) ! -name '*.example' ! -path './secrets/*' -print | grep -q .; then
  printf 'Unexpected runtime .env file is tracked in the source tree.\n' >&2
  find . -type f \( -name '*.env' -o -name '.env' \) ! -name '*.example' ! -path './secrets/*' -print >&2
  fail=1
fi

printf '== Legacy unsafe SQL direct input check ==\n'
unsafe=$(grep -RInE --include='*.php' '(SELECT|INSERT|UPDATE|DELETE).*(\$_GET|\$_POST|\$_REQUEST)' "$APP_DIR" \
  | grep -vE 'db_(quote|int|int_list)\(' \
  | grep -vE '^[^:]+:[0-9]+:[[:space:]]*#' || true)
if [[ -n "$unsafe" ]]; then
  printf '%s\n' "$unsafe" >&2
  printf 'Unsanitized direct HTTP input found in SQL-looking line.\n' >&2
  fail=1
fi

exit "$fail"
