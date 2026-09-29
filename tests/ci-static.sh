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

if ! grep -q '{{INGRESS_CLASS_ANNOTATION}}' kubernetes/templates/40-ingress.yaml.tpl; then
  printf 'Ingress template must expose the legacy ingress-class annotation placeholder.\n' >&2
  fail=1
fi
if ! grep -Fq 'kubernetes.io/ingress.class: "haproxy"' kubernetes/manifests/40-ingress.yaml; then
  printf 'Rendered HAProxy Ingress must include kubernetes.io/ingress.class: haproxy.\n' >&2
  fail=1
fi
if ! grep -Fq 'kubernetes.io/ingress.class: "haproxy"' kubernetes/haproxy-ingress-tls-passthrough.yaml; then
  printf 'HAProxy passthrough example must include kubernetes.io/ingress.class: haproxy.\n' >&2
  fail=1
fi
if ! grep -Fq 'kubectl apply -k "$OUTPUT_DIR"' scripts/atlas-install-k8s-wizard.sh; then
  printf 'Wizard must apply generated resources through Kustomize.\n' >&2
  fail=1
fi
for token in '--self-update' 'ATLAS_WIZARD_AUTO_UPDATE' 'atlas-install-k8s-wizard.sh.sha256' 'Use an optional nodeSelector' 'ATLAS_NODE_SELECTOR' '--db-host' 'ATLAS_DB_HOST' '--db-ssl' 'ATLAS_DB_SSL' 'Restarting automatically with the updated wizard.' 'Database server/IP (RW, RO and broker)' 'Database connection settings updated without rotating database passwords.'; do
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


printf '== r20 branding/i18n/auth/charts/mobile/documentation regressions ==\n'
for token in 'atlas-mobile-menu-toggle' 'atlas-mobile-menu-backdrop' 'atlas-menu-brand'; do
  grep -q "$token" "$APP_DIR/css/menubar.php" || { printf 'Mobile menu token missing: %s\n' "$token" >&2; fail=1; }
done
grep -q "params.get('per_page')" "$APP_DIR/css/page_header.php" || { printf 'Browser pagination missing.\n' >&2; fail=1; }
grep -q 'href="documentation.php"' "$APP_DIR/index.php" || { printf 'Home documentation link is not local.\n' >&2; fail=1; }
[[ -s "$APP_DIR/documentation.php" ]] || { printf 'In-app documentation page missing.\n' >&2; fail=1; }
[[ -s "$APP_DIR/i18n.php" ]] || { printf 'i18n helper missing.\n' >&2; fail=1; }
[[ -s "$APP_DIR/img/ljsf3-logo.png" && -s "$APP_DIR/img/ljsf3-icon.png" && -s "$APP_DIR/img/favicon.ico" ]] || { printf 'LJSF 3 logo/favicon assets missing.\n' >&2; fail=1; }
grep -q 'atlas_language_selector_html' "$APP_DIR/css/main_header.php" || { printf 'Language selector missing from header.\n' >&2; fail=1; }
grep -q 'atlas_identity_details_html' "$APP_DIR/security.php" || { printf 'Collapsible identity details missing.\n' >&2; fail=1; }
grep -q 'go_login' "$APP_DIR/security.php" || { printf 'Access-denied login guidance missing.\n' >&2; fail=1; }
[[ -s sql/local-auth-schema.sql ]] || { printf 'Local auth schema SQL missing.\n' >&2; fail=1; }
grep -q 'bootstrap-db.php' container/entrypoint.sh || { printf 'Application DB startup bootstrap missing.\n' >&2; fail=1; }
grep -q 'ATLAS_DB_BOOTSTRAP_USER' scripts/atlas-install-k8s-wizard.sh || { printf 'Temporary DB bootstrap user support missing.\n' >&2; fail=1; }
grep -q 'clear_bootstrap_admin_password' scripts/atlas-install-k8s-wizard.sh || { printf 'Temporary DB bootstrap password cleanup missing.\n' >&2; fail=1; }
grep -q 'initial trust-anchor/fetch-crl' container/entrypoint.sh || { printf 'Initial fetch-crl startup logging missing.\n' >&2; fail=1; }
if grep -RInE --include='*.php' --include='*.sh' 'chart\.apis\.google|google\.com/jsapi|google\.visualization|google\.load' "$APP_DIR" | grep -v '/emap.php:' | grep -q .; then
  printf 'External Google chart rendering reference remains.\n' >&2; fail=1
fi
grep -q 'atlas_chart_line' "$APP_DIR/chart_local.php" || { printf 'Local chart renderer missing.\n' >&2; fail=1; }
grep -q 'plot summaries refreshed; no external chart service used' "$APP_DIR/create_ljsfi_plots.php" || { printf 'Summary-only maintenance marker missing.\n' >&2; fail=1; }
grep -q 'kubernetes.io/ingress.class' kubernetes/haproxy-ingress-tls-passthrough.yaml || { printf 'HAProxy legacy ingress class annotation missing.\n' >&2; fail=1; }

printf '== r22 REST API and compact mobile tables ==\n'
[[ -s "$APP_DIR/api/v1/index.php" ]] || { printf 'REST API router missing.\n' >&2; fail=1; }
grep -q "'releases'=>" "$APP_DIR/api/v1/index.php" || { printf 'REST releases resource missing.\n' >&2; fail=1; }
grep -q "'local-users'=>" "$APP_DIR/api/v1/index.php" || { printf 'REST local-users resource missing.\n' >&2; fail=1; }
grep -q "X-ATLAS-TOTP" "$APP_DIR/api/v1/index.php" || { printf 'REST TOTP support missing.\n' >&2; fail=1; }
grep -q "FallbackResource /atlas_install/api/v1/index.php" container/httpd-container.conf.template || { printf 'REST pretty-path routing missing.\n' >&2; fail=1; }
[[ -s docs/REST-API.it.md && -s docs/REST-API.en.md ]] || { printf 'REST API documentation missing.\n' >&2; fail=1; }
grep -q 'atlas-mobile-collapsible-table' "$APP_DIR/css/page_header.php" || { printf 'Mobile table adapter missing.\n' >&2; fail=1; }
grep -q 'atlas-mobile-record' "$APP_DIR/css/modern.css" || { printf 'Mobile compact table CSS missing.\n' >&2; fail=1; }

printf '== r24 mobile menu and list.php structural regressions ==\n'
grep -q "function initAll(){initAutocompleteFallback();}" "$APP_DIR/js/atlas-ui.js" || { printf 'atlas-ui.js still owns duplicate menu handlers.\n' >&2; fail=1; }
grep -q "bar.addEventListener('click'" "$APP_DIR/css/menubar.php" || { printf 'Delegated common mobile submenu handler missing.\n' >&2; fail=1; }
grep -q 'id="atlas-list-filter-form"' "$APP_DIR/list.php" || { printf 'Valid external list filter form missing.\n' >&2; fail=1; }
grep -q '<THEAD><TR class="atlas-list-filter-row">' "$APP_DIR/list.php" || { printf 'Valid list table header row missing.\n' >&2; fail=1; }
if grep -q '</TR><TBODY>' "$APP_DIR/list.php"; then printf 'Repeated invalid TBODY markup remains in list.php.\n' >&2; fail=1; fi
grep -q 'data-label="Num"' "$APP_DIR/list.php" || { printf 'Mobile Num primary field missing.\n' >&2; fail=1; }
grep -q 'data-label="Release number"' "$APP_DIR/list.php" || { printf 'Mobile Release number primary field missing.\n' >&2; fail=1; }
grep -q 'data-label="Site name"' "$APP_DIR/list.php" || { printf 'Mobile Site name primary field missing.\n' >&2; fail=1; }
grep -q 'data-label="Release arch"' "$APP_DIR/list.php" || { printf 'Mobile Release arch primary field missing.\n' >&2; fail=1; }
grep -q 'row.addEventListener' "$APP_DIR/css/page_header.php" || { printf 'Whole-row mobile expansion handler missing.\n' >&2; fail=1; }
grep -q 'max-width:1024px.*pointer:coarse' "$APP_DIR/css/modern.css" || { printf 'Landscape phone list rendering rule missing.\n' >&2; fail=1; }

printf '== r25 auth/logging/layout regressions ==\n'
if ! grep -Fq "'/atlas_install/auth/login.php','/atlas_install/auth/change_password.php'" var/www/html/atlas_install-3.0.0/security.php; then
  printf 'Local auth forms must be exempt from proxy-sensitive same-origin guard and protected by their own CSRF tokens.\n' >&2; fail=1
fi
if ! grep -Fq "event'=>'fatal_shutdown'" var/www/html/atlas_install-3.0.0/security.php || ! grep -Fq "event'=>'uncaught_exception'" var/www/html/atlas_install-3.0.0/security.php; then
  printf 'PHP fatal/exception logging to container stderr is missing.\n' >&2; fail=1
fi
if ! grep -Fq "authentication_required" var/www/html/atlas_install-3.0.0/i18n.php || ! grep -Fq "You must authenticate with an authorized client certificate or a local account." var/www/html/atlas_install-3.0.0/i18n.php; then
  printf 'Localized access-denied authentication guidance is missing.\n' >&2; fail=1
fi
if grep -Fq 'You are logged in as' var/www/html/atlas_install-3.0.0/list.php; then
  printf 'list.php must not emit the old uncollapsed identity line.\n' >&2; fail=1
fi
for token in 'atlas-list-page' 'atlas_identity_details_html()' 'data-mobile-primary="1" data-label="Release number"' 'data-mobile-primary="1" data-label="Site name"' 'data-mobile-primary="1" data-label="Release arch"'; do
  if ! grep -Fq "$token" var/www/html/atlas_install-3.0.0/list.php; then
    printf 'r25 list.php behavior missing token: %s\n' "$token" >&2; fail=1
  fi
done
if ! grep -Fq 'body.atlas-list-page #atlas-list-results tr.atlas-mobile-record' var/www/html/atlas_install-3.0.0/css/modern.css; then
  printf 'Desktop/mobile full-width collapsible list CSS is missing.\n' >&2; fail=1
fi
if grep -Fq '</TD></TR></TABLE>' var/www/html/atlas_install-3.0.0/list.php; then
  printf 'Legacy list.php wrapper close that caused footer/table overlap must be removed.\n' >&2; fail=1
fi
if ! grep -Fq 'db_query_rest' var/www/html/atlas_install-3.0.0/access_log.php; then
  printf 'Access logging must be best-effort/non-fatal.\n' >&2; fail=1
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

echo "Landing search / root redirect checks"
grep -Fq '<form method="get" name="select" action="list.php"' var/www/html/atlas_install/index.php
grep -Fq '<input id="rel" name="rel"' var/www/html/atlas_install/index.php
grep -Fq '<input id="sitename" name="sitename"' var/www/html/atlas_install/index.php
grep -Fq '<input id="resource" name="resource"' var/www/html/atlas_install/index.php
grep -Fq 'RedirectMatch 302 ^/$ /atlas_install/' container/httpd-container.conf.template

# r23 mobile/login regressions
grep -q 'ATLAS_PUBLIC_HOSTNAME' "$APP_ROOT/security.php" || { echo "ERROR: configured public host missing from same-origin guard" >&2; exit 1; }
grep -q 'data-atlas-datalist' "$APP_ROOT/index.php" || { echo "ERROR: home autocomplete fallback missing" >&2; exit 1; }
grep -q 'atlas-autocomplete-popup' "$APP_ROOT/css/modern.css" || { echo "ERROR: mobile autocomplete CSS missing" >&2; exit 1; }
grep -q 'atlasFallbackBound' "$APP_ROOT/css/menubar.php" || { echo "ERROR: common mobile menu fallback missing" >&2; exit 1; }
