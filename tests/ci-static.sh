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
  scripts/atlas-install-k8s-wizard.sh.sha256 \
  kubernetes/templates/00-namespace.yaml.tpl \
  kubernetes/templates/10-pvc.yaml.tpl \
  kubernetes/templates/20-deployment.yaml.tpl \
  kubernetes/templates/30-service.yaml.tpl \
  kubernetes/templates/40-ingress.yaml.tpl \
  kubernetes/templates/50-maintenance-cronjobs.yaml.tpl \
  kubernetes/templates/kustomization.yaml.tpl \
  kubernetes/manifests/00-namespace.yaml \
  kubernetes/manifests/10-pvc.yaml \
  kubernetes/manifests/20-deployment.yaml \
  kubernetes/manifests/30-service.yaml \
  kubernetes/manifests/40-ingress.yaml \
  kubernetes/manifests/kustomization.yaml; do
  if [[ ! -s "$f" ]]; then
    printf 'Required Kubernetes asset missing/empty: %s\n' "$f" >&2
    fail=1
  fi
done
for token in NAMESPACE APP_NAME IMAGE; do
  if ! grep -Rq "{{${token}}}" kubernetes/templates/*.tpl; then
    printf 'Kubernetes templates missing placeholder: %s\n' "$token" >&2
    fail=1
  fi
done
for token in NODE_SELECTOR_BLOCK; do
  if ! grep -q "{{${token}}}" kubernetes/templates/20-deployment.yaml.tpl; then
    printf 'Deployment template missing optional placeholder: %s\n' "$token" >&2
    fail=1
  fi
done
if ! grep -Fq 'kubectl apply -k "$OUTPUT_DIR"' scripts/atlas-install-k8s-wizard.sh; then
  printf 'Wizard must apply generated resources through Kustomize.\n' >&2
  fail=1
fi
for token in '--self-update' 'ATLAS_WIZARD_AUTO_UPDATE' 'atlas-install-k8s-wizard.sh.sha256' 'Use an optional nodeSelector' 'ATLAS_NODE_SELECTOR' '--db-host' 'ATLAS_DB_HOST' 'Database server/IP (RW, RO and broker)' 'Database endpoint updated without rotating database passwords.'; do
  if ! grep -Fq -- "$token" scripts/atlas-install-k8s-wizard.sh; then
    printf 'Wizard feature missing: %s\n' "$token" >&2
    fail=1
  fi
done
if ! (cd scripts && sha256sum -c atlas-install-k8s-wizard.sh.sha256 >/dev/null 2>&1); then
  printf 'Wizard SHA-256 sidecar does not match the wizard script.\n' >&2
  fail=1
fi
if grep -Rqs 'desalvo/atlas-install-server:latest' kubernetes/manifests kubernetes/templates; then
  printf 'Kubernetes release manifests/templates must not use :latest.\n' >&2
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

# Container must never require a privileged HTTP listener.
grep -Fq "disabled in container: Listen 80" Dockerfile || {
  echo "ERROR: Dockerfile does not disable Rocky Linux default Listen 80" >&2
  exit 1
}
grep -Fq "disable_default_http_listener" container/entrypoint.sh || {
  echo "ERROR: container entrypoint lacks Listen 80 runtime guard" >&2
  exit 1
}
if grep -Eq '^[[:space:]]*Listen[[:space:]]+80([[:space:]]*)$' container/httpd-container.conf.template; then
  echo "ERROR: container Apache template enables privileged port 80" >&2
  exit 1
fi

grep -Fq 'ensure_httpd_container_config' container/entrypoint.sh || {
  echo "ERROR: entrypoint lacks Apache generated-config validation" >&2
  exit 1
}
grep -Fq 'generated Apache config does not contain Listen' container/entrypoint.sh || {
  echo "ERROR: entrypoint lacks HTTPS Listen validation" >&2
  exit 1
}
grep -Fq 'Apache still has no VirtualHost' container/entrypoint.sh || {
  echo "ERROR: entrypoint lacks effective VirtualHost validation" >&2
  exit 1
}

# Apache CRL configuration invariant.
grep -Eq '^[[:space:]]*SSLCARevocationCheck[[:space:]]+' container/httpd-container.conf.template
grep -Eq '^[[:space:]]*SSLCARevocation(Path|File)[[:space:]]+' container/httpd-container.conf.template

# Kubernetes bootstrap Secret must remain authoritative over the persistent env file.
grep -Fq 'sync_bootstrap_config' container/entrypoint.sh || {
  echo "ERROR: entrypoint lacks bootstrap-to-env synchronization" >&2
  exit 1
}
grep -Fq 'bootstrap_sync_loop' container/entrypoint.sh || {
  echo "ERROR: entrypoint lacks runtime bootstrap Secret watcher" >&2
  exit 1
}
grep -Fq 'BOOTSTRAP_SECRET_CHANGED' scripts/atlas-install-k8s-wizard.sh || {
  echo "ERROR: Kubernetes wizard does not track bootstrap Secret changes" >&2
  exit 1
}
grep -Fq 'Bootstrap configuration changed; restarting deployment' scripts/atlas-install-k8s-wizard.sh || {
  echo "ERROR: Kubernetes wizard does not restart after bootstrap Secret changes" >&2
  exit 1
}
