#!/usr/bin/env bash

set -euo pipefail

script_started_ms="$(date +%s%3N)"

usage() {
    cat <<'EOF'
Usage: benchmark.sh restore [options]

Required:
  --method METHOD       baseline, carpov, erkin, or edbarchive
  --bundle FILE         bundle.tar.gz produced by the backup benchmark
  --database NAME       new database receiving the restored data

Required for carpov and erkin:
  --key-dir DIR         directory containing existing private.bin and public.bin

Optional:
  --metrics FILE        output metrics path (default: /results/restore-METHOD.json)
  --discard-bundle      remove the compressed bundle after successful extraction
EOF
}

method=""
bundle_file=""
target_db=""
key_dir=""
metrics_file=""
discard_bundle=0

while (($#)); do
    case "$1" in
        --method) method="${2:?missing value for --method}"; shift 2 ;;
        --bundle) bundle_file="${2:?missing value for --bundle}"; shift 2 ;;
        --database) target_db="${2:?missing value for --database}"; shift 2 ;;
        --key-dir) key_dir="${2:?missing value for --key-dir}"; shift 2 ;;
        --metrics) metrics_file="${2:?missing value for --metrics}"; shift 2 ;;
        --discard-bundle) discard_bundle=1; shift ;;
        --help|-h) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
    esac
done

case "${method}" in
    baseline|carpov|erkin|edbarchive) ;;
    *) echo "Invalid or missing --method." >&2; exit 2 ;;
esac
if [[ -z "${bundle_file}" || -z "${target_db}" ]]; then
    echo "--bundle and --database are required." >&2
    exit 2
fi
if [[ ! "${target_db}" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Unsafe target database name: ${target_db}" >&2
    exit 2
fi
[[ -f "${bundle_file}" ]] || { echo "Bundle not found: ${bundle_file}" >&2; exit 2; }
if [[ "${method}" == "carpov" || "${method}" == "erkin" ]]; then
    [[ -f "${key_dir}/private.bin" && -f "${key_dir}/public.bin" ]] || {
        echo "--key-dir must contain private.bin and public.bin." >&2
        exit 2
    }
fi
if [[ -z "${metrics_file}" ]]; then
    metrics_file="/results/restore-${method}.json"
fi
mkdir -p "$(dirname "${metrics_file}")"

db_host="${BENCHMARK_DB_HOST:-127.0.0.1}"
db_port="${BENCHMARK_DB_PORT:-3306}"
db_user="${BENCHMARK_DB_USER:-edbarchive}"
export MYSQL_PWD="${BENCHMARK_DB_PASSWORD:-edbarchive}"
temporary_root="${BENCHMARK_TEMP_DIR:-/tmp}"
mkdir -p "${temporary_root}"
[[ -d "${temporary_root}" && -w "${temporary_root}" ]] || {
    echo "Benchmark temporary directory is not writable: ${temporary_root}" >&2
    exit 2
}
temporary_dir="$(mktemp -d "${temporary_root%/}/restore.XXXXXX")"
created_database=0

cleanup() {
    if [[ "${created_database}" -eq 1 ]]; then
        mariadb -h "${db_host}" -P "${db_port}" -u "${db_user}" \
            -e "DROP DATABASE IF EXISTS \`${target_db}\`" >/dev/null 2>&1 || true
    fi
    rm -rf -- "${temporary_dir}"
}
trap cleanup EXIT

pigz -dc "${bundle_file}" | tar -C "${temporary_dir}" -xf -
bundle_dir="${temporary_dir}/bundle"
[[ -f "${bundle_dir}/dump.sql" ]] || { echo "Invalid bundle: dump.sql is missing." >&2; exit 2; }

if [[ "${discard_bundle}" -eq 1 ]]; then
    rm -f -- "${bundle_file}"
fi

existing_db="$(mariadb -N -B -h "${db_host}" -P "${db_port}" -u "${db_user}" \
    -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='${target_db}'")"
if [[ -n "${existing_db}" ]]; then
    echo "Target database already exists: ${target_db}" >&2
    exit 2
fi

mariadb -h "${db_host}" -P "${db_port}" -u "${db_user}" \
    -e "CREATE DATABASE \`${target_db}\`"
created_database=1
mariadb -h "${db_host}" -P "${db_port}" -u "${db_user}" "${target_db}" < "${bundle_dir}/dump.sql"
rm -f -- "${bundle_dir}/dump.sql"

restore_args=(
    --method "${method}"
    --database "${target_db}"
    --bundle-dir "${bundle_dir}"
    --metrics "${metrics_file}"
)
if [[ "${discard_bundle}" -eq 1 ]]; then
    restore_args+=(--cleanup-ciphertexts true)
fi
if [[ "${method}" == "carpov" || "${method}" == "erkin" ]]; then
    restore_args+=(--key-dir "${key_dir}")
fi
benchmark-restore "${restore_args[@]}"

created_database=0
cleanup
trap - EXIT

script_finished_ms="$(date +%s%3N)"
total_elapsed_ms="$((script_finished_ms - script_started_ms))"
metrics_tmp="${metrics_file}.tmp"
jq \
    --argjson total_elapsed_ms "${total_elapsed_ms}" \
    '.backend_elapsed_ms = .elapsed_ms
     | del(.elapsed_ms)
     | .total_elapsed_ms = $total_elapsed_ms' \
    "${metrics_file}" > "${metrics_tmp}"
mv "${metrics_tmp}" "${metrics_file}"

echo "Restore benchmark completed: ${target_db}"
