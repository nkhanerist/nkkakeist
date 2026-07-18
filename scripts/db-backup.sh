#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${ROOT_DIR}/docker/mysql/backups"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
DEFAULT_FILE="asset_manager-${TIMESTAMP}.sql.gz"
OUTPUT_PATH="${1:-${BACKUP_DIR}/${DEFAULT_FILE}}"
COMPOSE_CMD=(
    docker
    compose
    -f "${ROOT_DIR}/docker-compose.yml"
    --project-directory "${ROOT_DIR}"
)

resolve_output_path() {
    local path="$1"

    if [[ "${path}" == /* ]]; then
        printf '%s\n' "${path}"
        return
    fi

    if [[ "${path}" == ./* || "${path}" == ../* ]]; then
        printf '%s\n' "$(pwd)/${path}"
        return
    fi

    printf '%s\n' "${ROOT_DIR}/${path}"
}

mkdir -p "${BACKUP_DIR}"

if [[ $# -gt 0 ]]; then
    OUTPUT_PATH="$(resolve_output_path "${OUTPUT_PATH}")"
fi

if [[ "${OUTPUT_PATH}" != *.sql && "${OUTPUT_PATH}" != *.sql.gz ]]; then
    OUTPUT_PATH="${OUTPUT_PATH}.sql.gz"
fi

mkdir -p "$(dirname "${OUTPUT_PATH}")"

if [[ "${OUTPUT_PATH}" == *.gz ]]; then
    "${COMPOSE_CMD[@]}" exec -T db sh -lc \
        'exec mysqldump -uroot -p"${MYSQL_ROOT_PASSWORD}" --single-transaction --routines --triggers --events --databases "${MYSQL_DATABASE}" --add-drop-database --add-drop-table' \
        | gzip > "${OUTPUT_PATH}"
else
    "${COMPOSE_CMD[@]}" exec -T db sh -lc \
        'exec mysqldump -uroot -p"${MYSQL_ROOT_PASSWORD}" --single-transaction --routines --triggers --events --databases "${MYSQL_DATABASE}" --add-drop-database --add-drop-table' \
        > "${OUTPUT_PATH}"
fi

echo "Backup created: ${OUTPUT_PATH}"
