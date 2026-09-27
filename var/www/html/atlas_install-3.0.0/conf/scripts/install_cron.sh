#!/usr/bin/bash
set -euo pipefail
DIR="$(cd -P "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRONDIR="$(readlink -f "$DIR/../cron")"
for src in "$CRONDIR"/*.cron; do
  [[ -f "$src" ]] || continue
  install -o root -g root -m 0644 "$src" "/etc/cron.d/$(basename "$src")"
done
