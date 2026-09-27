#!/usr/bin/bash
set -euo pipefail
umask 077
DIR="$(cd -P "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQLDIR="$(dirname "$DIR")/sql"
ENV_FILE="${ATLAS_ENV_FILE:-/etc/atlas-install/atlas-install.env}"
[[ -r "$ENV_FILE" ]] || { echo "Missing $ENV_FILE" >&2; exit 2; }
ini_get() {
  /usr/bin/php -r '$a=parse_ini_file($argv[1],false,INI_SCANNER_RAW)?:[];$k=$argv[2];echo $a[$k]??"";' "$ENV_FILE" "$1"
}
ATLAS_DB_NAME="$(ini_get ATLAS_DB_NAME)"
ATLAS_DB_RO_USER="$(ini_get ATLAS_DB_RO_USER)"
ATLAS_DB_RO_PASSWORD="$(ini_get ATLAS_DB_RO_PASSWORD)"
ATLAS_DB_RW_USER="$(ini_get ATLAS_DB_RW_USER)"
ATLAS_DB_RW_PASSWORD="$(ini_get ATLAS_DB_RW_PASSWORD)"
ATLAS_DB_GRANT_HOST="$(ini_get ATLAS_DB_GRANT_HOST)"
: "${ATLAS_DB_NAME:?ATLAS_DB_NAME is required}"
: "${ATLAS_DB_RO_USER:?ATLAS_DB_RO_USER is required}"
: "${ATLAS_DB_RO_PASSWORD:?ATLAS_DB_RO_PASSWORD is required}"
: "${ATLAS_DB_RW_USER:?ATLAS_DB_RW_USER is required}"
: "${ATLAS_DB_RW_PASSWORD:?ATLAS_DB_RW_PASSWORD is required}"
ATLAS_DB_GRANT_HOST="${ATLAS_DB_GRANT_HOST:-localhost}"
export ATLAS_DB_NAME ATLAS_DB_RO_USER ATLAS_DB_RO_PASSWORD ATLAS_DB_RW_USER ATLAS_DB_RW_PASSWORD ATLAS_DB_GRANT_HOST
read -r -p "Database admin user [root]: " DBUSER
DBUSER="${DBUSER:-root}"
TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT
python3 - "$SQLDIR/create_install_db.sql.template" "$TMP" <<'PY2'
import os,sys
src,dst=sys.argv[1:]
text=open(src).read()
vals={'@DBNAME@':os.environ['ATLAS_DB_NAME'],'@DBREADER@':os.environ['ATLAS_DB_RO_USER'],'@DBREADERPASS@':os.environ['ATLAS_DB_RO_PASSWORD'],'@DBWRITER@':os.environ['ATLAS_DB_RW_USER'],'@DBWRITERPASS@':os.environ['ATLAS_DB_RW_PASSWORD'],'@DBHOST@':os.environ['ATLAS_DB_GRANT_HOST']}
for k,v in vals.items():
    if any(c in v for c in "\n\r\x00"):
        raise SystemExit('Invalid control character in database configuration')
    text=text.replace(k,v.replace("'","''"))
open(dst,'w').write(text)
PY2
mysql -u "$DBUSER" -p < "$TMP"
