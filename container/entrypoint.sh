#!/usr/bin/env bash
set -euo pipefail

ENV_FILE=${ATLAS_ENV_FILE:-/var/lib/atlas-install/config/atlas-install.env}
BOOTSTRAP=${ATLAS_BOOTSTRAP_ENV:-/run/secrets/bootstrap/atlas-install.env}
TLS_CERT=${ATLAS_TLS_CERT_FILE:-/run/secrets/tls/tls.crt}
TLS_KEY=${ATLAS_TLS_KEY_FILE:-/run/secrets/tls/tls.key}
HTTPS_PORT=${ATLAS_HTTPS_PORT:-8443}
CONFIG_DIR=$(dirname "$ENV_FILE")

log(){ printf '[atlas-container] %s\n' "$*" >&2; }
die(){ log "ERROR: $*"; exit 1; }

read_env_value() {
  local key=$1 default=${2:-}
  python3 - "$ENV_FILE" "$key" "$default" <<'PY'
from pathlib import Path
import sys,re
path,key,default=sys.argv[1:]
try: lines=Path(path).read_text().splitlines()
except OSError:
    print(default); raise SystemExit
for line in lines:
    line=line.strip()
    if not line or line.startswith('#') or '=' not in line: continue
    k,v=line.split('=',1)
    if k.strip()!=key: continue
    v=v.strip()
    if len(v)>=2 and v[0]=='"' and v[-1]=='"':
        v=v[1:-1]
        v=re.sub(r'\\([\\"$`])',r'\1',v)
    print(v); raise SystemExit
print(default)
PY
}

init_config() {
  install -d -o root -g atlas-install -m 2770 "$CONFIG_DIR"
  if [[ ! -s "$ENV_FILE" ]]; then
    if [[ -r "$BOOTSTRAP" ]]; then
      log "Initializing managed configuration from Kubernetes bootstrap secret."
      install -o root -g atlas-install -m 0660 "$BOOTSTRAP" "$ENV_FILE"
    else
      log "No bootstrap secret found; installing non-secret defaults. Database credentials must be configured before readiness succeeds."
      cat >"$ENV_FILE" <<'CFG'
ATLAS_PUBLIC_HOSTNAME="atlas-install-el10.apps.desalvo.eu"
ATLAS_DB_NAME="atlas_install_panda"
ATLAS_DB_RW_HOST="192.168.1.145"
ATLAS_DB_RW_USER="atlas_rw"
ATLAS_DB_RW_PASSWORD=""
ATLAS_DB_RO_HOST="192.168.1.145"
ATLAS_DB_RO_USER="atlas_ro"
ATLAS_DB_RO_PASSWORD=""
ATLAS_DB_BROKER_HOST="192.168.1.145"
ATLAS_DB_BROKER_USER="atlas_rw"
ATLAS_DB_BROKER_PASSWORD=""
ATLAS_VO="ATLAS"
ATLAS_DEBUG="0"
ATLAS_UPLOAD_PATH="/var/lib/atlas-install/log"
ATLAS_ARCHIVE_PATH="/var/lib/atlas-install/logbackup"
ATLAS_CACHE_PATH="/var/cache/atlas-install"
ATLAS_KML_CACHE="/var/cache/atlas-install/install.kml"
CFG
      chown root:atlas-install "$ENV_FILE"
      chmod 0660 "$ENV_FILE"
    fi
  fi
  chown root:atlas-install "$ENV_FILE"
  chmod 0660 "$ENV_FILE"
}

validate_tls() {
  [[ -r "$TLS_CERT" ]] || die "TLS certificate not readable: $TLS_CERT"
  [[ -r "$TLS_KEY" ]] || die "TLS private key not readable: $TLS_KEY"
  openssl x509 -in "$TLS_CERT" -noout >/dev/null 2>&1 || die "Invalid TLS certificate"
  openssl pkey -in "$TLS_KEY" -noout >/dev/null 2>&1 || die "Invalid TLS private key"
  local c k host
  c=$(openssl x509 -in "$TLS_CERT" -pubkey -noout | openssl pkey -pubin -outform DER 2>/dev/null | sha256sum | awk '{print $1}')
  k=$(openssl pkey -in "$TLS_KEY" -pubout -outform DER 2>/dev/null | sha256sum | awk '{print $1}')
  [[ "$c" == "$k" ]] || die "TLS certificate and private key do not match"
  host=$(read_env_value ATLAS_PUBLIC_HOSTNAME atlas-install-el10.apps.desalvo.eu)
  openssl x509 -in "$TLS_CERT" -noout -checkhost "$host" >/dev/null 2>&1 || die "TLS certificate does not cover $host"
}

disable_default_http_listener() {
  local conf=/etc/httpd/conf/httpd.conf
  if [[ -f "$conf" ]] && grep -Eq '^[[:space:]]*Listen[[:space:]]+80([[:space:]]*)$' "$conf"; then
    log "Disabling Rocky Linux default HTTP listener on port 80; container HTTPS listener is ${HTTPS_PORT}."
    sed -ri 's|^[[:space:]]*Listen[[:space:]]+80([[:space:]]*)$|# disabled in container: Listen 80|' "$conf"
  fi
  if grep -Eq '^[[:space:]]*Listen[[:space:]]+80([[:space:]]*)$' "$conf" 2>/dev/null; then
    die "Apache default Listen 80 is still active; refusing to start with privileged HTTP listener"
  fi
}

render_httpd() {
  local host
  host=$(read_env_value ATLAS_PUBLIC_HOSTNAME atlas-install-el10.apps.desalvo.eu)
  python3 - /opt/atlas/httpd-container.conf.template /etc/httpd/conf.d/25-atlas-install-container.conf \
    "$host" "$TLS_CERT" "$TLS_KEY" "$HTTPS_PORT" <<'PY'
from pathlib import Path
import sys
src,dst,host,cert,key,port=sys.argv[1:]
s=Path(src).read_text()
for k,v in {'@PUBLIC_HOSTNAME@':host,'@HOST_CERT@':cert,'@HOST_KEY@':key,'@HTTPS_PORT@':port}.items():
    s=s.replace(k,v)
Path(dst).write_text(s)
Path(dst).chmod(0o644)
PY
}

ensure_httpd_container_config() {
  local generated=/etc/httpd/conf.d/25-atlas-install-container.conf
  local main=/etc/httpd/conf/httpd.conf
  local dump

  [[ -s "$generated" ]] || die "generated Apache container config is missing or empty: $generated"
  grep -Eq "^[[:space:]]*Listen[[:space:]]+${HTTPS_PORT}([[:space:]]+https)?[[:space:]]*$" "$generated" \
    || die "generated Apache config does not contain Listen ${HTTPS_PORT}"
  grep -Eq "<VirtualHost[^>]*:${HTTPS_PORT}>" "$generated" \
    || die "generated Apache config does not contain VirtualHost on ${HTTPS_PORT}"
  if grep -Eq '^[[:space:]]*SSLCARevocationCheck[[:space:]]+' "$generated"; then
    grep -Eq '^[[:space:]]*SSLCARevocation(Path|File)[[:space:]]+' "$generated" \
      || die "SSLCARevocationCheck is enabled but neither SSLCARevocationPath nor SSLCARevocationFile is configured"
  fi

  dump=$(/usr/sbin/httpd -t -D DUMP_VHOSTS 2>&1 || true)
  if ! printf '%s\n' "$dump" | grep -Eq "(:|\*)${HTTPS_PORT}([^0-9]|$)"; then
    log "Apache is not loading $generated through its normal includes; adding an explicit Include."
    if ! grep -Fq "Include \"$generated\"" "$main"; then
      printf '\n# ATLAS container-generated HTTPS configuration\nInclude \"%s\"\n' "$generated" >> "$main"
    fi
  fi

  dump=$(/usr/sbin/httpd -t -D DUMP_VHOSTS 2>&1) || {
    printf '%s\n' "$dump" >&2
    die "Apache virtual-host validation failed"
  }
  printf '%s\n' "$dump" | grep -Eq "(:|\*)${HTTPS_PORT}([^0-9]|$)" \
    || { printf '%s\n' "$dump" >&2; die "Apache still has no VirtualHost on ${HTTPS_PORT}"; }

  log "Apache listener/VHost validation passed for HTTPS port ${HTTPS_PORT}."
}

igtf_refresh_once() {
  log "Refreshing IGTF trust anchors and CRLs in background."
  if /usr/local/sbin/atlas-update-igtf; then
    log "IGTF/CRL background refresh completed; reloading Apache."
    /usr/sbin/httpd -k graceful || true
  else
    log "WARNING: IGTF/CRL background refresh failed; keeping the current trust store."
  fi
}

igtf_refresh_loop() {
  local interval="${ATLAS_IGTF_REFRESH_SECONDS:-21600}"
  [[ "$interval" =~ ^[0-9]+$ ]] && (( interval > 0 )) || die "ATLAS_IGTF_REFRESH_SECONDS must be a positive integer"
  igtf_refresh_once
  while sleep "$interval"; do
    igtf_refresh_once
  done
}

case "${1:-serve}" in
  update-igtf)
    exec /usr/local/sbin/atlas-update-igtf
    ;;
  maintenance)
    shift
    exec /usr/local/sbin/atlas-maintenance "$@"
    ;;
  serve)
    init_config
    validate_tls
    install -d -o root -g atlas-install -m 2770 /var/lib/atlas-install/log /var/lib/atlas-install/logbackup /var/cache/atlas-install
    ATLAS_IGTF_SKIP_CRL=1 /usr/local/sbin/atlas-update-igtf
    disable_default_http_listener
    render_httpd
    ensure_httpd_container_config
    /usr/sbin/httpd -t
    log "Starting PHP-FPM."
    /usr/sbin/php-fpm --nodaemonize --fpm-config /etc/php-fpm.conf &
    fpm_pid=$!
    trap 'kill "$fpm_pid" 2>/dev/null || true' EXIT TERM INT
    igtf_refresh_loop &
    log "Starting Apache HTTPS on port $HTTPS_PORT."
    exec /usr/sbin/httpd -DFOREGROUND
    ;;
  *)
    exec "$@"
    ;;
esac
