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
