#!/usr/bin/env bash

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_PATH="${1:-}"
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

if [[ -n "${OUTPUT_PATH}" ]]; then
    OUTPUT_PATH="$(resolve_output_path "${OUTPUT_PATH}")"
    mkdir -p "$(dirname "${OUTPUT_PATH}")"
    exec > >(tee "${OUTPUT_PATH}") 2>&1
fi

print_header() {
    local title="$1"

    printf '\n== %s ==\n' "${title}"
}

run_optional() {
    local description="$1"
    shift

    echo "\$ $*"

    if ! "$@"; then
        echo "[warn] ${description} に失敗しました"
    fi
}

print_header "Environment"
echo "root_dir: ${ROOT_DIR}"
echo "current_dir: $(pwd)"
echo "timestamp: $(date '+%Y-%m-%d %H:%M:%S %z')"
if [[ -n "${OUTPUT_PATH}" ]]; then
    echo "output_path: ${OUTPUT_PATH}"
fi

print_header "Compose Status"
run_optional "docker compose ps" "${COMPOSE_CMD[@]}" ps

DB_CONTAINER_ID="$("${COMPOSE_CMD[@]}" ps -q db 2>/dev/null || true)"

if [[ -z "${DB_CONTAINER_ID}" ]]; then
    print_header "Database Container"
    echo "db service is not running"
    exit 0
fi

MYSQL_DATABASE="$(
    docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' "${DB_CONTAINER_ID}" \
        | awk -F= '$1=="MYSQL_DATABASE" {print $2}'
)"

DB_MOUNT_TYPE="$(
    docker inspect --format '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Type}}{{end}}{{end}}' "${DB_CONTAINER_ID}"
)"
DB_MOUNT_NAME="$(
    docker inspect --format '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Name}}{{end}}{{end}}' "${DB_CONTAINER_ID}"
)"
DB_MOUNT_SOURCE="$(
    docker inspect --format '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Source}}{{end}}{{end}}' "${DB_CONTAINER_ID}"
)"

print_header "Database Container"
echo "container_id: ${DB_CONTAINER_ID}"
echo "mysql_database: ${MYSQL_DATABASE:-unknown}"
echo "mount_type: ${DB_MOUNT_TYPE:-unknown}"
echo "mount_name: ${DB_MOUNT_NAME:-unknown}"
echo "mount_source: ${DB_MOUNT_SOURCE:-unknown}"

if [[ -n "${DB_MOUNT_NAME}" ]]; then
    print_header "Volume Inspect"
    run_optional "docker volume inspect" docker volume inspect "${DB_MOUNT_NAME}"
fi

print_header "MySQL Databases"
run_optional \
    "SHOW DATABASES" \
    "${COMPOSE_CMD[@]}" exec -T db sh -lc \
    'exec mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SHOW DATABASES;"'

if [[ -n "${MYSQL_DATABASE}" ]]; then
    print_header "MySQL Tables"
    run_optional \
        "SHOW TABLES" \
        "${COMPOSE_CMD[@]}" exec -T db sh -lc \
        "exec mysql -uroot -p\"\${MYSQL_ROOT_PASSWORD}\" \"${MYSQL_DATABASE}\" -e \"SHOW TABLES;\""
fi

print_header "Recent DB Logs"
run_optional "docker compose logs db" "${COMPOSE_CMD[@]}" logs db --tail 50
