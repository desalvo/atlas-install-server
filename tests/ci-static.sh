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
for token in 'atlas-sidebar-toggle' 'atlas-mobile-menu-backdrop' 'atlas-sidebar-home'; do
  grep -q "$token" "$APP_DIR/css/menubar.php" "$APP_DIR/css/main_header.php" || { printf 'Responsive shell token missing: %s\n' "$token" >&2; fail=1; }
done
grep -q "params.get('per_page')" "$APP_DIR/css/page_header.php" || { printf 'Browser pagination missing.\n' >&2; fail=1; }
grep -q 'documentation.php' "$APP_DIR/css/main_header.php" || { printf 'Documentation link is not present in the common header.\n' >&2; fail=1; }
[[ -s "$APP_DIR/documentation.php" ]] || { printf 'In-app documentation page missing.\n' >&2; fail=1; }
[[ -s "$APP_DIR/i18n.php" ]] || { printf 'i18n helper missing.\n' >&2; fail=1; }
[[ -s "$APP_DIR/img/ljsf3-logo.png" && -s "$APP_DIR/img/ljsf3-icon.png" && -s "$APP_DIR/img/favicon.ico" ]] || { printf 'LJSF 3 logo/favicon assets missing.\n' >&2; fail=1; }
grep -q '/atlas_install/lang.php?lang=' "$APP_DIR/css/main_header.php" || { printf 'Language selector missing from header.\n' >&2; fail=1; }
grep -q 'atlas_identity_details_html' "$APP_DIR/security.php" || { printf 'Collapsible identity details missing.\n' >&2; fail=1; }
grep -q 'go_login' "$APP_DIR/security.php" || { printf 'Access-denied login guidance missing.\n' >&2; fail=1; }
[[ -s sql/local-auth-schema.sql ]] || { printf 'Local auth schema SQL missing.\n' >&2; fail=1; }
grep -q 'bootstrap-db.php' container/entrypoint.sh || { printf 'Application DB startup bootstrap missing.\n' >&2; fail=1; }
grep -q 'ATLAS_DB_BOOTSTRAP_USER' scripts/atlas-install-k8s-wizard.sh || { printf 'Temporary DB bootstrap user support missing.\n' >&2; fail=1; }
grep -q 'manage_db_admin_secret' scripts/atlas-install-k8s-wizard.sh || { printf 'Persistent DB schema-migration Secret support missing.\n' >&2; fail=1; }
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
grep -q "function initAll(){initAutocompleteFallback();initLegacyDefinitionSubmit();}" "$APP_DIR/js/atlas-ui.js" || { printf 'atlas-ui.js expected autocomplete/legacy-submit initialization missing.\n' >&2; fail=1; }
grep -q "atlas-sidebar-heading" "$APP_DIR/css/menubar.php" || { printf 'Common responsive sidebar section handler missing.\n' >&2; fail=1; }
grep -q 'id="atlas-list-filter-form"' "$APP_DIR/list.php" || { printf 'Valid external list filter form missing.\n' >&2; fail=1; }
grep -q '<THEAD><TR class="atlas-list-column-row">' "$APP_DIR/list.php" || { printf 'Valid list table header row missing.\n' >&2; fail=1; }
if grep -q '</TR><TBODY>' "$APP_DIR/list.php"; then printf 'Repeated invalid TBODY markup remains in list.php.\n' >&2; fail=1; fi
grep -q 'data-label="Num"' "$APP_DIR/list.php" || { printf 'Mobile Num primary field missing.\n' >&2; fail=1; }
grep -q 'data-label="Release number"' "$APP_DIR/list.php" || { printf 'Mobile Release number primary field missing.\n' >&2; fail=1; }
grep -q 'data-label="Site name"' "$APP_DIR/list.php" || { printf 'Mobile Site name primary field missing.\n' >&2; fail=1; }
grep -q 'data-label="Release arch"' "$APP_DIR/list.php" || { printf 'Mobile Release arch primary field missing.\n' >&2; fail=1; }
grep -q 'row.addEventListener' "$APP_DIR/css/page_header.php" || { printf 'Whole-row mobile expansion handler missing.\n' >&2; fail=1; }
grep -q 'max-width:1024px.*pointer:coarse' "$APP_DIR/css/modern.css" || { printf 'Landscape phone list rendering rule missing.\n' >&2; fail=1; }

printf '== r25 auth/logging/layout regressions ==\n'
if ! grep -Fq "preg_match('~/auth/(?:login|change_password)\\.php/?$~'" var/www/html/atlas_install-3.0.0/security.php; then
  printf 'Local auth forms must be exempt from proxy-sensitive same-origin guard and protected by their own CSRF tokens.\n' >&2; fail=1
fi
if ! grep -Fq "atlas_app_log('fatal_shutdown'" var/www/html/atlas_install-3.0.0/security.php || ! grep -Fq "atlas_app_log('uncaught_exception'" var/www/html/atlas_install-3.0.0/security.php; then
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

printf '== r26 list/search/OTP/logging regressions ==\n'
grep -Fq 'class="atlas-list-search"' "$APP_DIR/list.php" || { printf 'Collapsed list search section missing.\n' >&2; fail=1; }
if grep -Fq 'atlas-list-filter-row' "$APP_DIR/list.php"; then printf 'Per-column filter row must be removed from list.php.\n' >&2; fail=1; fi
grep -Fq 'class="atlas-row-toggle"' "$APP_DIR/list.php" || { printf 'Leading compact list row toggle missing.\n' >&2; fail=1; }
grep -Fq "return r.hasAttribute('data-atlas-list-record')" "$APP_DIR/css/page_header.php" || { printf 'list.php row adapter must ignore toolbar/filter rows.\n' >&2; fail=1; }
grep -Fq 'function atlas_totp_qr_data_uri' "$APP_DIR/local_auth.php" || { printf 'Local TOTP QR rendering missing.\n' >&2; fail=1; }
grep -Fq 'qrencode' Dockerfile || { printf 'Container QR encoder dependency missing.\n' >&2; fail=1; }
grep -Fq "atlas_app_log('http_5xx_completed'" "$APP_DIR/security.php" || { printf 'HTTP 5xx completion logging missing.\n' >&2; fail=1; }
grep -Fq "atlas_app_log('req_data_query'" "$APP_DIR/protected/req.php" || { printf 'req.php diagnostic logging missing.\n' >&2; fail=1; }
grep -Fq 'atlas-shell-footer' "$APP_DIR/security.php" || { printf 'Common application footer shell missing.\n' >&2; fail=1; }

printf '== r27 authenticated compatibility/logging/list regressions ==\n'
for token in 'atlas_sync_local_legacy_user' 'local_legacy_identity_synced' 'local_legacy_identity_sync_failed'; do
  grep -Fq "$token" var/www/html/atlas_install-3.0.0/local_auth.php || { printf 'r27 local legacy identity compatibility missing: %s\n' "$token" >&2; fail=1; }
done
[[ -f var/www/html/atlas_install-3.0.0/lang.php ]] || { printf 'r27 dedicated language endpoint missing\n' >&2; fail=1; }
grep -Fq '/atlas_install/lang.php?lang=' var/www/html/atlas_install-3.0.0/i18n.php || { printf 'r27 language selector does not use dedicated endpoint\n' >&2; fail=1; }
grep -Fq 'Application error log streaming enabled' container/entrypoint.sh || { printf 'r27 application log tail missing\n' >&2; fail=1; }
grep -Fq 'php-application.log' Dockerfile || { printf 'r27 PHP-FPM application log path missing\n' >&2; fail=1; }
if grep -Fq "</TD>\\n');" var/www/html/atlas_install-3.0.0/list.php; then printf 'r27 list.php still emits literal backslash-n text\n' >&2; fail=1; fi
grep -Fq 'preserve legacy installation-state colours' var/www/html/atlas_install-3.0.0/css/modern.css || { printf 'r27 list status colour compatibility missing\n' >&2; fail=1; }
for token in req_bootstrap_enter req_include_dependencies req_dependencies_loaded; do grep -Fq "$token" var/www/html/atlas_install-3.0.0/protected/req.php || { printf 'r27 req diagnostic checkpoint missing: %s\n' "$token" >&2; fail=1; }; done


printf '== r29 schema/query/docs regressions ==\n'
grep -q 'request_status_date_indx' container/bootstrap-db.php || { printf 'r29 request index migration missing.\n' >&2; fail=1; }
grep -q 'request_request_date_indx' container/bootstrap-db.php || { printf 'r29 request-date index migration missing.\n' >&2; fail=1; }
grep -q 'adminuser.name AS admin_name' "$APP_DIR/protected/req.php" || { printf 'req.php N+1 admin lookup optimization missing.\n' >&2; fail=1; }
grep -q 'manage_db_admin_secret' scripts/atlas-install-k8s-wizard.sh || { printf 'Persistent db-admin Secret management missing.\n' >&2; fail=1; }
grep -q '{{APP_NAME}}-db-admin' kubernetes/templates/20-deployment.yaml.tpl || { printf 'Deployment db-admin envFrom missing.\n' >&2; fail=1; }
[[ -s "$APP_DIR/docs/LJSF3-Manual.it.pdf" && -s "$APP_DIR/docs/LJSF3-Manual.en.pdf" ]] || { printf 'PDF manuals missing.\n' >&2; fail=1; }
grep -q 'initLegacyDefinitionSubmit' "$APP_DIR/js/atlas-ui.js" || { printf 'Legacy definition submit fallback missing.\n' >&2; fail=1; }
grep -q 'atlas_translate_legacy_html' "$APP_DIR/i18n.php" || { printf 'Legacy UI translation layer missing.\n' >&2; fail=1; }

echo "== r33 X.509 DN canonicalization regressions =="
php tests/test-dn-canonicalization.php
grep -q "atlas_canonicalize_dn" var/www/html/atlas_install-3.0.0/local_auth.php
grep -q "match_method.*canonical\|matchMethod='canonical'" var/www/html/atlas_install-3.0.0/local_auth.php
grep -q "known_user.*dn\|source==='certificate' && !empty(\$i\['known_user'\])" var/www/html/atlas_install-3.0.0/security.php
grep -q "legacy_unbound" var/www/html/atlas_install-3.0.0/local_auth.php
grep -q "identity_dn_ca_match" var/www/html/atlas_install-3.0.0/i18n.php
grep -q "identity_binding_status" var/www/html/atlas_install-3.0.0/security.php

printf '== r34 documented UI parity regressions ==\n'
[[ -s "$APP_DIR/img/mockup-topbar.png" && -s "$APP_DIR/img/mockup-earth-banner.jpg" ]] || { printf 'Documented UI earth/header assets missing.\n' >&2; fail=1; }
for token in 'atlas-dashboard-hero' 'atlas-kpi-grid' 'atlas-dashboard-row three' 'atlas-resource-panel' 'atlas-quick-grid'; do
  grep -Fq "$token" "$APP_DIR/index.php" || { printf 'Dashboard mock-up structure missing: %s\n' "$token" >&2; fail=1; }
done
grep -Fq -- '--atlas-sidebar-width:248px' "$APP_DIR/css/modern.css" || { printf 'Reference sidebar width is not locked to the documented mock-up.\n' >&2; fail=1; }
grep -Fq 'atlas-shell-footer' "$APP_DIR/css/modern.css" || { printf 'Mock-up footer styling missing.\n' >&2; fail=1; }
grep -Fq 'atlas-topbar-sky' "$APP_DIR/css/main_header.php" || { printf 'Mock-up top-bar structure missing.\n' >&2; fail=1; }
grep -Fq 'atlas-sidebar-section' "$APP_DIR/css/menubar.php" || { printf 'Mock-up sidebar structure missing.\n' >&2; fail=1; }

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

# r30 detailed documentation / REST API reference regressions
echo "== r30 detailed documentation regressions =="
test -s docs/USER-GUIDE.it.md
test -s docs/USER-GUIDE.en.md
test -s docs/REST-API.it.md
test -s docs/REST-API.en.md
test -s docs/LJSF3-Manual.it.pdf
test -s docs/LJSF3-Manual.en.pdf
test -s docs/LJSF3-REST-API.it.pdf
test -s docs/LJSF3-REST-API.en.pdf
grep -q 'X-ATLAS-TOTP' docs/REST-API.it.md
grep -q 'password_change_required' docs/REST-API.en.md
grep -q 'Idempotency-Key' docs/REST-API.it.md
grep -q 'assets/arch.png' docs/USER-GUIDE.it.md
grep -q 'assets/migrate.png' docs/USER-GUIDE.en.md
grep -q 'LJSF3-REST-API' var/www/html/atlas_install-3.0.0/documentation.php


echo "== r32 identity/CA/soft-delete regressions =="
grep -q "user','ca_dn'" container/bootstrap-db.php
grep -q "user','deleted_at'" container/bootstrap-db.php
grep -q "ca_mismatch_explanation" var/www/html/atlas_install-3.0.0/i18n.php
grep -q "known_user" var/www/html/atlas_install-3.0.0/security.php
grep -q "legacy_ref" var/www/html/atlas_install-3.0.0/local_auth.php
grep -q "soft_delete_user" var/www/html/atlas_install-3.0.0/protected/user_info.php
grep -q "delete_user" var/www/html/atlas_install-3.0.0/protected/user.php
! grep -q "You are logged in as" var/www/html/atlas_install-3.0.0/protected/user.php
grep -q "if(\$name==='users')" var/www/html/atlas_install-3.0.0/api/v1/index.php
grep -q "\$actor_is_master = \$actor_enabled && \$actor_role === 'master'" var/www/html/atlas_install-3.0.0/protected/user.php
grep -q "if (\$actor_is_master)" var/www/html/atlas_install-3.0.0/protected/user.php
grep -q "user_ca_dn_indx" var/www/html/atlas_install-3.0.0/conf/sql/create_install_db.sql.template
! sed -n '/CREATE TABLE `jdl`/,/) ENGINE/p' var/www/html/atlas_install-3.0.0/conf/sql/atlas_install_panda.sql | grep -q 'user_ca_dn_indx'
