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
PY
}

crl_loop() {
  while sleep "${ATLAS_CRL_REFRESH_SECONDS:-21600}"; do
    log "Refreshing IGTF CRLs."
    if /usr/local/sbin/atlas-update-igtf; then
      /usr/sbin/httpd -k graceful || true
    fi
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
    /usr/local/sbin/atlas-update-igtf
    render_httpd
    /usr/sbin/httpd -t
    log "Starting PHP-FPM."
    /usr/sbin/php-fpm --nodaemonize --fpm-config /etc/php-fpm.conf &
    fpm_pid=$!
    trap 'kill "$fpm_pid" 2>/dev/null || true' EXIT TERM INT
    crl_loop &
    log "Starting Apache HTTPS on port $HTTPS_PORT."
    exec /usr/sbin/httpd -DFOREGROUND
    ;;
  *)
    exec "$@"
    ;;
esac
