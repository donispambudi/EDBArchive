#!/usr/bin/env bash

set -euo pipefail

script_started_ms="$(date +%s%3N)"

usage() {
    cat <<'EOF'
Usage: benchmark.sh backup [options]

Required:
  --method METHOD       baseline, carpov, erkin, or edbarchive
  --source-db NAME      source database copied before it is modified
  --context FILE        existing context.bin
  --public-key FILE     existing public.bin
  --private-key FILE    existing private.bin used to generate relinearization.bin
                        and, for EDBArchive, rotation.bin

Optional:
  --config FILE         benchmark config (default: /configs/district.json)
  --name NAME           result directory name (default: METHOD-TIMESTAMP)
  --results-dir DIR     result root (default: /results)
EOF
}

method=""
source_db=""
context_file=""
public_key_file=""
private_key_file=""
config_file="/configs/district.json"
run_name=""
results_dir="/results"

while (($#)); do
    case "$1" in
        --method) method="${2:?missing value for --method}"; shift 2 ;;
        --source-db) source_db="${2:?missing value for --source-db}"; shift 2 ;;
        --context) context_file="${2:?missing value for --context}"; shift 2 ;;
        --public-key) public_key_file="${2:?missing value for --public-key}"; shift 2 ;;
        --private-key) private_key_file="${2:?missing value for --private-key}"; shift 2 ;;
        --config) config_file="${2:?missing value for --config}"; shift 2 ;;
        --name) run_name="${2:?missing value for --name}"; shift 2 ;;
        --results-dir) results_dir="${2:?missing value for --results-dir}"; shift 2 ;;
        --help|-h) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
    esac
done

case "${method}" in
    baseline|carpov|erkin|edbarchive) ;;
    *) echo "Invalid or missing --method." >&2; exit 2 ;;
esac

for value in "${source_db}" "${context_file}" "${public_key_file}" "${private_key_file}"; do
    if [[ -z "${value}" ]]; then
        echo "Missing a required backup option." >&2
        usage >&2
        exit 2
    fi
done
if [[ ! "${source_db}" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "Unsafe source database name: ${source_db}" >&2
    exit 2
fi
for path in "${context_file}" "${public_key_file}" "${private_key_file}" "${config_file}"; do
    [[ -f "${path}" ]] || { echo "File not found: ${path}" >&2; exit 2; }
done

if [[ -z "${run_name}" ]]; then
    run_name="${method}-$(date -u +%Y%m%dT%H%M%SZ)"
fi
if [[ ! "${run_name}" =~ ^[A-Za-z0-9_.-]+$ ]]; then
    echo "Unsafe result name: ${run_name}" >&2
    exit 2
fi

db_host="${BENCHMARK_DB_HOST:-127.0.0.1}"
db_port="${BENCHMARK_DB_PORT:-3306}"
db_user="${BENCHMARK_DB_USER:-edbarchive}"
export MYSQL_PWD="${BENCHMARK_DB_PASSWORD:-edbarchive}"

temporary_db="benchmark_${method}_$$_${RANDOM}"
run_dir="${results_dir}/${run_name}"
bundle_dir="${run_dir}/bundle"
mkdir -p "${bundle_dir}"

cleanup() {
    mariadb -h "${db_host}" -P "${db_port}" -u "${db_user}" \
        -e "DROP DATABASE IF EXISTS \`${temporary_db}\`" >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "Cloning ${source_db} to temporary database ${temporary_db}"
mariadb -h "${db_host}" -P "${db_port}" -u "${db_user}" \
    -e "CREATE DATABASE \`${temporary_db}\`"
mariadb-dump -h "${db_host}" -P "${db_port}" -u "${db_user}" \
    --single-transaction --routines --triggers "${source_db}" |
    mariadb -h "${db_host}" -P "${db_port}" -u "${db_user}" "${temporary_db}"

backup_args=(
    --method "${method}"
    --config "${config_file}"
    --database "${temporary_db}"
    --output-dir "${bundle_dir}"
    --context "${context_file}"
    --public-key "${public_key_file}"
    --private-key "${private_key_file}"
    --metrics "${run_dir}/backup-metrics.json"
)
benchmark-backup "${backup_args[@]}"

mariadb-dump -h "${db_host}" -P "${db_port}" -u "${db_user}" \
    --single-transaction --routines --triggers "${temporary_db}" > "${bundle_dir}/dump.sql"

tar_file="${run_dir}/bundle.tar"
tar -cf "${tar_file}" -C "${run_dir}" bundle

tar_bytes="$(stat -c%s "${tar_file}")"
dump_bytes="$(stat -c%s "${bundle_dir}/dump.sql")"
if [[ "${dump_bytes}" -gt "$((tar_bytes / 2))" ]]; then
    compression_level=8
else
    compression_level=1
fi
pigz -f -"${compression_level}" "${tar_file}"

cleanup
trap - EXIT

script_finished_ms="$(date +%s%3N)"
total_elapsed_ms="$((script_finished_ms - script_started_ms))"
bundle_bytes="$(stat -c%s "${run_dir}/bundle.tar.gz")"
metrics_tmp="${run_dir}/.backup-metrics.tmp"
jq \
    --argjson total_elapsed_ms "${total_elapsed_ms}" \
    --argjson tar_bytes "${tar_bytes}" \
    --argjson dump_bytes "${dump_bytes}" \
    --argjson bundle_bytes "${bundle_bytes}" \
    --argjson compression_level "${compression_level}" \
    '.backend_elapsed_ms = .elapsed_ms
     | del(.elapsed_ms)
     | .total_elapsed_ms = $total_elapsed_ms
     | .tar_bytes = $tar_bytes
     | .dump_bytes = $dump_bytes
     | .bundle_bytes = $bundle_bytes
     | .compression_level = $compression_level' \
    "${run_dir}/backup-metrics.json" > "${metrics_tmp}"
mv "${metrics_tmp}" "${run_dir}/backup-metrics.json"

# The compressed archive is the transferable benchmark artifact. Remove the
# uncompressed staging directory only after the archive and metrics have been
# written successfully to avoid retaining a second copy of the bundle.
rm -rf -- "${bundle_dir}"

echo "Backup benchmark completed: ${run_dir}"
