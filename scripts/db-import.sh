#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${ROOT_DIR}/docker/mysql/backups"
INPUT_PATH="${1:-}"
COMPOSE_CMD=(
    docker
    compose
    -f "${ROOT_DIR}/docker-compose.yml"
    --project-directory "${ROOT_DIR}"
)

resolve_input_path() {
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

if [[ -z "${INPUT_PATH}" ]]; then
    LATEST_BACKUP="$(
        (
            cd "${BACKUP_DIR}" || exit 1
            find . -maxdepth 1 -type f \( -name '*.sql' -o -name '*.sql.gz' \) -exec ls -1t {} + 2>/dev/null \
                | head -n 1
        )
    )"

    if [[ -z "${LATEST_BACKUP}" ]]; then
        echo "No backup file found in ${BACKUP_DIR}" >&2
        exit 1
    fi

    INPUT_PATH="${BACKUP_DIR}/${LATEST_BACKUP#./}"
else
    INPUT_PATH="$(resolve_input_path "${INPUT_PATH}")"
fi

if [[ ! -f "${INPUT_PATH}" ]]; then
    echo "Backup file not found: ${INPUT_PATH}" >&2
    exit 1
fi

if [[ "${INPUT_PATH}" == *.gz ]]; then
    gunzip -c "${INPUT_PATH}" | "${COMPOSE_CMD[@]}" exec -T db sh -lc \
        'exec mysql -uroot -p"${MYSQL_ROOT_PASSWORD}"'
else
    "${COMPOSE_CMD[@]}" exec -T db sh -lc \
        'exec mysql -uroot -p"${MYSQL_ROOT_PASSWORD}"' \
        < "${INPUT_PATH}"
fi

echo "Backup imported: ${INPUT_PATH}"
