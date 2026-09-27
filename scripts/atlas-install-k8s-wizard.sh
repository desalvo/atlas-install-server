#!/usr/bin/env bash
set -euo pipefail

WIZARD_VERSION="3.0.0"
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
DEFAULT_VO="ATLAS"
DEFAULT_EMAIL="no-reply@localhost"
DEFAULT_INFOSYS="lcg-bdii.cern.ch"
DEFAULT_ACTIVITY_PERIOD="3 DAY"
DEFAULT_DEBUG="0"

STATE_ROOT="${XDG_CONFIG_HOME:-$HOME/.config}/atlas-install-server"
STATE_FILE="$STATE_ROOT/k8s-wizard.env"
CACHE_ROOT="$STATE_ROOT/cache"
OUTPUT_DIR="${PWD}/atlas-install-kubernetes"
CLI_OUTPUT_DIR=""
APPLY_MANIFESTS=""
MANAGE_SECRETS=""
NON_INTERACTIVE=0

usage() {
  cat <<USAGE
ATLAS Installation Server Kubernetes wizard $WIZARD_VERSION

Usage: $(basename "$0") [options]

Options:
  --output-dir DIR       Directory where rendered manifests are written
  --generate-only        Render manifests only; do not touch the cluster
  --apply                Render and apply manifests, and manage secrets
  --non-interactive      Use stored/default values; requires existing secrets or env vars for passwords/cert paths
  --reset-state          Forget stored non-secret wizard choices
  -h, --help             Show this help

Optional environment variables for automation:
  GITHUB_TOKEN           GitHub token for private repositories
  ATLAS_DB_RW_PASSWORD   Bootstrap RW database password
  ATLAS_DB_RO_PASSWORD   Bootstrap RO database password
  ATLAS_DB_BROKER_PASSWORD  Bootstrap broker database password
  ATLAS_TLS_CERT_FILE    Host certificate/full-chain PEM path
  ATLAS_TLS_KEY_FILE     Host private-key PEM path
USAGE
}

log() { printf '%s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "required command not found: $1"
}

prompt() {
  local label="$1" default="${2-}" answer
  if (( NON_INTERACTIVE )); then
    printf '%s' "$default"
    return 0
  fi
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
  if (( NON_INTERACTIVE )); then
    printf ''
    return 0
  fi
  read -r -s -p "$label: " answer
  printf '\n' >&2
  printf '%s' "$answer"
}

yesno() {
  local label="$1" default="${2:-y}" answer suffix
  if (( NON_INTERACTIVE )); then
    [[ "$default" =~ ^[Yy]$ ]]
    return
  fi
  if [[ "$default" =~ ^[Yy]$ ]]; then suffix="Y/n"; else suffix="y/N"; fi
  read -r -p "$label [$suffix]: " answer
  answer="${answer:-$default}"
  [[ "$answer" =~ ^[Yy]([Ee][Ss])?$ ]]
}

shell_quote() { printf '%q' "$1"; }

save_state() {
  mkdir -p "$STATE_ROOT"
  chmod 700 "$STATE_ROOT"
  umask 077
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
    printf 'DB_RW_HOST=%q\n' "$DB_RW_HOST"
    printf 'DB_RW_USER=%q\n' "$DB_RW_USER"
    printf 'DB_RO_HOST=%q\n' "$DB_RO_HOST"
    printf 'DB_RO_USER=%q\n' "$DB_RO_USER"
    printf 'DB_BROKER_HOST=%q\n' "$DB_BROKER_HOST"
    printf 'DB_BROKER_USER=%q\n' "$DB_BROKER_USER"
    printf 'ATLAS_VO_VALUE=%q\n' "$ATLAS_VO_VALUE"
    printf 'ATLAS_EMAIL_VALUE=%q\n' "$ATLAS_EMAIL_VALUE"
    printf 'ATLAS_CONTACTS_VALUE=%q\n' "$ATLAS_CONTACTS_VALUE"
    printf 'ATLAS_DEFAULT_INFOSYS_VALUE=%q\n' "$ATLAS_DEFAULT_INFOSYS_VALUE"
    printf 'ATLAS_ACTIVITY_PERIOD_VALUE=%q\n' "$ATLAS_ACTIVITY_PERIOD_VALUE"
    printf 'ATLAS_DEBUG_VALUE=%q\n' "$ATLAS_DEBUG_VALUE"
    printf 'TLS_CERT_PATH=%q\n' "$TLS_CERT_PATH"
    printf 'TLS_KEY_PATH=%q\n' "$TLS_KEY_PATH"
    printf 'OUTPUT_DIR=%q\n' "$OUTPUT_DIR"
  } > "$STATE_FILE"
  chmod 600 "$STATE_FILE"
}

load_state() {
  if [[ -f "$STATE_FILE" ]]; then
    # File is written exclusively by this script with shell-escaped values.
    # shellcheck disable=SC1090
    source "$STATE_FILE"
    return 0
  fi
  return 1
}

validate_dns_label() {
  [[ "$1" =~ ^[a-z0-9]([-a-z0-9]*[a-z0-9])?$ ]] || die "invalid Kubernetes name: $1"
}

validate_repo() {
  [[ "$1" =~ ^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$ ]] || die "GitHub repository must be owner/repository"
}

fetch_template() {
  local remote_path="$1" local_path="$2" url tmp auth=()
  url="https://raw.githubusercontent.com/${GITHUB_REPO}/${GITHUB_REF}/${remote_path}"
  tmp="${local_path}.tmp.$$"
  if [[ -n "${GITHUB_TOKEN:-}" ]]; then
    auth=(-H "Authorization: Bearer ${GITHUB_TOKEN}")
  fi
  mkdir -p "$(dirname "$local_path")"
  if ! curl --fail --location --silent --show-error "${auth[@]}" "$url" -o "$tmp"; then
    rm -f "$tmp"
    if [[ -s "$local_path" ]]; then
      log "WARNING: cannot refresh $remote_path from GitHub; using cached template."
      return 0
    fi
    die "cannot download $remote_path from $GITHUB_REPO ref $GITHUB_REF and no cached template is available"
  fi
  if [[ ! -s "$tmp" ]]; then
    rm -f "$tmp"
    die "downloaded template is empty: $remote_path"
  fi
  if [[ -f "$local_path" ]] && cmp -s "$tmp" "$local_path"; then
    rm -f "$tmp"
    log "Template unchanged: $remote_path"
  else
    mv -f "$tmp" "$local_path"
    log "Template downloaded/updated: $remote_path"
  fi
}

render_template() {
  local src="$1" dst="$2" content storage_block
  content="$(cat "$src")"
  if [[ -n "$STORAGE_CLASS" ]]; then
    storage_block="  storageClassName: ${STORAGE_CLASS}"$'\n'
  else
    storage_block=""
  fi
  content="${content//\{\{NAMESPACE\}\}/$NAMESPACE}"
  content="${content//\{\{APP_NAME\}\}/$APP_NAME}"
  content="${content//\{\{IMAGE\}\}/$IMAGE}"
  content="${content//\{\{PUBLIC_HOSTNAME\}\}/$PUBLIC_HOSTNAME}"
  content="${content//\{\{INGRESS_CLASS\}\}/$INGRESS_CLASS}"
  content="${content//\{\{STORAGE_SIZE\}\}/$STORAGE_SIZE}"
  content="${content//\{\{STORAGE_CLASS_BLOCK\}\}/$storage_block}"
  content="${content//\{\{PVC_ACCESS_MODE\}\}/$PVC_ACCESS_MODE}"
  content="${content//\{\{IMAGE_PULL_POLICY\}\}/$IMAGE_PULL_POLICY}"
  content="${content//\{\{CPU_REQUEST\}\}/$CPU_REQUEST}"
  content="${content//\{\{MEMORY_REQUEST\}\}/$MEMORY_REQUEST}"
  content="${content//\{\{CPU_LIMIT\}\}/$CPU_LIMIT}"
  content="${content//\{\{MEMORY_LIMIT\}\}/$MEMORY_LIMIT}"
  content="${content//\{\{REPLICAS\}\}/$REPLICAS}"
  content="${content//\{\{PLOTS_SCHEDULE\}\}/$PLOTS_SCHEDULE}"
  content="${content//\{\{CLEANUP_SCHEDULE\}\}/$CLEANUP_SCHEDULE}"
  if grep -q '{{[A-Z0-9_]*}}' <<<"$content"; then
    die "unresolved placeholder while rendering $src"
  fi
  mkdir -p "$(dirname "$dst")"
  printf '%s\n' "$content" > "$dst"
}

get_existing_bootstrap_env() {
  kubectl -n "$NAMESPACE" get secret "${APP_NAME}-bootstrap" \
    -o jsonpath='{.data.atlas-install\.env}' 2>/dev/null | base64 -d 2>/dev/null || true
}

env_raw_value() {
  local data="$1" key="$2"
  awk -F= -v k="$key" '$1==k {print substr($0,index($0,"=")+1); exit}' <<<"$data"
}

env_quote() {
  local value="$1"
  [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]] || die "configuration values may not contain newlines"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '"%s"' "$value"
}

require_or_keep_password_raw() {
  local env_name="$1" label="$2" existing_raw="$3" value
  value="${!env_name:-}"
  if [[ -n "$value" ]]; then env_quote "$value"; return; fi
  if (( NON_INTERACTIVE )); then
    [[ -n "$existing_raw" ]] || die "$env_name must be set in non-interactive mode when no existing secret value exists"
    printf '%s' "$existing_raw"
    return
  fi
  if [[ -n "$existing_raw" ]]; then
    value="$(prompt_secret "$label (leave blank to keep current)")"
    if [[ -z "$value" ]]; then printf '%s' "$existing_raw"; else env_quote "$value"; fi
  else
    while [[ -z "$value" ]]; do value="$(prompt_secret "$label")"; done
    env_quote "$value"
  fi
}

apply_bootstrap_secret() {
  local existing rw_old ro_old broker_old rw_pw ro_pw broker_pw tmp
  existing="$(get_existing_bootstrap_env)"
  rw_old="$(env_raw_value "$existing" ATLAS_DB_RW_PASSWORD)"
  ro_old="$(env_raw_value "$existing" ATLAS_DB_RO_PASSWORD)"
  broker_old="$(env_raw_value "$existing" ATLAS_DB_BROKER_PASSWORD)"
  rw_pw="$(require_or_keep_password_raw ATLAS_DB_RW_PASSWORD 'RW database password' "$rw_old")"
  ro_pw="$(require_or_keep_password_raw ATLAS_DB_RO_PASSWORD 'RO database password' "$ro_old")"
  broker_pw="$(require_or_keep_password_raw ATLAS_DB_BROKER_PASSWORD 'Broker database password' "$broker_old")"
  tmp="$(mktemp)"
  chmod 600 "$tmp"
  {
    printf 'ATLAS_PUBLIC_HOSTNAME=%s\n' "$(env_quote "$PUBLIC_HOSTNAME")"
    printf 'ATLAS_DB_NAME=%s\n' "$(env_quote "$DB_NAME")"
    printf 'ATLAS_DB_RW_HOST=%s\n' "$(env_quote "$DB_RW_HOST")"
    printf 'ATLAS_DB_RW_USER=%s\n' "$(env_quote "$DB_RW_USER")"
    printf 'ATLAS_DB_RW_PASSWORD=%s\n' "$rw_pw"
    printf 'ATLAS_DB_RO_HOST=%s\n' "$(env_quote "$DB_RO_HOST")"
    printf 'ATLAS_DB_RO_USER=%s\n' "$(env_quote "$DB_RO_USER")"
    printf 'ATLAS_DB_RO_PASSWORD=%s\n' "$ro_pw"
    printf 'ATLAS_DB_BROKER_HOST=%s\n' "$(env_quote "$DB_BROKER_HOST")"
    printf 'ATLAS_DB_BROKER_USER=%s\n' "$(env_quote "$DB_BROKER_USER")"
    printf 'ATLAS_DB_BROKER_PASSWORD=%s\n' "$broker_pw"
    printf 'ATLAS_VO=%s\n' "$(env_quote "$ATLAS_VO_VALUE")"
    printf 'ATLAS_EMAIL=%s\n' "$(env_quote "$ATLAS_EMAIL_VALUE")"
    printf 'ATLAS_CONTACTS=%s\n' "$(env_quote "$ATLAS_CONTACTS_VALUE")"
    printf 'ATLAS_DEFAULT_INFOSYS=%s\n' "$(env_quote "$ATLAS_DEFAULT_INFOSYS_VALUE")"
    printf 'ATLAS_ACTIVITY_PERIOD=%s\n' "$(env_quote "$ATLAS_ACTIVITY_PERIOD_VALUE")"
    printf 'ATLAS_DEBUG=%s\n' "$(env_quote "$ATLAS_DEBUG_VALUE")"
    printf 'ATLAS_UPLOAD_PATH=%s\n' '"/var/lib/atlas-install/log"'
    printf 'ATLAS_ARCHIVE_PATH=%s\n' '"/var/lib/atlas-install/logbackup"'
    printf 'ATLAS_CACHE_PATH=%s\n' '"/var/cache/atlas-install"'
    printf 'ATLAS_KML_CACHE=%s\n' '"/var/cache/atlas-install/install.kml"'
  } > "$tmp"
  kubectl -n "$NAMESPACE" create secret generic "${APP_NAME}-bootstrap" \
    --from-file=atlas-install.env="$tmp" --dry-run=client -o yaml | kubectl apply -f - >/dev/null
  rm -f "$tmp"
  log "Secret applied: ${NAMESPACE}/${APP_NAME}-bootstrap"
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
    openssl x509 -in "$cert" -noout -checkhost "$PUBLIC_HOSTNAME" >/dev/null 2>&1 || \
      die "certificate does not match host $PUBLIC_HOSTNAME"
  fi
}

apply_tls_secret() {
  local cert="${ATLAS_TLS_CERT_FILE:-$TLS_CERT_PATH}" key="${ATLAS_TLS_KEY_FILE:-$TLS_KEY_PATH}"
  if [[ -z "$cert" || ! -r "$cert" ]]; then
    if kubectl -n "$NAMESPACE" get secret "${APP_NAME}-tls" >/dev/null 2>&1; then
      if (( NON_INTERACTIVE )) || yesno "TLS files unavailable. Keep existing ${APP_NAME}-tls secret?" y; then
        log "TLS secret retained unchanged."
        return
      fi
    fi
    cert="$(prompt 'Host certificate/full-chain PEM path' "$cert")"
  fi
  if [[ -z "$key" || ! -r "$key" ]]; then
    key="$(prompt 'Host private-key PEM path' "$key")"
  fi
  validate_tls_pair "$cert" "$key"
  TLS_CERT_PATH="$cert"
  TLS_KEY_PATH="$key"
  kubectl -n "$NAMESPACE" create secret tls "${APP_NAME}-tls" \
    --cert="$cert" --key="$key" --dry-run=client -o yaml | kubectl apply -f - >/dev/null
  log "Secret applied: ${NAMESPACE}/${APP_NAME}-tls"
}

while (($#)); do
  case "$1" in
    --output-dir) [[ $# -ge 2 ]] || die "--output-dir requires a value"; CLI_OUTPUT_DIR="$2"; shift 2 ;;
    --generate-only) APPLY_MANIFESTS="no"; MANAGE_SECRETS="no"; shift ;;
    --apply) APPLY_MANIFESTS="yes"; MANAGE_SECRETS="yes"; shift ;;
    --non-interactive) NON_INTERACTIVE=1; shift ;;
    --reset-state) rm -f "$STATE_FILE"; shift ;;
    -h|--help) usage; exit 0 ;;
    *) die "unknown option: $1" ;;
  esac
done

need_cmd curl
need_cmd sha256sum

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
DB_RW_HOST="${DB_RW_HOST:-$DEFAULT_DB_HOST}"
DB_RW_USER="${DB_RW_USER:-$DEFAULT_DB_RW_USER}"
DB_RO_HOST="${DB_RO_HOST:-$DEFAULT_DB_HOST}"
DB_RO_USER="${DB_RO_USER:-$DEFAULT_DB_RO_USER}"
DB_BROKER_HOST="${DB_BROKER_HOST:-$DEFAULT_DB_HOST}"
DB_BROKER_USER="${DB_BROKER_USER:-$DEFAULT_DB_BROKER_USER}"
ATLAS_VO_VALUE="${ATLAS_VO_VALUE:-$DEFAULT_VO}"
ATLAS_EMAIL_VALUE="${ATLAS_EMAIL_VALUE:-$DEFAULT_EMAIL}"
ATLAS_CONTACTS_VALUE="${ATLAS_CONTACTS_VALUE:-}"
ATLAS_DEFAULT_INFOSYS_VALUE="${ATLAS_DEFAULT_INFOSYS_VALUE:-$DEFAULT_INFOSYS}"
ATLAS_ACTIVITY_PERIOD_VALUE="${ATLAS_ACTIVITY_PERIOD_VALUE:-$DEFAULT_ACTIVITY_PERIOD}"
ATLAS_DEBUG_VALUE="${ATLAS_DEBUG_VALUE:-$DEFAULT_DEBUG}"
TLS_CERT_PATH="${TLS_CERT_PATH:-}"
TLS_KEY_PATH="${TLS_KEY_PATH:-}"
OUTPUT_DIR="${CLI_OUTPUT_DIR:-${OUTPUT_DIR:-${PWD}/atlas-install-kubernetes}}"

log "ATLAS Installation Server Kubernetes wizard $WIZARD_VERSION"
log "State: $STATE_FILE"

if (( ! had_state )); then
  GITHUB_REPO="$(prompt 'GitHub repository (owner/repository)' "$GITHUB_REPO")"
  validate_repo "$GITHUB_REPO"
  if (( ! NON_INTERACTIVE )); then
    yesno "Use GitHub repository '$GITHUB_REPO'?" y || die "repository not confirmed"
  fi
else
  GITHUB_REPO="$(prompt 'GitHub repository' "$GITHUB_REPO")"
  validate_repo "$GITHUB_REPO"
fi
GITHUB_REF="$(prompt 'GitHub ref/branch/tag for Kubernetes templates' "$GITHUB_REF")"

NAMESPACE="$(prompt 'Kubernetes namespace' "$NAMESPACE")"
APP_NAME="$(prompt 'Application resource prefix' "$APP_NAME")"
validate_dns_label "$NAMESPACE"
validate_dns_label "$APP_NAME"
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
OUTPUT_DIR="$(prompt 'Generated manifest directory' "$OUTPUT_DIR")"

if yesno "Generate maintenance CronJobs?" "$([[ "$ENABLE_MAINTENANCE" == yes ]] && echo y || echo n)"; then
  ENABLE_MAINTENANCE=yes
  PLOTS_SCHEDULE="$(prompt 'Plots CronJob schedule' "$PLOTS_SCHEDULE")"
  CLEANUP_SCHEDULE="$(prompt 'Log-cleanup CronJob schedule' "$CLEANUP_SCHEDULE")"
else
  ENABLE_MAINTENANCE=no
fi

DB_NAME="$(prompt 'Database name' "$DB_NAME")"
DB_RW_HOST="$(prompt 'RW database host' "$DB_RW_HOST")"
DB_RW_USER="$(prompt 'RW database user' "$DB_RW_USER")"
DB_RO_HOST="$(prompt 'RO database host' "$DB_RO_HOST")"
DB_RO_USER="$(prompt 'RO database user' "$DB_RO_USER")"
DB_BROKER_HOST="$(prompt 'Broker database host' "$DB_BROKER_HOST")"
DB_BROKER_USER="$(prompt 'Broker database user' "$DB_BROKER_USER")"
ATLAS_VO_VALUE="$(prompt 'ATLAS VO' "$ATLAS_VO_VALUE")"
ATLAS_EMAIL_VALUE="$(prompt 'Notification email' "$ATLAS_EMAIL_VALUE")"
ATLAS_CONTACTS_VALUE="$(prompt 'Contacts' "$ATLAS_CONTACTS_VALUE")"
ATLAS_DEFAULT_INFOSYS_VALUE="$(prompt 'Default infosys' "$ATLAS_DEFAULT_INFOSYS_VALUE")"
ATLAS_ACTIVITY_PERIOD_VALUE="$(prompt 'Activity period' "$ATLAS_ACTIVITY_PERIOD_VALUE")"
ATLAS_DEBUG_VALUE="$(prompt 'Debug flag' "$ATLAS_DEBUG_VALUE")"

cache_key="${GITHUB_REPO//\//_}/${GITHUB_REF//\//_}"
cache_dir="$CACHE_ROOT/$cache_key"
main_tpl="$cache_dir/atlas-install-container.yaml.tpl"
maint_tpl="$cache_dir/maintenance-cronjobs.yaml.tpl"
fetch_template "kubernetes/templates/atlas-install-container.yaml.tpl" "$main_tpl"
fetch_template "kubernetes/templates/maintenance-cronjobs.yaml.tpl" "$maint_tpl"

mkdir -p "$OUTPUT_DIR"
main_out="$OUTPUT_DIR/atlas-install-container.yaml"
maint_out="$OUTPUT_DIR/maintenance-cronjobs.yaml"
render_template "$main_tpl" "$main_out"
if [[ "$ENABLE_MAINTENANCE" == yes ]]; then
  render_template "$maint_tpl" "$maint_out"
else
  rm -f "$maint_out"
fi

cat > "$OUTPUT_DIR/README.generated.txt" <<INFO
Generated by ATLAS Kubernetes wizard $WIZARD_VERSION
GitHub source: https://github.com/$GITHUB_REPO
GitHub ref: $GITHUB_REF
Namespace: $NAMESPACE
Application: $APP_NAME
Image: $IMAGE
Public hostname: $PUBLIC_HOSTNAME
Generated: $(date -u +%Y-%m-%dT%H:%M:%SZ)
INFO

log "Generated manifest: $main_out"
[[ "$ENABLE_MAINTENANCE" == yes ]] && log "Generated manifest: $maint_out"

if [[ -z "$MANAGE_SECRETS" ]]; then
  if yesno "Create/update Kubernetes secrets now?" y; then MANAGE_SECRETS=yes; else MANAGE_SECRETS=no; fi
fi
if [[ -z "$APPLY_MANIFESTS" ]]; then
  if yesno "Apply generated manifests to Kubernetes now?" n; then APPLY_MANIFESTS=yes; else APPLY_MANIFESTS=no; fi
fi

if [[ "$MANAGE_SECRETS" == yes || "$APPLY_MANIFESTS" == yes ]]; then
  need_cmd kubectl
  ctx="$(kubectl config current-context 2>/dev/null || true)"
  [[ -n "$ctx" ]] || die "kubectl has no current context"
  if (( ! NON_INTERACTIVE )); then
    yesno "Use kubectl context '$ctx'?" y || die "kubectl context not confirmed"
  fi
  kubectl create namespace "$NAMESPACE" --dry-run=client -o yaml | kubectl apply -f - >/dev/null
  log "Namespace ensured: $NAMESPACE"
fi

if [[ "$MANAGE_SECRETS" == yes ]]; then
  need_cmd base64
  need_cmd openssl
  apply_bootstrap_secret
  apply_tls_secret
fi

if [[ "$APPLY_MANIFESTS" == yes ]]; then
  kubectl apply -f "$main_out"
  if [[ "$ENABLE_MAINTENANCE" == yes ]]; then kubectl apply -f "$maint_out"; fi
  log "Kubernetes manifests applied."
fi

save_state
log "Saved non-secret choices: $STATE_FILE"
log "Done."
