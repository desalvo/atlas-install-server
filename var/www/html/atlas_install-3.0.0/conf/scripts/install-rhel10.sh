#!/usr/bin/bash
set -euo pipefail
if [[ -x /usr/local/sbin/atlas-install-config ]]; then
  exec /usr/local/sbin/atlas-install-config "$@"
fi
echo "Run install-atlas-rhel10.sh from the deployment archive first." >&2
exit 1
