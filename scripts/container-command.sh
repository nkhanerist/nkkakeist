#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
SERVICE_NAME="${DOCKER_COMPOSE_SERVICE:-app}"

if [ "$#" -eq 0 ]; then
    echo "Usage: $0 <command> [args...]" >&2
    exit 64
fi

cd "${PROJECT_DIR}"

exec_options=()
if [ ! -t 0 ] || [ ! -t 1 ]; then
    exec_options=(-T)
fi

if docker compose ps --status running --services "${SERVICE_NAME}" | grep -qx "${SERVICE_NAME}"; then
    exec docker compose exec "${exec_options[@]}" "${SERVICE_NAME}" "$@"
fi

exec docker compose run --rm "${SERVICE_NAME}" "$@"
