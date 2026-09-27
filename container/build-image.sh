#!/usr/bin/env bash
set -euo pipefail
ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
ENGINE=${CONTAINER_ENGINE:-}
if [[ -z "$ENGINE" ]]; then
  if command -v podman >/dev/null 2>&1; then ENGINE=podman
  elif command -v docker >/dev/null 2>&1; then ENGINE=docker
  else echo "podman or docker is required" >&2; exit 1
  fi
fi
IMAGE=${1:-desalvo/atlas-install-server:latest}
exec "$ENGINE" build -f "$ROOT/container/Containerfile" -t "$IMAGE" "$ROOT"
