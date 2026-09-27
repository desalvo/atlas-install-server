#!/usr/bin/env bash
set -euo pipefail
mode=${1:-all}
APP=/var/www/html/atlas_install
case "$mode" in
  plots)
    exec /usr/bin/php "$APP/create_ljsfi_plots.php"
    ;;
  cleanup)
    exec "$APP/conf/scripts/cleanup-logfiles"
    ;;
  all)
    /usr/bin/php "$APP/create_ljsfi_plots.php"
    exec "$APP/conf/scripts/cleanup-logfiles"
    ;;
  *)
    echo "usage: atlas-maintenance {plots|cleanup|all}" >&2
    exit 2
    ;;
esac
