#!/usr/bin/bash
set -euo pipefail
umask 027

APP_NAME="atlas-install"
APP_VERSION="3.0.0"
SELF_DIR="$(cd -P "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PERSISTENT_PKG_ROOT="/usr/local/share/atlas-install-installer"
if [[ -d "$SELF_DIR/var/www/html/atlas_install-3.0.0" ]]; then
  PKG_ROOT="$SELF_DIR"
elif [[ -d "$PERSISTENT_PKG_ROOT/var/www/html/atlas_install-3.0.0" ]]; then
  PKG_ROOT="$PERSISTENT_PKG_ROOT"
else
  echo "ATLAS installer payload not found next to the script or in $PERSISTENT_PKG_ROOT" >&2
  exit 1
fi
APP_SRC="$PKG_ROOT/var/www/html/atlas_install-3.0.0"
ENV_EXAMPLE="$PKG_ROOT/etc/atlas-install/atlas-install.env.example"
ENV_DIR="/etc/atlas-install"
ENV_FILE="$ENV_DIR/atlas-install.env"
APP_DST="/var/www/html/atlas_install-3.0.0"
APP_LINK="/var/www/html/atlas_install"
HTTPD_CONF="/etc/httpd/conf.d/25-atlas-install.conf"
HTTPD_HTTP_CONF="/etc/httpd/conf.d/25-atlas-install-nonssl.conf"
CERT_DST="/etc/pki/tls/certs/atlas-install.crt"
KEY_DST="/etc/pki/tls/private/atlas-install.key"
CA_DIR="/etc/grid-security/certificates"
STATE_DIR="/var/lib/atlas-install"
IGTF_STAMP="$STATE_DIR/igtf-last-update"
DEFAULT_DB_HOST="192.168.1.145"
DEFAULT_DB_NAME="atlas_install_panda"
DEFAULT_DB_RW_USER="atlas_rw"
DEFAULT_DB_RO_USER="atlas_ro"
DEFAULT_DB_BROKER_USER="atlas_rw"
DEFAULT_PUBLIC_HOSTNAME="atlas-install-el10.apps.desalvo.eu"
DEFAULT_GRANT_HOST="192.168.1.144"

RECONFIGURE=0
CERT_ONLY=0
UPDATE_IGTF=0
ASSUME_YES=0

usage() {
  cat <<USAGE
Usage: sudo ./install-atlas-rhel10.sh [options]

Options:
  --reconfigure   Reconfigure DB, DB users/passwords, host certificate paths,
                  and public hostname. Existing values are proposed as defaults.
  --cert-only     Ask only for host certificate/key paths and update them if changed.
  --update-igtf   Force refresh of IGTF trust anchors and CRLs.
  -y, --yes       Do not ask for final confirmation.
  -h, --help      Show this help.

Without options:
  * first run: interactive configuration with project defaults;
  * later runs: show current general configuration, then update the application
    and dependencies without asking configuration questions.
USAGE
}

while (($#)); do
  case "$1" in
    --reconfigure) RECONFIGURE=1 ;;
    --cert-only) CERT_ONLY=1 ;;
    --update-igtf) UPDATE_IGTF=1 ;;
    -y|--yes) ASSUME_YES=1 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
  esac
  shift
done

if (( RECONFIGURE && CERT_ONLY )); then
  echo "--reconfigure and --cert-only cannot be used together." >&2
  exit 2
fi

if [[ ${EUID:-$(id -u)} -ne 0 ]]; then
  echo "This installer must run as root." >&2
  exit 1
fi

log() { printf '[atlas-install] %s\n' "$*"; }
warn() { printf '[atlas-install] WARNING: %s\n' "$*" >&2; }
die() { printf '[atlas-install] ERROR: %s\n' "$*" >&2; exit 1; }

require_rhel10_family() {
  [[ -r /etc/os-release ]] || return 0
  # shellcheck disable=SC1091
  . /etc/os-release
  local major="${VERSION_ID%%.*}"
  if [[ "$major" != "10" ]]; then
    warn "This package targets RHEL/EL 10; detected ${PRETTY_NAME:-unknown}."
  fi
}

install_dependencies() {
  local pkgs=(
    httpd mod_ssl
    php php-cli php-fpm php-mysqlnd php-gd php-mbstring php-ldap php-xml
    rsync curl openssl tar ca-certificates
    policycoreutils-python-utils mariadb python3
  )
  local missing=() p
  for p in "${pkgs[@]}"; do
    rpm -q "$p" >/dev/null 2>&1 || missing+=("$p")
  done
  if ((${#missing[@]})); then
    log "Installing missing dependencies: ${missing[*]}"
    dnf -y install "${missing[@]}"
  else
    log "Base dependencies already installed."
  fi

  if ! command -v fetch-crl >/dev/null 2>&1; then
    if ! dnf -q list --available fetch-crl >/dev/null 2>&1; then
      log "Enabling EPEL 10 for fetch-crl."
      dnf -y install "https://dl.fedoraproject.org/pub/epel/epel-release-latest-10.noarch.rpm"
    fi
    dnf -y install fetch-crl
  fi
}

# Environment file uses shell-compatible double-quoted values. Escape the only
# characters that retain special meaning inside double quotes.
env_quote() {
  local v="$1"
  [[ "$v" != *$'\n'* && "$v" != *$'\r'* ]] || die "Configuration values cannot contain newlines."
  v=${v//\\/\\\\}
  v=${v//\"/\\\"}
  v=${v//\$/\\\$}
  v=${v//\`/\\\`}
  printf '"%s"' "$v"
}

load_env() {
  [[ -r "$ENV_FILE" ]] || return 1
  # shellcheck disable=SC1090
  source "$ENV_FILE"
}

prompt_value() {
  local var_name="$1" label="$2" default="$3" secret="${4:-0}" answer=""
  if [[ "$secret" == 1 ]]; then
    if [[ -n "$default" ]]; then
      read -r -s -p "$label [Enter = keep current]: " answer
      printf '\n'
      [[ -n "$answer" ]] || answer="$default"
    else
      while [[ -z "$answer" ]]; do
        read -r -s -p "$label: " answer
        printf '\n'
        [[ -n "$answer" ]] || echo "A non-empty password is required." >&2
      done
    fi
  else
    read -r -p "$label [$default]: " answer
    answer="${answer:-$default}"
  fi
  printf -v "$var_name" '%s' "$answer"
}

show_config() {
  cat <<SUMMARY

General configuration
---------------------
Public hostname       : ${ATLAS_PUBLIC_HOSTNAME:-}
Database name         : ${ATLAS_DB_NAME:-}
Database host (RW)    : ${ATLAS_DB_RW_HOST:-}
Database user (RW)    : ${ATLAS_DB_RW_USER:-}
Database password (RW): ********
Database host (RO)    : ${ATLAS_DB_RO_HOST:-}
Database user (RO)    : ${ATLAS_DB_RO_USER:-}
Database password (RO): ********
Database host (broker): ${ATLAS_DB_BROKER_HOST:-}
Database user (broker): ${ATLAS_DB_BROKER_USER:-}
Broker password       : ********
DB grant source host  : ${ATLAS_DB_GRANT_HOST:-}
Host certificate src  : ${ATLAS_HOST_CERT_SOURCE:-}
Host private key src  : ${ATLAS_HOST_KEY_SOURCE:-}
Installed certificate : $CERT_DST
Installed private key : $KEY_DST
IGTF CA directory     : $CA_DIR
Application path      : $APP_LINK
SUMMARY
}

configure_first_or_full() {
  local first="$1"
  if [[ "$first" == 1 ]]; then
    ATLAS_PUBLIC_HOSTNAME="$DEFAULT_PUBLIC_HOSTNAME"
    ATLAS_DB_NAME="$DEFAULT_DB_NAME"
    ATLAS_DB_RW_HOST="$DEFAULT_DB_HOST"
    ATLAS_DB_RW_USER="$DEFAULT_DB_RW_USER"
    ATLAS_DB_RW_PASSWORD=""
    ATLAS_DB_RO_HOST="$DEFAULT_DB_HOST"
    ATLAS_DB_RO_USER="$DEFAULT_DB_RO_USER"
    ATLAS_DB_RO_PASSWORD=""
    ATLAS_DB_BROKER_HOST="$DEFAULT_DB_HOST"
    ATLAS_DB_BROKER_USER="$DEFAULT_DB_BROKER_USER"
    ATLAS_DB_BROKER_PASSWORD=""
    ATLAS_DB_GRANT_HOST="$DEFAULT_GRANT_HOST"
    ATLAS_HOST_CERT_SOURCE=""
    ATLAS_HOST_KEY_SOURCE=""
  else
    load_env || die "Cannot read existing $ENV_FILE"
  fi

  prompt_value ATLAS_DB_RW_HOST "Database server address" "${ATLAS_DB_RW_HOST:-$DEFAULT_DB_HOST}"
  ATLAS_DB_RO_HOST="$ATLAS_DB_RW_HOST"
  ATLAS_DB_BROKER_HOST="$ATLAS_DB_RW_HOST"
  prompt_value ATLAS_DB_RW_USER "Application RW database user" "${ATLAS_DB_RW_USER:-$DEFAULT_DB_RW_USER}"
  prompt_value ATLAS_DB_RW_PASSWORD "Application RW database password" "${ATLAS_DB_RW_PASSWORD:-}" 1
  prompt_value ATLAS_DB_RO_USER "Application RO database user" "${ATLAS_DB_RO_USER:-$DEFAULT_DB_RO_USER}"
  prompt_value ATLAS_DB_RO_PASSWORD "Application RO database password" "${ATLAS_DB_RO_PASSWORD:-}" 1
  prompt_value ATLAS_DB_BROKER_USER "Application broker database user" "${ATLAS_DB_BROKER_USER:-$ATLAS_DB_RW_USER}"
  prompt_value ATLAS_DB_BROKER_PASSWORD "Application broker database password" "${ATLAS_DB_BROKER_PASSWORD:-$ATLAS_DB_RW_PASSWORD}" 1
  prompt_value ATLAS_HOST_CERT_SOURCE "Source host certificate/full chain PEM" "${ATLAS_HOST_CERT_SOURCE:-/etc/letsencrypt/live/apps.desalvo.eu/fullchain.pem}"
  prompt_value ATLAS_HOST_KEY_SOURCE "Source host private key PEM" "${ATLAS_HOST_KEY_SOURCE:-/etc/letsencrypt/live/apps.desalvo.eu/privkey.pem}"
  prompt_value ATLAS_PUBLIC_HOSTNAME "Public hostname exposed by reverse proxy" "${ATLAS_PUBLIC_HOSTNAME:-$DEFAULT_PUBLIC_HOSTNAME}"
  ATLAS_DB_NAME="${ATLAS_DB_NAME:-$DEFAULT_DB_NAME}"
  ATLAS_DB_GRANT_HOST="${ATLAS_DB_GRANT_HOST:-$DEFAULT_GRANT_HOST}"
}

configure_cert_only() {
  load_env || die "$ENV_FILE does not exist. Run a full installation first."
  prompt_value ATLAS_HOST_CERT_SOURCE "Source host certificate/full chain PEM" "${ATLAS_HOST_CERT_SOURCE:-}"
  prompt_value ATLAS_HOST_KEY_SOURCE "Source host private key PEM" "${ATLAS_HOST_KEY_SOURCE:-}"
}

write_env() {
  install -d -o root -g atlas-install -m 2770 "$ENV_DIR"
  local tmp
  tmp=$(mktemp "$ENV_DIR/.atlas-install.env.XXXXXX")
  trap 'rm -f "$tmp"' RETURN
  {
    echo '# Managed by install-atlas-rhel10.sh. Contains secrets.'
    printf 'ATLAS_PUBLIC_HOSTNAME=%s\n' "$(env_quote "$ATLAS_PUBLIC_HOSTNAME")"
    printf 'ATLAS_DB_NAME=%s\n' "$(env_quote "${ATLAS_DB_NAME:-$DEFAULT_DB_NAME}")"
    printf 'ATLAS_DB_RW_HOST=%s\n' "$(env_quote "$ATLAS_DB_RW_HOST")"
    printf 'ATLAS_DB_RW_USER=%s\n' "$(env_quote "$ATLAS_DB_RW_USER")"
    printf 'ATLAS_DB_RW_PASSWORD=%s\n' "$(env_quote "$ATLAS_DB_RW_PASSWORD")"
    printf 'ATLAS_DB_RO_HOST=%s\n' "$(env_quote "$ATLAS_DB_RO_HOST")"
    printf 'ATLAS_DB_RO_USER=%s\n' "$(env_quote "$ATLAS_DB_RO_USER")"
    printf 'ATLAS_DB_RO_PASSWORD=%s\n' "$(env_quote "$ATLAS_DB_RO_PASSWORD")"
    printf 'ATLAS_DB_BROKER_HOST=%s\n' "$(env_quote "$ATLAS_DB_BROKER_HOST")"
    printf 'ATLAS_DB_BROKER_USER=%s\n' "$(env_quote "$ATLAS_DB_BROKER_USER")"
    printf 'ATLAS_DB_BROKER_PASSWORD=%s\n' "$(env_quote "$ATLAS_DB_BROKER_PASSWORD")"
    printf 'ATLAS_DB_GRANT_HOST=%s\n' "$(env_quote "${ATLAS_DB_GRANT_HOST:-$DEFAULT_GRANT_HOST}")"
    printf 'ATLAS_HOST_CERT_SOURCE=%s\n' "$(env_quote "$ATLAS_HOST_CERT_SOURCE")"
    printf 'ATLAS_HOST_KEY_SOURCE=%s\n' "$(env_quote "$ATLAS_HOST_KEY_SOURCE")"
    printf 'ATLAS_HOST_CERT=%s\n' "$(env_quote "$CERT_DST")"
    printf 'ATLAS_HOST_KEY=%s\n' "$(env_quote "$KEY_DST")"
    printf 'ATLAS_UPLOAD_PATH=%s\n' '"/var/lib/atlas-install/log"'
    printf 'ATLAS_ARCHIVE_PATH=%s\n' '"/var/lib/atlas-install/logbackup"'
    printf 'ATLAS_CACHE_PATH=%s\n' '"/var/cache/atlas-install"'
    printf 'ATLAS_KML_CACHE=%s\n' '"/var/cache/atlas-install/install.kml"'
    printf 'ATLAS_DEBUG=%s\n' '"0"'
    printf 'ATLAS_MAX_LOG_DIRS=%s\n' '"4"'
  } >"$tmp"
  chown root:atlas-install "$tmp"
  chmod 0660 "$tmp"
  mv -f "$tmp" "$ENV_FILE"
  trap - RETURN
}

validate_certificate_pair() {
  local cert="$1" key="$2" host="$3"
  [[ -r "$cert" ]] || die "Certificate is not readable: $cert"
  [[ -r "$key" ]] || die "Private key is not readable: $key"
  openssl x509 -in "$cert" -noout >/dev/null 2>&1 || die "Invalid X.509 certificate: $cert"
  openssl pkey -in "$key" -noout >/dev/null 2>&1 || die "Invalid private key: $key"
  local cert_pub key_pub
  cert_pub=$(openssl x509 -in "$cert" -pubkey -noout | openssl pkey -pubin -outform DER 2>/dev/null | sha256sum | awk '{print $1}')
  key_pub=$(openssl pkey -in "$key" -pubout -outform DER 2>/dev/null | sha256sum | awk '{print $1}')
  [[ "$cert_pub" == "$key_pub" ]] || die "Certificate and private key do not match."
  openssl x509 -in "$cert" -noout -checkhost "$host" >/dev/null 2>&1 || die "Certificate does not cover hostname $host"
  openssl x509 -in "$cert" -noout -checkend 86400 >/dev/null 2>&1 || die "Certificate is expired or expires within 24 hours."
}

install_certificate_if_changed() {
  validate_certificate_pair "$ATLAS_HOST_CERT_SOURCE" "$ATLAS_HOST_KEY_SOURCE" "$ATLAS_PUBLIC_HOSTNAME"
  install -d -o root -g root -m 0755 /etc/pki/tls/certs
  install -d -o root -g root -m 0700 /etc/pki/tls/private
  local changed=0
  if [[ ! -f "$CERT_DST" ]] || ! cmp -s "$ATLAS_HOST_CERT_SOURCE" "$CERT_DST"; then
    install -o root -g root -m 0644 "$ATLAS_HOST_CERT_SOURCE" "$CERT_DST"
    changed=1
  fi
  if [[ ! -f "$KEY_DST" ]] || ! cmp -s "$ATLAS_HOST_KEY_SOURCE" "$KEY_DST"; then
    install -o root -g root -m 0600 "$ATLAS_HOST_KEY_SOURCE" "$KEY_DST"
    changed=1
  fi
  CERT_CHANGED="$changed"
}

igtf_refresh_needed() {
  (( UPDATE_IGTF )) && return 0
  [[ -d "$CA_DIR" ]] || return 0
  find "$CA_DIR" -maxdepth 1 -type f -name '*.0' -print -quit 2>/dev/null | grep -q . || return 0
  [[ -f "$IGTF_STAMP" ]] || return 0
  find "$IGTF_STAMP" -mtime -30 -print -quit | grep -q . && return 1
  return 0
}

install_igtf_trust_anchors() {
  if ! igtf_refresh_needed; then
    log "IGTF trust anchors are present and were refreshed within 30 days."
    return 0
  fi
  log "Refreshing IGTF accredited trust anchors."
  local work stage profile url bundle root
  work=$(mktemp -d)
  stage="$work/stage"
  mkdir -p "$stage"
  trap 'rm -rf "$work"' RETURN
  for profile in classic mics iota; do
    url="https://dist.igtf.net/distribution/current/accredited/igtf-preinstalled-bundle-${profile}.tar.gz"
    bundle="$work/${profile}.tar.gz"
    curl --fail --silent --show-error --location --proto '=https' --tlsv1.2 "$url" -o "$bundle"
    tar -xzf "$bundle" -C "$stage"
  done
  # Some historical bundles contain a certificates/ directory; normalize it.
  if [[ -d "$stage/certificates" ]]; then
    root="$stage/certificates"
  else
    root="$stage"
  fi
  find "$root" -maxdepth 1 -type f -name '*.0' -print -quit | grep -q . || die "Downloaded IGTF bundle contains no hashed CA certificates."
  install -d -o root -g root -m 0755 "$CA_DIR"
  rsync -a --delete --exclude='*.r[0-9]*' "$root/" "$CA_DIR/"
  chown -R root:root "$CA_DIR"
  find "$CA_DIR" -type d -exec chmod 0755 {} +
  find "$CA_DIR" -type f -exec chmod 0644 {} +
  touch "$IGTF_STAMP"
  rm -rf "$work"
  trap - RETURN
}

configure_fetch_crl_timer() {
  local fetch
  fetch=$(command -v fetch-crl || true)
  [[ -n "$fetch" ]] || die "fetch-crl is not installed."
  if systemctl list-unit-files fetch-crl.timer --no-legend 2>/dev/null | grep -q '^fetch-crl.timer'; then
    systemctl enable --now fetch-crl.timer
  else
    cat >/etc/systemd/system/atlas-fetch-crl.service <<SERVICE
[Unit]
Description=Refresh IGTF certificate revocation lists
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=$fetch
SERVICE
    cat >/etc/systemd/system/atlas-fetch-crl.timer <<'TIMER'
[Unit]
Description=Periodic IGTF CRL refresh for ATLAS Install

[Timer]
OnBootSec=5min
OnUnitActiveSec=6h
RandomizedDelaySec=20min
Persistent=true

[Install]
WantedBy=timers.target
TIMER
    systemctl daemon-reload
    systemctl enable --now atlas-fetch-crl.timer
  fi
  log "Refreshing CRLs now."
  "$fetch" || warn "fetch-crl returned a non-zero status; inspect its output before production use."
}

install_application() {
  getent group atlas-install >/dev/null 2>&1 || groupadd --system atlas-install
  id atlas-install >/dev/null 2>&1 || useradd --system --gid atlas-install --home-dir "$STATE_DIR" --shell /sbin/nologin atlas-install
  usermod -a -G atlas-install apache

  install -d -o atlas-install -g atlas-install -m 0750 "$STATE_DIR" "$STATE_DIR/log" "$STATE_DIR/logbackup" /var/cache/atlas-install
  install -d -o root -g root -m 0755 /var/www/html "$PERSISTENT_PKG_ROOT"
  if [[ "$PKG_ROOT" != "$PERSISTENT_PKG_ROOT" ]]; then
    rsync -a --delete "$PKG_ROOT/" "$PERSISTENT_PKG_ROOT/"
    PKG_ROOT="$PERSISTENT_PKG_ROOT"
    APP_SRC="$PKG_ROOT/var/www/html/atlas_install-3.0.0"
  fi
  install -o root -g root -m 0755 "$PKG_ROOT/install-atlas-rhel10.sh" /usr/local/sbin/atlas-install-config
  rsync -a --delete "$APP_SRC/" "$APP_DST/"
  chown -R root:root "$APP_DST"
  find "$APP_DST" -type d -exec chmod 0755 {} +
  find "$APP_DST" -type f -exec chmod 0644 {} +
  chmod 0755 "$APP_DST/conf/scripts/cleanup-logfiles" "$APP_DST/conf/scripts/create_install_db.sh" "$APP_DST/conf/scripts/install_cron.sh" "$APP_DST/conf/scripts/install-rhel10.sh" 2>/dev/null || true
  if [[ -e "$APP_LINK" && ! -L "$APP_LINK" ]]; then
    local backup="${APP_LINK}.pre-rhel10.$(date +%Y%m%d%H%M%S)"
    warn "$APP_LINK is a real file/directory; moving it to $backup"
    mv "$APP_LINK" "$backup"
  fi
  ln -sfn "atlas_install-3.0.0" "$APP_LINK"

  install -o root -g root -m 0644 "$PKG_ROOT/etc/cron.d/create_ljsfi_plots.cron" /etc/cron.d/create_ljsfi_plots.cron
  install -o root -g root -m 0644 "$PKG_ROOT/etc/cron.d/ljsf-cleanup-logfiles.cron" /etc/cron.d/ljsf-cleanup-logfiles.cron
}

render_apache_config() {
  python3 - "$PKG_ROOT/etc/httpd/conf.d/25-atlas-install.conf.template" "$HTTPD_CONF" \
    "$ATLAS_PUBLIC_HOSTNAME" "$CERT_DST" "$KEY_DST" <<'PY'
from pathlib import Path
import sys
src,dst,hostname,cert,key=sys.argv[1:]
s=Path(src).read_text()
s=s.replace('@PUBLIC_HOSTNAME@',hostname).replace('@HOST_CERT@',cert).replace('@HOST_KEY@',key)
Path(dst).write_text(s)
PY
  python3 - "$PKG_ROOT/etc/httpd/conf.d/25-atlas-install-nonssl.conf.template" "$HTTPD_HTTP_CONF" \
    "$ATLAS_PUBLIC_HOSTNAME" <<'PY'
from pathlib import Path
import sys
src,dst,hostname=sys.argv[1:]
s=Path(src).read_text().replace('@PUBLIC_HOSTNAME@',hostname)
Path(dst).write_text(s)
PY
  chown root:root "$HTTPD_CONF" "$HTTPD_HTTP_CONF"
  chmod 0644 "$HTTPD_CONF" "$HTTPD_HTTP_CONF"
}

configure_selinux() {
  semanage fcontext -a -t httpd_sys_rw_content_t '/var/lib/atlas-install(/.*)?' 2>/dev/null || semanage fcontext -m -t httpd_sys_rw_content_t '/var/lib/atlas-install(/.*)?'
  semanage fcontext -a -t httpd_sys_rw_content_t '/var/cache/atlas-install(/.*)?' 2>/dev/null || semanage fcontext -m -t httpd_sys_rw_content_t '/var/cache/atlas-install(/.*)?'
  restorecon -RF /var/lib/atlas-install /var/cache/atlas-install /var/www/html /etc/httpd /etc/pki/tls >/dev/null || true
  setsebool -P httpd_can_network_connect_db 1
  setsebool -P httpd_can_network_connect 1
}

verify_secret_permissions() {
  [[ $(stat -c '%U:%G %a' "$ENV_FILE") == 'root:atlas-install 660' ]] || die "Unsafe permissions on $ENV_FILE"
  [[ $(stat -c '%U:%G %a' "$KEY_DST") == 'root:root 600' ]] || die "Unsafe permissions on $KEY_DST"
  if grep -RIlE 'ATLAS_DB_(RW|RO|BROKER)_PASSWORD=.*[^@]$' /var/www/html/atlas_install 2>/dev/null | grep -vE '/(RHEL10-MIGRATION|SQL-INJECTION-HARDENING)\.md$' | grep -q .; then
    die "A database password assignment was detected under the web document root."
  fi
}

confirm() {
  (( ASSUME_YES )) && return 0
  local ans
  read -r -p "Proceed with these settings? [Y/n]: " ans
  [[ -z "$ans" || "$ans" =~ ^[Yy]$ ]] || exit 0
}

main() {
  require_rhel10_family
  local first=0
  [[ -f "$ENV_FILE" ]] || first=1

  if (( CERT_ONLY )); then
    configure_cert_only
    show_config
    confirm
    install_dependencies
    install_certificate_if_changed
    write_env
    if (( CERT_CHANGED )); then
      apachectl configtest
      systemctl reload httpd
      log "Host certificate updated and Apache reloaded."
    else
      log "Installed host certificate is already current; Apache reload not needed."
    fi
    exit 0
  fi

  if (( first )); then
    log "First configuration: project defaults will be proposed."
    configure_first_or_full 1
  elif (( RECONFIGURE )); then
    log "Reconfiguration requested: current values will be proposed as defaults."
    configure_first_or_full 0
  else
    load_env || die "Cannot read $ENV_FILE"
  fi

  show_config
  confirm

  install_dependencies
  install_application
  write_env
  install_igtf_trust_anchors
  configure_fetch_crl_timer
  install_certificate_if_changed
  render_apache_config
  configure_selinux

  systemctl enable php-fpm httpd >/dev/null
  systemctl restart php-fpm
  apachectl configtest
  verify_secret_permissions

  if systemctl is-active --quiet httpd; then
    systemctl reload httpd
    if (( CERT_CHANGED )); then
      log "Host certificate changed; Apache reloaded."
    else
      log "Apache configuration reloaded."
    fi
  else
    systemctl start httpd
    log "Apache started."
  fi

  log "Installation/update completed."
  log "Public URL: https://$ATLAS_PUBLIC_HOSTNAME/atlas_install/"
  log "Public paths remain accessible without a client certificate; /atlas_install/protected requires an IGTF-valid client certificate."
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
  main "$@"
fi
