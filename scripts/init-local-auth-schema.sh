#!/usr/bin/env bash
set -euo pipefail
ENV_FILE=${ATLAS_ENV_FILE:-/var/lib/atlas-install/config/atlas-install.env}
SQL_FILE=${ATLAS_LOCAL_AUTH_SCHEMA_SQL:-$(cd "$(dirname "$0")/.." && pwd)/sql/local-auth-schema.sql}
DB_USER=${ATLAS_SCHEMA_ADMIN_USER:-root}
[[ -r "$ENV_FILE" ]] || { echo "ERROR: cannot read $ENV_FILE" >&2; exit 1; }
[[ -r "$SQL_FILE" ]] || { echo "ERROR: cannot read $SQL_FILE" >&2; exit 1; }
set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a
DB_HOST=${ATLAS_DB_RW_HOST:?ATLAS_DB_RW_HOST missing}
DB_NAME=${ATLAS_DB_NAME:-atlas_install_panda}
DB_PORT=${ATLAS_DB_PORT:-3306}
read -r -s -p "Database password for ${DB_USER}@${DB_HOST}: " DB_PASSWORD; echo
args=(--no-defaults --protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME")
case "${ATLAS_DB_SSL:-0}" in 1|true|TRUE|yes|YES|on|ON) args+=(--ssl) ;; esac
if [[ -n "${ATLAS_DB_SSL_CA:-}" ]]; then args+=(--ssl-ca "$ATLAS_DB_SSL_CA"); fi
MYSQL_PWD="$DB_PASSWORD" mariadb "${args[@]}" < "$SQL_FILE"
unset DB_PASSWORD
echo "Local authentication schema initialized in ${DB_NAME}."
