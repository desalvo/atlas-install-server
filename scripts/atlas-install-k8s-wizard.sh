#!/usr/bin/env bash
set -euo pipefail

WIZARD_VERSION="3.0.0-r35"
DEFAULT_REPO="desalvo/atlas-install-server"
DEFAULT_REF="main"
DEFAULT_NAMESPACE="atlas-install"
DEFAULT_APP_NAME="atlas-install"
DEFAULT_IMAGE="desalvo/atlas-install-server:3.0.0"
DEFAULT_HOSTNAME="atlas-install-el10.apps.desalvo.eu"
DEFAULT_INGRESS_CLASS="haproxy"
DEFAULT_STORAGE_SIZE="5Gi"
DEFAULT_PVC_ACCESS_MODE="ReadWriteOnce"
DEFAULT_IMAGE_PULL_POLICY="IfNotPresent"
DEFAULT_CPU_REQUEST="100m"
DEFAULT_MEMORY_REQUEST="256Mi"
DEFAULT_CPU_LIMIT="2"
DEFAULT_MEMORY_LIMIT="1Gi"
DEFAULT_REPLICAS="1"
DEFAULT_PLOTS_SCHEDULE="0 * * * *"
DEFAULT_CLEANUP_SCHEDULE="15 0 * * *"
DEFAULT_DB_NAME="atlas_install_panda"
DEFAULT_DB_HOST="192.168.1.145"
DEFAULT_DB_RW_USER="atlas_rw"
DEFAULT_DB_RO_USER="atlas_ro"
DEFAULT_DB_BROKER_USER="atlas_rw"
DEFAULT_DB_BOOTSTRAP_USER="root"
DEFAULT_DB_SSL="no"
DEFAULT_DB_SSL_VERIFY="no"
DEFAULT_DB_SSL_CA=""
DEFAULT_VO="ATLAS"
DEFAULT_EMAIL="no-reply@localhost"
DEFAULT_INFOSYS="lcg-bdii.cern.ch"
DEFAULT_ACTIVITY_PERIOD="3 DAY"
DEFAULT_DEBUG="0"
DEFAULT_LOCAL_ADMIN_PASSWORD="password"
DEFAULT_AUTO_UPDATE_WIZARD="yes"
DEFAULT_NODE_SELECTOR_ENABLED="no"
DEFAULT_NODE_SELECTOR=""

STATE_ROOT="${XDG_CONFIG_HOME:-$HOME/.config}/atlas-install-server"
STATE_FILE="$STATE_ROOT/k8s-wizard.env"
CACHE_ROOT="$STATE_ROOT/cache"
OUTPUT_DIR="${PWD}/atlas-install-kubernetes"
CLI_OUTPUT_DIR=""
CLI_DB_HOST=""
DB_HOST_EXPLICIT=0
DB_SSL_EXPLICIT=0
DB_SSL_VERIFY_EXPLICIT=0
CLI_DB_SSL=""
CLI_DB_SSL_VERIFY=""
CLI_DB_SSL_CA=""
PRESERVE_EXISTING_PASSWORDS=0
APPLY_MANIFESTS=""
MANAGE_SECRETS=""
NON_INTERACTIVE=0
TLS_SECRET_CHANGED=0
BOOTSTRAP_SECRET_CHANGED=0
DB_ADMIN_SECRET_CHANGED=0
SELF_UPDATE_OVERRIDE=""
ORIGINAL_ARGS=("$@")

usage() {
  cat <<USAGE
ATLAS Installation Server Kubernetes wizard $WIZARD_VERSION

Usage: $(basename "$0") [options]

Options:
  --output-dir DIR       Directory where rendered manifests are written
  --db-host HOST         Database IP/hostname used for RW, RO and broker connections
  --db-ssl               Enable TLS/SSL for database connections
  --no-db-ssl            Disable TLS/SSL for database connections
  --db-ssl-verify        Verify the database TLS server certificate
  --no-db-ssl-verify     Do not verify the database TLS server certificate
  --db-ssl-ca PATH       CA bundle path inside the application container
  --generate-only        Render manifests only; do not touch the cluster
  --apply                Render/apply manifests and manage secrets
  --non-interactive      Use stored/default values; existing secrets are kept unless explicitly requested
  --reset-state          Forget stored non-secret wizard choices
  --self-update          Enable wizard self-update for this and future runs
  --no-self-update       Disable wizard self-update for this and future runs
  -h, --help             Show this help

Optional environment variables for automation:
  GITHUB_TOKEN                        GitHub token for private repositories
  ATLAS_UPDATE_BOOTSTRAP_SECRET=1     Update an existing bootstrap secret in non-interactive mode
  ATLAS_UPDATE_TLS_SECRET=1           Update an existing TLS secret in non-interactive mode
  ATLAS_DB_HOST                       Database IP/hostname used for RW, RO and broker connections
  ATLAS_DB_SSL=1                      Enable database TLS/SSL
  ATLAS_DB_SSL_VERIFY=1               Verify database TLS server certificate
  ATLAS_DB_SSL_CA                     CA bundle path inside the application container
  ATLAS_DB_RW_PASSWORD                Bootstrap RW database password
  ATLAS_DB_RO_PASSWORD                Bootstrap RO database password
  ATLAS_DB_BROKER_PASSWORD            Bootstrap broker database password
  ATLAS_LOCAL_ADMIN_PASSWORD           Initial/reset local admin password
  ATLAS_DB_BOOTSTRAP_USER              Persistent schema-migration DB user (default: root)
  ATLAS_DB_BOOTSTRAP_PASSWORD          Schema-migration DB password stored in a dedicated Kubernetes Secret
  ATLAS_TLS_CERT_FILE                 Host certificate/full-chain PEM path
  ATLAS_TLS_KEY_FILE                  Host private-key PEM path
  ATLAS_WIZARD_AUTO_UPDATE=1           Enable self-update in non-interactive mode
  ATLAS_NODE_SELECTOR                   Optional comma-separated key=value node selector(s)
USAGE
}

log() { printf '%s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
need_cmd() { command -v "$1" >/dev/null 2>&1 || die "required command not found: $1"; }

prompt() {
  local label="$1" default="${2-}" answer
  if (( NON_INTERACTIVE )); then printf '%s' "$default"; return 0; fi
  if [[ -n "$default" ]]; then
    read -r -p "$label [$default]: " answer
    printf '%s' "${answer:-$default}"
  else
    read -r -p "$label: " answer
    printf '%s' "$answer"
  fi
}

prompt_secret() {
  local label="$1" answer
  if (( NON_INTERACTIVE )); then printf ''; return 0; fi
  read -r -s -p "$label: " answer
  printf '\n' >&2
  printf '%s' "$answer"
}

yesno() {
  local label="$1" default="${2:-y}" answer suffix
  if (( NON_INTERACTIVE )); then [[ "$default" =~ ^[Yy]$ ]]; return; fi
  if [[ "$default" =~ ^[Yy]$ ]]; then suffix="Y/n"; else suffix="y/N"; fi
  read -r -p "$label [$suffix]: " answer
  answer="${answer:-$default}"
  [[ "$answer" =~ ^[Yy]([Ee][Ss])?$ ]]
}

truthy() { [[ "${1:-}" =~ ^(1|true|TRUE|yes|YES|y|Y)$ ]]; }

write_if_changed() {
  local src="$1" dst="$2"
  mkdir -p "$(dirname "$dst")"
  if [[ -f "$dst" ]] && cmp -s "$src" "$dst"; then
    rm -f "$src"
    return 1
  fi
  mv -f "$src" "$dst"
  return 0
}

save_state() {
  mkdir -p "$STATE_ROOT"
  chmod 700 "$STATE_ROOT"
  umask 077
  local tmp
  tmp="$(mktemp "$STATE_ROOT/.k8s-wizard.env.XXXXXX")"
  {
    printf 'GITHUB_REPO=%q\n' "$GITHUB_REPO"
    printf 'GITHUB_REF=%q\n' "$GITHUB_REF"
    printf 'NAMESPACE=%q\n' "$NAMESPACE"
    printf 'APP_NAME=%q\n' "$APP_NAME"
    printf 'IMAGE=%q\n' "$IMAGE"
    printf 'PUBLIC_HOSTNAME=%q\n' "$PUBLIC_HOSTNAME"
    printf 'INGRESS_CLASS=%q\n' "$INGRESS_CLASS"
    printf 'STORAGE_SIZE=%q\n' "$STORAGE_SIZE"
    printf 'STORAGE_CLASS=%q\n' "$STORAGE_CLASS"
    printf 'PVC_ACCESS_MODE=%q\n' "$PVC_ACCESS_MODE"
    printf 'IMAGE_PULL_POLICY=%q\n' "$IMAGE_PULL_POLICY"
    printf 'CPU_REQUEST=%q\n' "$CPU_REQUEST"
    printf 'MEMORY_REQUEST=%q\n' "$MEMORY_REQUEST"
    printf 'CPU_LIMIT=%q\n' "$CPU_LIMIT"
    printf 'MEMORY_LIMIT=%q\n' "$MEMORY_LIMIT"
    printf 'REPLICAS=%q\n' "$REPLICAS"
    printf 'ENABLE_MAINTENANCE=%q\n' "$ENABLE_MAINTENANCE"
    printf 'PLOTS_SCHEDULE=%q\n' "$PLOTS_SCHEDULE"
    printf 'CLEANUP_SCHEDULE=%q\n' "$CLEANUP_SCHEDULE"
    printf 'DB_NAME=%q\n' "$DB_NAME"
    printf 'DB_HOST=%q\n' "$DB_HOST"
    printf 'DB_RW_HOST=%q\n' "$DB_RW_HOST"
    printf 'DB_RW_USER=%q\n' "$DB_RW_USER"
    printf 'DB_RO_HOST=%q\n' "$DB_RO_HOST"
    printf 'DB_RO_USER=%q\n' "$DB_RO_USER"
    printf 'DB_BROKER_HOST=%q\n' "$DB_BROKER_HOST"
    printf 'DB_BROKER_USER=%q\n' "$DB_BROKER_USER"
    printf 'DB_SSL=%q\n' "$DB_SSL"
    printf 'DB_SSL_VERIFY=%q\n' "$DB_SSL_VERIFY"
    printf 'DB_SSL_CA=%q\n' "$DB_SSL_CA"
    printf 'ATLAS_VO_VALUE=%q\n' "$ATLAS_VO_VALUE"
    printf 'ATLAS_EMAIL_VALUE=%q\n' "$ATLAS_EMAIL_VALUE"
    printf 'ATLAS_CONTACTS_VALUE=%q\n' "$ATLAS_CONTACTS_VALUE"
    printf 'ATLAS_DEFAULT_INFOSYS_VALUE=%q\n' "$ATLAS_DEFAULT_INFOSYS_VALUE"
    printf 'DB_BOOTSTRAP_USER=%q\n' "$DB_BOOTSTRAP_USER"
    printf 'ATLAS_ACTIVITY_PERIOD_VALUE=%q\n' "$ATLAS_ACTIVITY_PERIOD_VALUE"
    printf 'ATLAS_DEBUG_VALUE=%q\n' "$ATLAS_DEBUG_VALUE"
    printf 'TLS_CERT_PATH=%q\n' "$TLS_CERT_PATH"
    printf 'TLS_KEY_PATH=%q\n' "$TLS_KEY_PATH"
    printf 'AUTO_UPDATE_WIZARD=%q\n' "$AUTO_UPDATE_WIZARD"
    printf 'NODE_SELECTOR_ENABLED=%q\n' "$NODE_SELECTOR_ENABLED"
    printf 'NODE_SELECTOR=%q\n' "$NODE_SELECTOR"
    printf 'OUTPUT_DIR=%q\n' "$OUTPUT_DIR"
  } > "$tmp"
  chmod 600 "$tmp"
  if [[ -f "$STATE_FILE" ]] && cmp -s "$tmp" "$STATE_FILE"; then
    rm -f "$tmp"
    log "State unchanged: $STATE_FILE"
  else
    mv -f "$tmp" "$STATE_FILE"
    log "Saved non-secret choices: $STATE_FILE"
  fi
}

load_state() {
  [[ -f "$STATE_FILE" ]] || return 1
  # Written exclusively by this script with shell-escaped values.
  # shellcheck disable=SC1090
  source "$STATE_FILE"
}

validate_dns_label() { [[ "$1" =~ ^[a-z0-9]([-a-z0-9]*[a-z0-9])?$ ]] || die "invalid Kubernetes name: $1"; }
validate_repo() { [[ "$1" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || die "GitHub repository must be owner/repository"; }
validate_db_host() {
  local host="$1"
  [[ -n "$host" ]] || die "database host/IP may not be empty"
  [[ "$host" != *[[:space:]]* ]] || die "database host/IP may not contain whitespace"
  [[ "$host" != *"/"* ]] || die "database host/IP must not contain a path"
  [[ "$host" != *"://"* ]] || die "database host/IP must be a host or IP, not a URL"
}


validate_node_selector() {
  local spec="$1" item key value
  [[ -z "$spec" ]] && return 0
  IFS=',' read -r -a _selectors <<< "$spec"
  for item in "${_selectors[@]}"; do
    [[ "$item" == *=* ]] || die "invalid nodeSelector entry '$item' (expected key=value)"
    key="${item%%=*}"; value="${item#*=}"
    [[ -n "$key" && -n "$value" ]] || die "invalid nodeSelector entry '$item'"
    [[ "$key" =~ ^([A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?)(/[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?)?$ ]] || die "invalid nodeSelector key: $key"
    [[ "$value" =~ ^[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?$ ]] || die "invalid nodeSelector value for $key: $value"
  done
}

yaml_dquote() {
  local v="$1"
  v="${v//\\/\\\\}"
  v="${v//\"/\\\"}"
  printf '"%s"' "$v"
}

render_node_selector_block() {
  local spec="$1" item key value
  validate_node_selector "$spec"
  printf '      nodeSelector:\n'
  IFS=',' read -r -a _selectors <<< "$spec"
  for item in "${_selectors[@]}"; do
    key="${item%%=*}"; value="${item#*=}"
    printf '        %s: %s\n' "$key" "$(yaml_dquote "$value")"
  done
}

render_maintenance_scheduling_block() {
  case "$PVC_ACCESS_MODE" in
    ReadWriteOnce)
      cat <<EOF
          affinity:
            podAffinity:
              requiredDuringSchedulingIgnoredDuringExecution:
                - labelSelector:
                    matchLabels:
                      app: ${APP_NAME}
                  topologyKey: kubernetes.io/hostname
EOF
      ;;
    ReadWriteOncePod)
      die "maintenance CronJobs cannot share a ReadWriteOncePod PVC with the server; use ReadWriteOnce/ReadWriteMany or disable maintenance CronJobs"
      ;;
    *)
      :
      ;;
  esac
}

self_script_path() {
  local src="${BASH_SOURCE[0]}"
  if command -v readlink >/dev/null 2>&1; then
    readlink -f "$src" 2>/dev/null || printf '%s' "$src"
  else
    printf '%s' "$src"
  fi
}

maybe_self_update() {
  [[ "$AUTO_UPDATE_WIZARD" == yes ]] || return 0
  [[ "${ATLAS_WIZARD_SKIP_SELF_UPDATE:-0}" != 1 ]] || return 0
  local self remote_script remote_sum url_base tmp_script tmp_sum expected actual auth=()
  self="$(self_script_path)"
  [[ -f "$self" ]] || { log "WARNING: cannot self-update: current script path is not a regular file: $self"; return 0; }
  [[ -w "$self" ]] || { log "WARNING: cannot self-update: script is not writable: $self"; return 0; }
  url_base="https://raw.githubusercontent.com/${GITHUB_REPO}/${GITHUB_REF}/scripts"
  remote_script="$url_base/atlas-install-k8s-wizard.sh"
  remote_sum="$url_base/atlas-install-k8s-wizard.sh.sha256"
  tmp_script="$(mktemp)"; tmp_sum="$(mktemp)"
  [[ -n "${GITHUB_TOKEN:-}" ]] && auth=(-H "Authorization: Bearer ${GITHUB_TOKEN}")
  if ! curl --fail --location --silent --show-error "${auth[@]}" "$remote_script" -o "$tmp_script"; then
    rm -f "$tmp_script" "$tmp_sum"
    log "WARNING: wizard auto-update check failed; continuing with local copy."
    return 0
  fi
  if ! curl --fail --location --silent --show-error "${auth[@]}" "$remote_sum" -o "$tmp_sum"; then
    rm -f "$tmp_script" "$tmp_sum"
    log "WARNING: remote wizard checksum is unavailable; refusing self-update."
    return 0
  fi
  expected="$(awk 'NF {print $1; exit}' "$tmp_sum")"
  actual="$(sha256sum "$tmp_script" | awk '{print $1}')"
  if [[ ! "$expected" =~ ^[0-9a-fA-F]{64}$ || "${expected,,}" != "$actual" ]]; then
    rm -f "$tmp_script" "$tmp_sum"
    log "WARNING: remote wizard checksum verification failed; refusing self-update."
    return 0
  fi
  local remote_version newest
  remote_version="$(sed -n 's/^WIZARD_VERSION="\([^"]*\)"/\1/p' "$tmp_script" | head -n1)"
  [[ -n "$remote_version" ]] || { rm -f "$tmp_script" "$tmp_sum"; log "WARNING: remote wizard has no version marker; refusing self-update."; return 0; }
  if [[ "$remote_version" == "$WIZARD_VERSION" ]]; then
    rm -f "$tmp_script" "$tmp_sum"
    log "Wizard is already current ($WIZARD_VERSION)."
    return 0
  fi
  newest="$(printf '%s\n%s\n' "$WIZARD_VERSION" "$remote_version" | sort -V | tail -n1)"
  if [[ "$newest" != "$remote_version" ]]; then
    rm -f "$tmp_script" "$tmp_sum"
    log "Remote wizard $remote_version is older than local $WIZARD_VERSION; keeping local copy."
    return 0
  fi
  chmod --reference="$self" "$tmp_script" 2>/dev/null || chmod 0755 "$tmp_script"
  mv -f "$tmp_script" "$self"
  rm -f "$tmp_sum"
  log "Wizard updated from $WIZARD_VERSION to $remote_version via $GITHUB_REPO@$GITHUB_REF"
  log "Restarting automatically with the updated wizard."
  export ATLAS_WIZARD_SKIP_SELF_UPDATE=1
  exec bash "$self" "${ORIGINAL_ARGS[@]}"
}

fetch_template() {
  local remote_path="$1" local_path="$2" url tmp auth=()
  url="https://raw.githubusercontent.com/${GITHUB_REPO}/${GITHUB_REF}/${remote_path}"
  tmp="${local_path}.tmp.$$"
  [[ -n "${GITHUB_TOKEN:-}" ]] && auth=(-H "Authorization: Bearer ${GITHUB_TOKEN}")
  mkdir -p "$(dirname "$local_path")"
  if ! curl --fail --location --silent --show-error "${auth[@]}" "$url" -o "$tmp"; then
    rm -f "$tmp"
    if [[ -s "$local_path" ]]; then
      log "WARNING: cannot refresh $remote_path from GitHub; using cached template."
      return 0
    fi
    die "cannot download $remote_path from $GITHUB_REPO ref $GITHUB_REF and no cached template is available"
  fi
  [[ -s "$tmp" ]] || { rm -f "$tmp"; die "downloaded template is empty: $remote_path"; }
  if [[ -f "$local_path" ]] && cmp -s "$tmp" "$local_path"; then
    rm -f "$tmp"
    log "Template unchanged: $remote_path"
  else
    mv -f "$tmp" "$local_path"
    log "Template downloaded/updated: $remote_path"
  fi
}

render_template() {
  local src="$1" dst="$2" content storage_block node_selector_block maintenance_resource_block maintenance_scheduling_block ingress_class_annotation tmp
  content="$(cat "$src")"
  [[ -n "$STORAGE_CLASS" ]] && storage_block="  storageClassName: ${STORAGE_CLASS}"$'\n' || storage_block=""
  node_selector_block=""
  if [[ "$NODE_SELECTOR_ENABLED" == yes && -n "$NODE_SELECTOR" ]]; then
    node_selector_block="$(render_node_selector_block "$NODE_SELECTOR")"
  fi
  maintenance_resource_block=""
  maintenance_scheduling_block=""
  ingress_class_annotation=""
  if [[ "${INGRESS_CLASS,,}" == "haproxy" ]]; then
    ingress_class_annotation='    kubernetes.io/ingress.class: "haproxy"'
  fi
  if [[ "$ENABLE_MAINTENANCE" == yes ]]; then
    maintenance_resource_block="  - 50-maintenance-cronjobs.yaml"
    maintenance_scheduling_block="$(render_maintenance_scheduling_block)"
  fi
  content="${content//\{\{NAMESPACE\}\}/$NAMESPACE}"
  content="${content//\{\{APP_NAME\}\}/$APP_NAME}"
  content="${content//\{\{IMAGE\}\}/$IMAGE}"
  content="${content//\{\{PUBLIC_HOSTNAME\}\}/$PUBLIC_HOSTNAME}"
  content="${content//\{\{INGRESS_CLASS\}\}/$INGRESS_CLASS}"
  content="${content//\{\{INGRESS_CLASS_ANNOTATION\}\}/$ingress_class_annotation}"
  content="${content//\{\{STORAGE_SIZE\}\}/$STORAGE_SIZE}"
  content="${content//\{\{STORAGE_CLASS_BLOCK\}\}/$storage_block}"
  content="${content//\{\{PVC_ACCESS_MODE\}\}/$PVC_ACCESS_MODE}"
  content="${content//\{\{IMAGE_PULL_POLICY\}\}/$IMAGE_PULL_POLICY}"
  content="${content//\{\{CPU_REQUEST\}\}/$CPU_REQUEST}"
  content="${content//\{\{MEMORY_REQUEST\}\}/$MEMORY_REQUEST}"
  content="${content//\{\{CPU_LIMIT\}\}/$CPU_LIMIT}"
  content="${content//\{\{MEMORY_LIMIT\}\}/$MEMORY_LIMIT}"
  content="${content//\{\{REPLICAS\}\}/$REPLICAS}"
  content="${content//\{\{NODE_SELECTOR_BLOCK\}\}/$node_selector_block}"
  content="${content//\{\{MAINTENANCE_RESOURCE_BLOCK\}\}/$maintenance_resource_block}"
  content="${content//\{\{MAINTENANCE_SCHEDULING_BLOCK\}\}/$maintenance_scheduling_block}"
  content="${content//\{\{PLOTS_SCHEDULE\}\}/$PLOTS_SCHEDULE}"
  content="${content//\{\{CLEANUP_SCHEDULE\}\}/$CLEANUP_SCHEDULE}"
  grep -q '{{[A-Z0-9_]*}}' <<<"$content" && die "unresolved placeholder while rendering $src"
  tmp="$(mktemp)"
  printf '%s\n' "$content" > "$tmp"
  if write_if_changed "$tmp" "$dst"; then log "Generated/updated manifest: $dst"; else log "Manifest unchanged: $dst"; fi
}

secret_exists() { kubectl -n "$NAMESPACE" get secret "$1" >/dev/null 2>&1; }

get_existing_bootstrap_env() {
  kubectl -n "$NAMESPACE" get secret "${APP_NAME}-bootstrap" -o jsonpath='{.data.atlas-install\.env}' 2>/dev/null | base64 -d 2>/dev/null || true
}

env_raw_value() { local data="$1" key="$2"; awk -F= -v k="$key" '$1==k {print substr($0,index($0,"=")+1); exit}' <<<"$data"; }

env_plain_value() {
  local raw="$1"
  if [[ "$raw" == '"'*'"' && ${#raw} -ge 2 ]]; then raw="${raw:1:${#raw}-2}"; fi
  raw="${raw//\\\"/\"}"
  raw="${raw//\\\\/\\}"
  printf '%s' "$raw"
}

env_quote() {
  local value="$1"
  [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]] || die "configuration values may not contain newlines"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '"%s"' "$value"
}

seed_bootstrap_defaults_from_secret() {
  local data="$1" raw plain
  local existing_bootstrap_user
  existing_bootstrap_user="$(env_plain_value "$(env_raw_value "$1" ATLAS_DB_BOOTSTRAP_USER)")"
  [[ -n "$existing_bootstrap_user" ]] && DB_BOOTSTRAP_USER="$existing_bootstrap_user"
  # Pre-r12 bootstrap Secrets have no DB TLS keys; treat absence as TLS disabled.
  DB_SSL_RAW=""; DB_SSL_VERIFY_RAW=""; DB_SSL=no; DB_SSL_VERIFY=no; DB_SSL_CA=""
  while IFS='|' read -r key var; do
    raw="$(env_raw_value "$data" "$key")"
    [[ -n "$raw" ]] || continue
    plain="$(env_plain_value "$raw")"
    printf -v "$var" '%s' "$plain"
  done <<'MAP'
ATLAS_DB_NAME|DB_NAME
ATLAS_DB_RW_HOST|DB_RW_HOST
ATLAS_DB_RW_USER|DB_RW_USER
ATLAS_DB_RO_HOST|DB_RO_HOST
ATLAS_DB_RO_USER|DB_RO_USER
ATLAS_DB_BROKER_HOST|DB_BROKER_HOST
ATLAS_DB_BROKER_USER|DB_BROKER_USER
ATLAS_DB_SSL|DB_SSL_RAW
ATLAS_DB_SSL_VERIFY|DB_SSL_VERIFY_RAW
ATLAS_DB_SSL_CA|DB_SSL_CA
ATLAS_VO|ATLAS_VO_VALUE
ATLAS_EMAIL|ATLAS_EMAIL_VALUE
ATLAS_CONTACTS|ATLAS_CONTACTS_VALUE
ATLAS_DEFAULT_INFOSYS|ATLAS_DEFAULT_INFOSYS_VALUE
ATLAS_ACTIVITY_PERIOD|ATLAS_ACTIVITY_PERIOD_VALUE
ATLAS_DEBUG|ATLAS_DEBUG_VALUE
MAP
  if [[ -n "${DB_SSL_RAW:-}" ]]; then truthy "$DB_SSL_RAW" && DB_SSL=yes || DB_SSL=no; fi
  if [[ -n "${DB_SSL_VERIFY_RAW:-}" ]]; then truthy "$DB_SSL_VERIFY_RAW" && DB_SSL_VERIFY=yes || DB_SSL_VERIFY=no; fi
}

prompt_bootstrap_nonsecrets() {
  DB_NAME="$(prompt 'Database name' "$DB_NAME")"
  DB_RW_HOST="$DB_HOST"
  DB_RO_HOST="$DB_HOST"
  DB_BROKER_HOST="$DB_HOST"
  DB_RW_USER="$(prompt 'RW database user' "$DB_RW_USER")"
  DB_RO_USER="$(prompt 'RO database user' "$DB_RO_USER")"
  DB_BROKER_USER="$(prompt 'Broker database user' "$DB_BROKER_USER")"
  ATLAS_VO_VALUE="$(prompt 'ATLAS VO' "$ATLAS_VO_VALUE")"
  ATLAS_EMAIL_VALUE="$(prompt 'Notification email' "$ATLAS_EMAIL_VALUE")"
  ATLAS_CONTACTS_VALUE="$(prompt 'Contacts' "$ATLAS_CONTACTS_VALUE")"
  ATLAS_DEFAULT_INFOSYS_VALUE="$(prompt 'Default infosys' "$ATLAS_DEFAULT_INFOSYS_VALUE")"
  ATLAS_ACTIVITY_PERIOD_VALUE="$(prompt 'Activity period' "$ATLAS_ACTIVITY_PERIOD_VALUE")"
  ATLAS_DEBUG_VALUE="$(prompt 'Debug flag' "$ATLAS_DEBUG_VALUE")"
}

require_or_keep_password_raw() {
  local env_name="$1" label="$2" existing_raw="$3" value
  if (( PRESERVE_EXISTING_PASSWORDS )) && [[ -n "$existing_raw" ]]; then
    printf '%s' "$existing_raw"
    return 0
  fi
  value="${!env_name:-}"
  if [[ -n "$value" ]]; then env_quote "$value"; return; fi
  if (( NON_INTERACTIVE )); then
    [[ -n "$existing_raw" ]] || die "$env_name must be set in non-interactive mode when no existing secret value exists"
    printf '%s' "$existing_raw"
    return
  fi
  if [[ -n "$existing_raw" ]]; then
    value="$(prompt_secret "$label (leave blank to keep current)")"
    [[ -z "$value" ]] && printf '%s' "$existing_raw" || env_quote "$value"
  else
    while [[ -z "$value" ]]; do value="$(prompt_secret "$label")"; done
    env_quote "$value"
  fi
}

apply_bootstrap_secret_content() {
  local existing="$1" rw_old ro_old broker_old rw_pw ro_pw broker_pw admin_old local_admin_pw bootstrap_user bootstrap_old bootstrap_pw tmp result
  rw_old="$(env_raw_value "$existing" ATLAS_DB_RW_PASSWORD)"
  ro_old="$(env_raw_value "$existing" ATLAS_DB_RO_PASSWORD)"
  broker_old="$(env_raw_value "$existing" ATLAS_DB_BROKER_PASSWORD)"
  admin_old="$(env_raw_value "$existing" ATLAS_LOCAL_ADMIN_PASSWORD)"
  bootstrap_old="$(env_raw_value "$existing" ATLAS_DB_BOOTSTRAP_PASSWORD)"
  bootstrap_user="${ATLAS_DB_BOOTSTRAP_USER:-$DB_BOOTSTRAP_USER}"
  rw_pw="$(require_or_keep_password_raw ATLAS_DB_RW_PASSWORD 'RW database password' "$rw_old")"
  ro_pw="$(require_or_keep_password_raw ATLAS_DB_RO_PASSWORD 'RO database password' "$ro_old")"
  broker_pw="$(require_or_keep_password_raw ATLAS_DB_BROKER_PASSWORD 'Broker database password' "$broker_old")"
  bootstrap_pw=""
  if [[ -n "${ATLAS_LOCAL_ADMIN_PASSWORD:-}" ]]; then
    local_admin_pw="$ATLAS_LOCAL_ADMIN_PASSWORD"
  elif (( NON_INTERACTIVE )); then
    if [[ -n "$admin_old" ]]; then local_admin_pw="$(env_plain_value "$admin_old")"; else local_admin_pw="$DEFAULT_LOCAL_ADMIN_PASSWORD"; fi
  else
    local current_admin_default entered_admin_pw
    if [[ -n "$admin_old" ]]; then current_admin_default="$(env_plain_value "$admin_old")"; else current_admin_default="$DEFAULT_LOCAL_ADMIN_PASSWORD"; fi
    entered_admin_pw="$(prompt_secret 'Local admin password (blank keeps current/default)')"
    local_admin_pw="${entered_admin_pw:-$current_admin_default}"
  fi
  tmp="$(mktemp)"; chmod 600 "$tmp"
  {
    printf 'ATLAS_PUBLIC_HOSTNAME=%s\n' "$(env_quote "$PUBLIC_HOSTNAME")"
    printf 'ATLAS_DB_NAME=%s\n' "$(env_quote "$DB_NAME")"
    printf 'ATLAS_DB_BOOTSTRAP_USER=%s\n' "$(env_quote "$bootstrap_user")"
    printf 'ATLAS_DB_RW_HOST=%s\n' "$(env_quote "$DB_RW_HOST")"
    printf 'ATLAS_DB_RW_USER=%s\n' "$(env_quote "$DB_RW_USER")"
    printf 'ATLAS_DB_RW_PASSWORD=%s\n' "$rw_pw"
    printf 'ATLAS_DB_RO_HOST=%s\n' "$(env_quote "$DB_RO_HOST")"
    printf 'ATLAS_DB_RO_USER=%s\n' "$(env_quote "$DB_RO_USER")"
    printf 'ATLAS_DB_RO_PASSWORD=%s\n' "$ro_pw"
    printf 'ATLAS_DB_BROKER_HOST=%s\n' "$(env_quote "$DB_BROKER_HOST")"
    printf 'ATLAS_DB_BROKER_USER=%s\n' "$(env_quote "$DB_BROKER_USER")"
    printf 'ATLAS_DB_BROKER_PASSWORD=%s\n' "$broker_pw"
    printf 'ATLAS_DB_SSL=%s\n' "$(env_quote "$([[ "$DB_SSL" == yes ]] && echo 1 || echo 0)")"
    printf 'ATLAS_DB_SSL_VERIFY=%s\n' "$(env_quote "$([[ "$DB_SSL_VERIFY" == yes ]] && echo 1 || echo 0)")"
    printf 'ATLAS_DB_SSL_CA=%s\n' "$(env_quote "$DB_SSL_CA")"
    printf 'ATLAS_DB_AUTO_INIT="1"\n'
    printf 'ATLAS_VO=%s\n' "$(env_quote "$ATLAS_VO_VALUE")"
    printf 'ATLAS_EMAIL=%s\n' "$(env_quote "$ATLAS_EMAIL_VALUE")"
    printf 'ATLAS_CONTACTS=%s\n' "$(env_quote "$ATLAS_CONTACTS_VALUE")"
    printf 'ATLAS_DEFAULT_INFOSYS=%s\n' "$(env_quote "$ATLAS_DEFAULT_INFOSYS_VALUE")"
    printf 'ATLAS_ACTIVITY_PERIOD=%s\n' "$(env_quote "$ATLAS_ACTIVITY_PERIOD_VALUE")"
    printf 'ATLAS_DEBUG=%s\n' "$(env_quote "$ATLAS_DEBUG_VALUE")"
    printf 'ATLAS_LOCAL_ADMIN_PASSWORD=%s\n' "$(env_quote "$local_admin_pw")"
    printf 'ATLAS_LOCAL_AUTH_KEY_FILE="/var/lib/atlas-install/config/local-auth.key"\n'
    printf 'ATLAS_CRL_MAX_AGE_SECONDS="21600"\n'
    printf 'ATLAS_UPLOAD_PATH="/var/lib/atlas-install/log"\n'
    printf 'ATLAS_ARCHIVE_PATH="/var/lib/atlas-install/logbackup"\n'
    printf 'ATLAS_CACHE_PATH="/var/cache/atlas-install"\n'
    printf 'ATLAS_KML_CACHE="/var/cache/atlas-install/install.kml"\n'
  } > "$tmp"
  if [[ "$(cat "$tmp")" != "$existing" ]]; then
    BOOTSTRAP_SECRET_CHANGED=1
  fi
  result="$(kubectl -n "$NAMESPACE" create secret generic "${APP_NAME}-bootstrap" --from-file=atlas-install.env="$tmp" --dry-run=client -o yaml | kubectl apply -f -)"
  rm -f "$tmp"
  log "$result"
}

manage_bootstrap_secret() {
  local name="${APP_NAME}-bootstrap" existing="" do_update=1
  local requested_host="$DB_HOST" requested_ssl="$DB_SSL" requested_verify="$DB_SSL_VERIFY" requested_ca="$DB_SSL_CA" old_rw="" old_ro="" old_broker="" endpoint_changed=0 tls_changed=0
  if secret_exists "$name"; then
    existing="$(get_existing_bootstrap_env)"
    log "Existing bootstrap secret found: ${NAMESPACE}/${name}"
    seed_bootstrap_defaults_from_secret "$existing"
    old_rw="$DB_RW_HOST"; old_ro="$DB_RO_HOST"; old_broker="$DB_BROKER_HOST"
    local old_ssl="$DB_SSL" old_verify="$DB_SSL_VERIFY" old_ca="$DB_SSL_CA"
    DB_SSL="$requested_ssl"; DB_SSL_VERIFY="$requested_verify"; DB_SSL_CA="$requested_ca"
    [[ "$old_ssl" != "$DB_SSL" || "$old_verify" != "$DB_SSL_VERIFY" || "$old_ca" != "$DB_SSL_CA" ]] && tls_changed=1

    # The wizard exposes one database endpoint and maps it to all three application roles.
    DB_HOST="$requested_host"
    DB_RW_HOST="$DB_HOST"; DB_RO_HOST="$DB_HOST"; DB_BROKER_HOST="$DB_HOST"
    if [[ "$old_rw" != "$DB_HOST" || "$old_ro" != "$DB_HOST" || "$old_broker" != "$DB_HOST" || $tls_changed -eq 1 ]]; then
      [[ "$old_rw" != "$DB_HOST" || "$old_ro" != "$DB_HOST" || "$old_broker" != "$DB_HOST" ]] && endpoint_changed=1
      log "Database connection settings change requested: endpoint=${DB_HOST}, TLS=${DB_SSL}, verify=${DB_SSL_VERIFY}, CA=${DB_SSL_CA:-system/default}"
      if (( NON_INTERACTIVE )); then
        if (( DB_HOST_EXPLICIT || DB_SSL_EXPLICIT || DB_SSL_VERIFY_EXPLICIT )) || truthy "${ATLAS_UPDATE_BOOTSTRAP_SECRET:-0}"; then
          do_update=1
        else
          die "stored database endpoint differs from Kubernetes Secret; use --db-host/ATLAS_DB_HOST or ATLAS_UPDATE_BOOTSTRAP_SECRET=1"
        fi
      else
        yesno "Update database connection settings in bootstrap Secret and keep existing DB passwords?" y && do_update=1 || do_update=0
      fi
      if (( ! do_update )); then
        log "Bootstrap secret retained unchanged; database endpoint remains ${old_rw}."
        DB_HOST="$old_rw"; DB_RW_HOST="$old_rw"; DB_RO_HOST="$old_ro"; DB_BROKER_HOST="$old_broker"
        return 0
      fi
      PRESERVE_EXISTING_PASSWORDS=1
      apply_bootstrap_secret_content "$existing"
      PRESERVE_EXISTING_PASSWORDS=0
      log "Database connection settings updated without rotating database passwords."
      return 0
    fi

    if (( NON_INTERACTIVE )); then
      truthy "${ATLAS_UPDATE_BOOTSTRAP_SECRET:-0}" && do_update=1 || do_update=0
    else
      yesno "Update existing bootstrap secret?" y && do_update=1 || do_update=0
    fi
    if (( ! do_update )); then
      log "Bootstrap secret retained unchanged."
      return 0
    fi
    log "Existing non-secret values are proposed as defaults; blank password input keeps the current password."
  else
    log "Bootstrap secret does not exist and will be created: ${NAMESPACE}/${name}"
    DB_RW_HOST="$DB_HOST"; DB_RO_HOST="$DB_HOST"; DB_BROKER_HOST="$DB_HOST"
  fi
  prompt_bootstrap_nonsecrets
  apply_bootstrap_secret_content "$existing"
}

manage_db_admin_secret() {
  local secret="${APP_NAME}-db-admin" existing_pw="" existing_user="" pw="" user="${ATLAS_DB_BOOTSTRAP_USER:-$DB_BOOTSTRAP_USER}" result
  if kubectl -n "$NAMESPACE" get secret "$secret" >/dev/null 2>&1; then
    existing_pw="$(kubectl -n "$NAMESPACE" get secret "$secret" -o jsonpath='{.data.ATLAS_DB_BOOTSTRAP_PASSWORD}' 2>/dev/null | base64 -d 2>/dev/null || true)"
    existing_user="$(kubectl -n "$NAMESPACE" get secret "$secret" -o jsonpath='{.data.ATLAS_DB_BOOTSTRAP_USER}' 2>/dev/null | base64 -d 2>/dev/null || true)"
    [[ -n "$existing_user" ]] && user="$existing_user"
  fi
  if [[ -n "${ATLAS_DB_BOOTSTRAP_PASSWORD:-}" ]]; then
    pw="$ATLAS_DB_BOOTSTRAP_PASSWORD"
  elif (( NON_INTERACTIVE )); then
    pw="$existing_pw"
  else
    local entered
    entered="$(prompt_secret 'Database schema-migration/admin password (blank keeps existing; recommended for automatic migrations after restores)')"
    pw="${entered:-$existing_pw}"
  fi
  if [[ -z "$pw" ]]; then
    log "No persistent database schema-migration password configured; startup migrations will fall back to the RW account."
    return 0
  fi
  if [[ "$existing_pw" != "$pw" || "$existing_user" != "$user" ]]; then DB_ADMIN_SECRET_CHANGED=1; fi
  result="$(kubectl -n "$NAMESPACE" create secret generic "$secret" \
    --from-literal=ATLAS_DB_BOOTSTRAP_USER="$user" \
    --from-literal=ATLAS_DB_BOOTSTRAP_PASSWORD="$pw" \
    --dry-run=client -o yaml | kubectl apply -f -)"
  log "$result"
  log "Persistent database schema-migration credential stored in Kubernetes Secret ${NAMESPACE}/${secret}."
  log "For encryption at rest, enable Kubernetes API-server/etcd Secret encryption in the cluster."
}
validate_tls_pair() {
  local cert="$1" key="$2" cert_pub key_pub
  [[ -r "$cert" ]] || die "certificate not readable: $cert"
  [[ -r "$key" ]] || die "private key not readable: $key"
  openssl x509 -in "$cert" -noout >/dev/null 2>&1 || die "invalid X.509 certificate: $cert"
  openssl pkey -in "$key" -noout >/dev/null 2>&1 || die "invalid private key: $key"
  cert_pub="$(openssl x509 -in "$cert" -pubkey -noout | openssl pkey -pubin -outform DER 2>/dev/null | sha256sum | awk '{print $1}')"
  key_pub="$(openssl pkey -in "$key" -pubout -outform DER 2>/dev/null | sha256sum | awk '{print $1}')"
  [[ "$cert_pub" == "$key_pub" ]] || die "certificate and private key do not match"
  if openssl x509 -help 2>&1 | grep -q -- '-checkhost'; then
    openssl x509 -in "$cert" -noout -checkhost "$PUBLIC_HOSTNAME" >/dev/null 2>&1 || die "certificate does not match host $PUBLIC_HOSTNAME"
  fi
}

existing_tls_digest() {
  local key="$1"
  kubectl -n "$NAMESPACE" get secret "${APP_NAME}-tls" -o "jsonpath={.data.${key}}" 2>/dev/null | base64 -d 2>/dev/null | sha256sum | awk '{print $1}' || true
}

manage_tls_secret() {
  local name="${APP_NAME}-tls" exists=0 do_update=1 change_paths=0 cert key old_cert old_key new_cert new_key result
  secret_exists "$name" && exists=1
  if (( exists )); then
    log "Existing TLS secret found: ${NAMESPACE}/${name}"
    if (( NON_INTERACTIVE )); then
      truthy "${ATLAS_UPDATE_TLS_SECRET:-0}" && do_update=1 || do_update=0
    else
      yesno "Update existing TLS secret?" n && do_update=1 || do_update=0
    fi
    if (( ! do_update )); then
      log "TLS secret retained unchanged; stored certificate/key paths remain unchanged."
      return 0
    fi
  else
    log "TLS secret does not exist and will be created: ${NAMESPACE}/${name}"
  fi

  cert="${ATLAS_TLS_CERT_FILE:-$TLS_CERT_PATH}"
  key="${ATLAS_TLS_KEY_FILE:-$TLS_KEY_PATH}"

  if [[ -n "${ATLAS_TLS_CERT_FILE:-}" || -n "${ATLAS_TLS_KEY_FILE:-}" ]]; then
    change_paths=1
  elif (( ! exists )) || [[ -z "$TLS_CERT_PATH" || -z "$TLS_KEY_PATH" ]]; then
    change_paths=1
  elif (( ! NON_INTERACTIVE )); then
    yesno "Change the stored host certificate/key paths?" n && change_paths=1 || change_paths=0
  fi

  if (( change_paths )); then
    if (( NON_INTERACTIVE )); then
      [[ -n "$cert" && -n "$key" ]] || die "ATLAS_TLS_CERT_FILE and ATLAS_TLS_KEY_FILE (or previously stored paths) are required"
    else
      cert="$(prompt 'Host certificate/full-chain PEM path' "$cert")"
      key="$(prompt 'Host private-key PEM path' "$key")"
    fi
  else
    cert="$TLS_CERT_PATH"
    key="$TLS_KEY_PATH"
    log "Reusing stored TLS paths: certificate=$cert key=$key"
  fi

  validate_tls_pair "$cert" "$key"
  TLS_CERT_PATH="$cert"
  TLS_KEY_PATH="$key"

  new_cert="$(sha256sum "$cert" | awk '{print $1}')"
  new_key="$(sha256sum "$key" | awk '{print $1}')"
  if (( exists )); then
    old_cert="$(existing_tls_digest 'tls\\.crt')"
    old_key="$(existing_tls_digest 'tls\\.key')"
    if [[ "$new_cert" == "$old_cert" && "$new_key" == "$old_key" ]]; then
      log "TLS secret content already matches the selected files; no update required."
      return 0
    fi
  fi

  result="$(kubectl -n "$NAMESPACE" create secret tls "$name" --cert="$cert" --key="$key" --dry-run=client -o yaml | kubectl apply -f -)"
  log "$result"
  TLS_SECRET_CHANGED=1
}

while (($#)); do
  case "$1" in
    --output-dir) [[ $# -ge 2 ]] || die "--output-dir requires a value"; CLI_OUTPUT_DIR="$2"; shift 2 ;;
    --db-host) [[ $# -ge 2 ]] || die "--db-host requires a value"; CLI_DB_HOST="$2"; DB_HOST_EXPLICIT=1; shift 2 ;;
    --db-ssl) CLI_DB_SSL=yes; DB_SSL_EXPLICIT=1; shift ;;
    --no-db-ssl) CLI_DB_SSL=no; DB_SSL_EXPLICIT=1; shift ;;
    --db-ssl-verify) CLI_DB_SSL_VERIFY=yes; DB_SSL_VERIFY_EXPLICIT=1; shift ;;
    --no-db-ssl-verify) CLI_DB_SSL_VERIFY=no; DB_SSL_VERIFY_EXPLICIT=1; shift ;;
    --db-ssl-ca) [[ $# -ge 2 ]] || die "--db-ssl-ca requires a value"; CLI_DB_SSL_CA="$2"; DB_SSL_VERIFY_EXPLICIT=1; shift 2 ;;
    --generate-only) APPLY_MANIFESTS="no"; MANAGE_SECRETS="no"; shift ;;
    --apply) APPLY_MANIFESTS="yes"; MANAGE_SECRETS="yes"; shift ;;
    --non-interactive) NON_INTERACTIVE=1; shift ;;
    --reset-state) rm -f "$STATE_FILE"; shift ;;
    --self-update) SELF_UPDATE_OVERRIDE=yes; shift ;;
    --no-self-update) SELF_UPDATE_OVERRIDE=no; shift ;;
    -h|--help) usage; exit 0 ;;
    *) die "unknown option: $1" ;;
  esac
done

need_cmd curl
need_cmd sha256sum
need_cmd cmp
need_cmd sort

had_state=0
load_state && had_state=1 || true

GITHUB_REPO="${GITHUB_REPO:-$DEFAULT_REPO}"
GITHUB_REF="${GITHUB_REF:-$DEFAULT_REF}"
NAMESPACE="${NAMESPACE:-$DEFAULT_NAMESPACE}"
APP_NAME="${APP_NAME:-$DEFAULT_APP_NAME}"
IMAGE="${IMAGE:-$DEFAULT_IMAGE}"
PUBLIC_HOSTNAME="${PUBLIC_HOSTNAME:-$DEFAULT_HOSTNAME}"
INGRESS_CLASS="${INGRESS_CLASS:-$DEFAULT_INGRESS_CLASS}"
STORAGE_SIZE="${STORAGE_SIZE:-$DEFAULT_STORAGE_SIZE}"
STORAGE_CLASS="${STORAGE_CLASS:-}"
PVC_ACCESS_MODE="${PVC_ACCESS_MODE:-$DEFAULT_PVC_ACCESS_MODE}"
IMAGE_PULL_POLICY="${IMAGE_PULL_POLICY:-$DEFAULT_IMAGE_PULL_POLICY}"
CPU_REQUEST="${CPU_REQUEST:-$DEFAULT_CPU_REQUEST}"
MEMORY_REQUEST="${MEMORY_REQUEST:-$DEFAULT_MEMORY_REQUEST}"
CPU_LIMIT="${CPU_LIMIT:-$DEFAULT_CPU_LIMIT}"
MEMORY_LIMIT="${MEMORY_LIMIT:-$DEFAULT_MEMORY_LIMIT}"
REPLICAS="${REPLICAS:-$DEFAULT_REPLICAS}"
ENABLE_MAINTENANCE="${ENABLE_MAINTENANCE:-no}"
PLOTS_SCHEDULE="${PLOTS_SCHEDULE:-$DEFAULT_PLOTS_SCHEDULE}"
CLEANUP_SCHEDULE="${CLEANUP_SCHEDULE:-$DEFAULT_CLEANUP_SCHEDULE}"
DB_NAME="${DB_NAME:-$DEFAULT_DB_NAME}"
if [[ -n "$CLI_DB_HOST" ]]; then
  DB_HOST="$CLI_DB_HOST"
elif [[ -n "${ATLAS_DB_HOST:-}" ]]; then
  DB_HOST="$ATLAS_DB_HOST"
  DB_HOST_EXPLICIT=1
else
  DB_HOST="${DB_HOST:-${DB_RW_HOST:-$DEFAULT_DB_HOST}}"
fi
validate_db_host "$DB_HOST"
DB_RW_HOST="${DB_RW_HOST:-$DB_HOST}"
DB_RW_USER="${DB_RW_USER:-$DEFAULT_DB_RW_USER}"
DB_RO_HOST="${DB_RO_HOST:-$DB_HOST}"
DB_RO_USER="${DB_RO_USER:-$DEFAULT_DB_RO_USER}"
DB_BROKER_HOST="${DB_BROKER_HOST:-$DB_HOST}"
DB_BROKER_USER="${DB_BROKER_USER:-$DEFAULT_DB_BROKER_USER}"
DB_BOOTSTRAP_USER="${DB_BOOTSTRAP_USER:-${ATLAS_DB_BOOTSTRAP_USER:-$DEFAULT_DB_BOOTSTRAP_USER}}"
DB_SSL="${DB_SSL:-$DEFAULT_DB_SSL}"
DB_SSL_VERIFY="${DB_SSL_VERIFY:-$DEFAULT_DB_SSL_VERIFY}"
DB_SSL_CA="${DB_SSL_CA:-$DEFAULT_DB_SSL_CA}"
if [[ -n "${ATLAS_DB_SSL:-}" ]]; then truthy "$ATLAS_DB_SSL" && DB_SSL=yes || DB_SSL=no; DB_SSL_EXPLICIT=1; fi
if [[ -n "${ATLAS_DB_SSL_VERIFY:-}" ]]; then truthy "$ATLAS_DB_SSL_VERIFY" && DB_SSL_VERIFY=yes || DB_SSL_VERIFY=no; DB_SSL_VERIFY_EXPLICIT=1; fi
[[ -n "${ATLAS_DB_SSL_CA:-}" ]] && DB_SSL_CA="$ATLAS_DB_SSL_CA"
[[ -n "$CLI_DB_SSL" ]] && DB_SSL="$CLI_DB_SSL"
[[ -n "$CLI_DB_SSL_VERIFY" ]] && DB_SSL_VERIFY="$CLI_DB_SSL_VERIFY"
[[ -n "$CLI_DB_SSL_CA" ]] && DB_SSL_CA="$CLI_DB_SSL_CA"
ATLAS_VO_VALUE="${ATLAS_VO_VALUE:-$DEFAULT_VO}"
ATLAS_EMAIL_VALUE="${ATLAS_EMAIL_VALUE:-$DEFAULT_EMAIL}"
ATLAS_CONTACTS_VALUE="${ATLAS_CONTACTS_VALUE:-}"
ATLAS_DEFAULT_INFOSYS_VALUE="${ATLAS_DEFAULT_INFOSYS_VALUE:-$DEFAULT_INFOSYS}"
ATLAS_ACTIVITY_PERIOD_VALUE="${ATLAS_ACTIVITY_PERIOD_VALUE:-$DEFAULT_ACTIVITY_PERIOD}"
ATLAS_DEBUG_VALUE="${ATLAS_DEBUG_VALUE:-$DEFAULT_DEBUG}"
TLS_CERT_PATH="${TLS_CERT_PATH:-}"
TLS_KEY_PATH="${TLS_KEY_PATH:-}"
AUTO_UPDATE_WIZARD="$DEFAULT_AUTO_UPDATE_WIZARD"
[[ "$SELF_UPDATE_OVERRIDE" == no ]] && AUTO_UPDATE_WIZARD=no
[[ "$SELF_UPDATE_OVERRIDE" == yes ]] && AUTO_UPDATE_WIZARD=yes
NODE_SELECTOR_ENABLED="${NODE_SELECTOR_ENABLED:-$DEFAULT_NODE_SELECTOR_ENABLED}"
NODE_SELECTOR="${NODE_SELECTOR:-$DEFAULT_NODE_SELECTOR}"
OUTPUT_DIR="${CLI_OUTPUT_DIR:-${OUTPUT_DIR:-${PWD}/atlas-install-kubernetes}}"

maybe_self_update
log "ATLAS Installation Server Kubernetes wizard $WIZARD_VERSION"
log "State: $STATE_FILE"

if (( ! had_state )); then
  GITHUB_REPO="$(prompt 'GitHub repository (owner/repository)' "$GITHUB_REPO")"
  validate_repo "$GITHUB_REPO"
  (( NON_INTERACTIVE )) || yesno "Use GitHub repository '$GITHUB_REPO'?" y || die "repository not confirmed"
else
  GITHUB_REPO="$(prompt 'GitHub repository' "$GITHUB_REPO")"
  validate_repo "$GITHUB_REPO"
fi
GITHUB_REF="$(prompt 'GitHub ref/branch/tag for Kubernetes templates' "$GITHUB_REF")"
NAMESPACE="$(prompt 'Kubernetes namespace' "$NAMESPACE")"
APP_NAME="$(prompt 'Application resource prefix' "$APP_NAME")"
validate_dns_label "$NAMESPACE"; validate_dns_label "$APP_NAME"
IMAGE="$(prompt 'Container image' "$IMAGE")"
PUBLIC_HOSTNAME="$(prompt 'Public hostname' "$PUBLIC_HOSTNAME")"
INGRESS_CLASS="$(prompt 'IngressClass' "$INGRESS_CLASS")"
STORAGE_SIZE="$(prompt 'Persistent volume size' "$STORAGE_SIZE")"
STORAGE_CLASS="$(prompt 'StorageClass (blank = cluster default)' "$STORAGE_CLASS")"
PVC_ACCESS_MODE="$(prompt 'PVC access mode' "$PVC_ACCESS_MODE")"
REPLICAS="$(prompt 'Deployment replicas' "$REPLICAS")"
IMAGE_PULL_POLICY="$(prompt 'Image pull policy' "$IMAGE_PULL_POLICY")"
CPU_REQUEST="$(prompt 'CPU request' "$CPU_REQUEST")"
MEMORY_REQUEST="$(prompt 'Memory request' "$MEMORY_REQUEST")"
CPU_LIMIT="$(prompt 'CPU limit' "$CPU_LIMIT")"
MEMORY_LIMIT="$(prompt 'Memory limit' "$MEMORY_LIMIT")"
DB_HOST="$(prompt 'Database server/IP (RW, RO and broker)' "$DB_HOST")"
validate_db_host "$DB_HOST"
DB_BOOTSTRAP_USER="$(prompt 'Database schema-migration/admin user (used for startup migrations after restores)' "$DB_BOOTSTRAP_USER")"
DB_RW_HOST="$DB_HOST"; DB_RO_HOST="$DB_HOST"; DB_BROKER_HOST="$DB_HOST"
if (( ! NON_INTERACTIVE )); then
  if yesno "Use TLS/SSL for database connections?" "$([[ "$DB_SSL" == yes ]] && echo y || echo n)"; then
    DB_SSL=yes
    if yesno "Verify database TLS server certificate?" "$([[ "$DB_SSL_VERIFY" == yes ]] && echo y || echo n)"; then
      DB_SSL_VERIFY=yes
      DB_SSL_CA="$(prompt 'Database TLS CA bundle path inside container (blank = system trust)' "$DB_SSL_CA")"
    else
      DB_SSL_VERIFY=no; DB_SSL_CA=""
    fi
  else
    DB_SSL=no; DB_SSL_VERIFY=no; DB_SSL_CA=""
  fi
fi
if [[ -n "${ATLAS_NODE_SELECTOR:-}" ]]; then
  NODE_SELECTOR_ENABLED=yes
  NODE_SELECTOR="$ATLAS_NODE_SELECTOR"
elif yesno "Use an optional nodeSelector for the server pod?" "$([[ "$NODE_SELECTOR_ENABLED" == yes ]] && echo y || echo n)"; then
  NODE_SELECTOR_ENABLED=yes
  NODE_SELECTOR="$(prompt 'nodeSelector(s), comma-separated key=value' "$NODE_SELECTOR")"
  [[ -n "$NODE_SELECTOR" ]] || die "nodeSelector enabled but no key=value selector was provided"
  validate_node_selector "$NODE_SELECTOR"
else
  NODE_SELECTOR_ENABLED=no
  NODE_SELECTOR=""
fi
OUTPUT_DIR="$(prompt 'Generated manifest directory' "$OUTPUT_DIR")"

if yesno "Generate maintenance CronJobs?" "$([[ "$ENABLE_MAINTENANCE" == yes ]] && echo y || echo n)"; then
  ENABLE_MAINTENANCE=yes
  PLOTS_SCHEDULE="$(prompt 'Plots CronJob schedule' "$PLOTS_SCHEDULE")"
  CLEANUP_SCHEDULE="$(prompt 'Log-cleanup CronJob schedule' "$CLEANUP_SCHEDULE")"
else
  ENABLE_MAINTENANCE=no
fi

cache_key="${GITHUB_REPO//\//_}/${GITHUB_REF//\//_}"
cache_dir="$CACHE_ROOT/$cache_key"
template_files=(
  00-namespace.yaml
  10-pvc.yaml
  20-deployment.yaml
  30-service.yaml
  40-ingress.yaml
  50-maintenance-cronjobs.yaml
  kustomization.yaml
)
mkdir -p "$OUTPUT_DIR"
for name in "${template_files[@]}"; do
  cache_file="$cache_dir/${name}.tpl"
  fetch_template "kubernetes/templates/${name}.tpl" "$cache_file"
  if [[ "$name" == "50-maintenance-cronjobs.yaml" && "$ENABLE_MAINTENANCE" != yes ]]; then
    rm -f "$OUTPUT_DIR/$name"
    continue
  fi
  render_template "$cache_file" "$OUTPUT_DIR/$name"
done

readme_tmp="$(mktemp)"
cat > "$readme_tmp" <<INFO
Generated by ATLAS Kubernetes wizard $WIZARD_VERSION
GitHub source: https://github.com/$GITHUB_REPO
GitHub ref: $GITHUB_REF
Namespace: $NAMESPACE
Application: $APP_NAME
Image: $IMAGE
Public hostname: $PUBLIC_HOSTNAME
Database endpoint: $DB_HOST
Database TLS: $DB_SSL (verify=$DB_SSL_VERIFY, ca=${DB_SSL_CA:-system/default})
Kustomize entrypoint: $OUTPUT_DIR/kustomization.yaml
Node selector: $([[ "$NODE_SELECTOR_ENABLED" == yes ]] && printf '%s' "$NODE_SELECTOR" || printf 'disabled')
Wizard auto-update: $AUTO_UPDATE_WIZARD
INFO
if write_if_changed "$readme_tmp" "$OUTPUT_DIR/README.generated.txt"; then log "Generated/updated: $OUTPUT_DIR/README.generated.txt"; else log "Generated README unchanged."; fi

if [[ -z "$MANAGE_SECRETS" ]]; then yesno "Create/update Kubernetes secrets now?" y && MANAGE_SECRETS=yes || MANAGE_SECRETS=no; fi
if [[ -z "$APPLY_MANIFESTS" ]]; then yesno "Apply generated manifests to Kubernetes now?" n && APPLY_MANIFESTS=yes || APPLY_MANIFESTS=no; fi

if [[ "$MANAGE_SECRETS" == yes || "$APPLY_MANIFESTS" == yes ]]; then
  need_cmd kubectl
  ctx="$(kubectl config current-context 2>/dev/null || true)"
  [[ -n "$ctx" ]] || die "kubectl has no current context"
  (( NON_INTERACTIVE )) || yesno "Use kubectl context '$ctx'?" y || die "kubectl context not confirmed"
  kubectl create namespace "$NAMESPACE" --dry-run=client -o yaml | kubectl apply -f - >/dev/null
  log "Namespace ensured: $NAMESPACE"
fi

if [[ "$MANAGE_SECRETS" == yes ]]; then
  need_cmd base64
  need_cmd openssl
  manage_bootstrap_secret
  manage_db_admin_secret
  manage_tls_secret
fi

if [[ "$APPLY_MANIFESTS" == yes ]]; then
  kubectl apply -f "$OUTPUT_DIR/00-namespace.yaml"
  kubectl apply -k "$OUTPUT_DIR"
  log "Kubernetes manifests applied with Kustomize."
fi

if (( BOOTSTRAP_SECRET_CHANGED || TLS_SECRET_CHANGED || DB_ADMIN_SECRET_CHANGED )) && kubectl -n "$NAMESPACE" get deployment "$APP_NAME" >/dev/null 2>&1; then
  if (( BOOTSTRAP_SECRET_CHANGED && TLS_SECRET_CHANGED )); then
    log "Bootstrap configuration and TLS material changed; restarting deployment."
  elif (( DB_ADMIN_SECRET_CHANGED )); then
    log "Database schema-migration credential changed; restarting deployment so startup migrations use the current Secret."
  elif (( BOOTSTRAP_SECRET_CHANGED )); then
    log "Bootstrap configuration changed; restarting deployment so the managed atlas-install.env is refreshed."
  else
    log "TLS material changed; restarting deployment so Apache loads the new certificate."
  fi
  kubectl -n "$NAMESPACE" rollout restart deployment "$APP_NAME"
fi

if [[ "$APPLY_MANIFESTS" == yes ]] && kubectl -n "$NAMESPACE" get deployment "$APP_NAME" >/dev/null 2>&1; then
  log "Waiting for deployment rollout to complete."
  if kubectl -n "$NAMESPACE" rollout status deployment "$APP_NAME" --timeout=300s; then
    log "Deployment rollout completed successfully. Persistent schema-migration Secret retained for future restore/startup migrations."
  else
    log "WARNING: rollout did not become ready; database schema-migration Secret is retained for retry/diagnostics."
  fi
fi

save_state
log "Done."
