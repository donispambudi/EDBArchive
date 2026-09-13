#!/usr/bin/env bash

# Stop on errors, unset variables, and failures from either side of a pipeline.
set -Eeuo pipefail

echo "Wrapper started at: $(date '+%Y-%m-%d %H:%M:%S')"
echo

wrapper_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
bundle_path=""
restore_db_name=""
db_config_path=""
execution_times=()
total_time=0
error_type=""
error_code=""
error_stage=""
error_message=""
backend_exit_code=0

# Record a structured error before terminating the wrapper.
fail() {
    local exit_code="$1"
    error_type="$2"
    error_code="$3"
    error_stage="$4"
    error_message="$5"

    echo "$error_message" >&2
    exit "$exit_code"
}

for required_command in jq mariadb tar gzip mktemp; do
    if ! command -v "$required_command" >/dev/null 2>&1; then
        fail 69 "dependency_error" "COMMAND_NOT_FOUND" "startup" \
            "Required command is not installed: $required_command"
    fi
done

time_start_parsing=$(date +%s%N)
while [ "$#" -gt 0 ]; do
    case "$1" in
        --bundle)
            if [ "$#" -lt 2 ]; then
                echo "Missing value for --bundle" >&2
                exit 1
            fi

            bundle_path="$2"
            shift 2
            ;;

        --restore-db)
            if [ "$#" -lt 2 ]; then
                echo "Missing value for --restore-db" >&2
                exit 1
            fi

            restore_db_name="$2"
            shift 2
            ;;

        --db-config)
            if [ "$#" -lt 2 ]; then
                echo "Missing value for --db-config" >&2
                exit 1
            fi

            db_config_path="$2"
            shift 2
            ;;

        *)
            echo "Unknown argument: $1" >&2
            exit 1
            ;;
    esac
done

if [ -z "$bundle_path" ]; then
    echo "Missing --bundle" >&2
    exit 64
fi

if [ ! -f "$bundle_path" ]; then
    echo "Bundle file does not exist: $bundle_path" >&2
    exit 66
fi

if [ -z "$restore_db_name" ]; then
    echo "Missing --restore-db" >&2
    exit 64
fi

if [ -z "$db_config_path" ]; then
    echo "Missing --db-config" >&2
    exit 64
fi

if [ ! -f "$db_config_path" ]; then
    echo "DB config file does not exist: $db_config_path" >&2
    exit 66
fi

time_end_parsing=$(date +%s%N)
elapsed_ms_parsing=$(( (time_end_parsing - time_start_parsing) / 1000000 ))
execution_times+=("Parameter parsing = ${elapsed_ms_parsing} ms")
total_time=$((total_time + elapsed_ms_parsing))

# Load and validate the database configuration before extracting its values.
if ! jq -e '
    (.host | type == "string" and length > 0) and
    (.port | type == "number") and
    (.port >= 1 and .port <= 65535 and .port == (.port | floor)) and
    (.user | type == "string" and length > 0) and
    (.pass | type == "string")
' "$db_config_path" >/dev/null; then
    fail 65 "validation_error" "INVALID_DATABASE_CONFIG" "configuration" \
        "Database configuration is incomplete or invalid."
fi

db_host=$(jq -r '.host' "$db_config_path")
db_port=$(jq -r '.port' "$db_config_path")
db_user=$(jq -r '.user' "$db_config_path")
db_pass=$(jq -r '.pass' "$db_config_path")

# Print DB info
echo "Database connection info:"
echo "   ├── Host   = $db_host"
echo "   ├── Port   = $db_port"
echo "   ├── User   = $db_user"
echo "   └── DB     = $restore_db_name"
echo

echo "Checking target database:"
# Refuse to overwrite an existing restore database.
if ! database_names=$(mariadb --protocol=TCP \
    -h "$db_host" \
    -P "$db_port" \
    -u "$db_user" \
    --password="$db_pass" \
    --batch \
    --skip-column-names \
    -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA;"); then
    fail 69 "database_error" "DATABASE_EXISTENCE_CHECK_FAILED" "database_validation" \
        "Failed to check whether database '$restore_db_name' already exists."
fi

while IFS= read -r database_name; do
    if [ "$database_name" = "$restore_db_name" ]; then
        fail 65 "validation_error" "RESTORE_DATABASE_ALREADY_EXISTS" "database_validation" \
            "Restore database '$restore_db_name' already exists."
    fi
done <<< "$database_names"

echo "   └── Target database '$restore_db_name' does not exist, continue restore."
echo

# Create an isolated temporary directory for restoring the bundle.
if ! restore_temp_dir=$(mktemp -d "/tmp/temp-edb-$(date '+%Y%m%d%H%M%S')-XXXXXX"); then
    fail 73 "filesystem_error" "RESTORE_TEMP_DIRECTORY_CREATION_FAILED" "restore_preparation" \
        "Failed to create a temporary restore directory."
fi

echo "Temp directory created successfuly = $restore_temp_dir"
echo

cleanup_restore_temp_dir() {
    if [ -d "$restore_temp_dir" ]; then
        rm -rf -- "$restore_temp_dir"
    fi
}

trap cleanup_restore_temp_dir EXIT

# Decompress the bundle into a tar file inside the restore directory.
echo "Decompressing bundle:"
tar_file_path="$restore_temp_dir/bundle.tar"
time_start_decompress=$(date +%s%N)

if command -v pigz >/dev/null 2>&1; then
    decompressor=(pigz -dc)
else
    decompressor=(gzip -dc)
fi

if ! "${decompressor[@]}" "$bundle_path" > "$tar_file_path"; then
    fail 65 "archive_error" "BUNDLE_DECOMPRESSION_FAILED" "bundle_decompression" \
        "Failed to decompress bundle: $bundle_path"
fi

time_end_decompress=$(date +%s%N)
elapsed_ms_decompress=$(( (time_end_decompress - time_start_decompress) / 1000000 ))
execution_times+=("Decompress bundle = ${elapsed_ms_decompress} ms")
total_time=$((total_time + elapsed_ms_decompress))

echo "   └── Bundle decompressed successfully."
echo

# Extract the decompressed tar archive into the restore directory.
echo "Extracting bundle:"
time_start_untar=$(date +%s%N)

if ! tar -xf "$tar_file_path" -C "$restore_temp_dir"; then
    fail 65 "archive_error" "BUNDLE_EXTRACTION_FAILED" "bundle_extraction" \
        "Failed to extract bundle: $tar_file_path"
fi

time_end_untar=$(date +%s%N)
elapsed_ms_untar=$(( (time_end_untar - time_start_untar) / 1000000 ))
execution_times+=("Extract tar archive = ${elapsed_ms_untar} ms")
total_time=$((total_time + elapsed_ms_untar))

if ! rm -f -- "$tar_file_path"; then
    fail 74 "filesystem_error" "TEMP_TAR_CLEANUP_FAILED" "bundle_extraction" \
        "Failed to remove temporary tar file: $tar_file_path"
fi

echo "   └── Bundle extracted successfully."
echo

# Load and validate the FHE backend configuration from the extracted bundle.
bundle_config_path="$restore_temp_dir/config.json"

if [ ! -f "$bundle_config_path" ]; then
    fail 66 "archive_error" "BUNDLE_CONFIG_NOT_FOUND" "restore_preparation" \
        "Bundle configuration does not exist: $bundle_config_path"
fi

if ! jq -e '
    (.fhe | type == "object") and
    (.fhe.library_name | type == "string" and length > 0) and
    (.fhe.scheme_name | type == "string" and length > 0)
' "$bundle_config_path" >/dev/null; then
    fail 65 "validation_error" "INVALID_BUNDLE_CONFIG" "restore_preparation" \
        "Bundle configuration must contain non-empty fhe.library_name and fhe.scheme_name strings."
fi

library_name=$(jq -r '.fhe.library_name | ascii_downcase' "$bundle_config_path")
scheme_name=$(jq -r '.fhe.scheme_name | ascii_downcase' "$bundle_config_path")

if [[ ! "$library_name" =~ ^[[:alnum:]_-]+$ ||
      ! "$scheme_name" =~ ^[[:alnum:]_-]+$ ]]; then
    fail 65 "validation_error" "INVALID_FHE_BACKEND_NAME" "restore_preparation" \
        "FHE library and scheme names may only contain letters, numbers, underscores, and hyphens."
fi

# Create the target database and restore dump.sql into it.
dump_sql_path="$restore_temp_dir/dump.sql"

if [ ! -f "$dump_sql_path" ]; then
    fail 66 "archive_error" "DATABASE_DUMP_NOT_FOUND" "database_restore" \
        "Database dump does not exist in the bundle: $dump_sql_path"
fi

escaped_restore_db_name="${restore_db_name//\`/\`\`}"
quoted_restore_db_name="\`$escaped_restore_db_name\`"

drop_restore_database() {
    mariadb --protocol=TCP \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        --password="$db_pass" \
        -e "DROP DATABASE IF EXISTS $quoted_restore_db_name;" \
        >/dev/null 2>&1 || true
}

time_start_restore=$(date +%s%N)

echo "Restoring database dump:"

if ! mariadb --protocol=TCP \
    -h "$db_host" \
    -P "$db_port" \
    -u "$db_user" \
    --password="$db_pass" \
    -e "CREATE DATABASE $quoted_restore_db_name;"; then
    fail 69 "database_error" "RESTORE_DATABASE_CREATION_FAILED" "database_restore" \
        "Failed to create restore database '$restore_db_name'."
fi

echo "   ├── Target database '$restore_db_name' created successfully."

if ! mariadb --protocol=TCP \
    -h "$db_host" \
    -P "$db_port" \
    -u "$db_user" \
    --password="$db_pass" \
    "$restore_db_name" < "$dump_sql_path"; then
    drop_restore_database

    fail 69 "database_error" "DATABASE_RESTORE_FAILED" "database_restore" \
        "Failed to restore dump into database '$restore_db_name'."
fi

time_end_restore=$(date +%s%N)
elapsed_ms_restore=$(( (time_end_restore - time_start_restore) / 1000000 ))
execution_times+=("Create and restore database = ${elapsed_ms_restore} ms")
total_time=$((total_time + elapsed_ms_restore))

echo "   └── Database '$restore_db_name' restored successfully."
echo

# Run the selected backend and preserve its original exit code.
echo "[Execute FHE Backend]"
export RUN_FROM_WRAPPER=1
time_start_backend=$(date +%s%N)

backend_name="${library_name}_${scheme_name}"
backend_dir="${EDB_THIRD_PARTY_BIN_DIR:-$wrapper_dir/bin}"
backend_path="$backend_dir/$backend_name"

if [ ! -x "$backend_path" ]; then
    drop_restore_database
    fail 66 "backend_error" "BACKEND_EXECUTABLE_NOT_FOUND" "backend_execution" \
        "Backend executable does not exist or is not executable: $backend_path"
fi

if "$backend_path" "$restore_temp_dir" "$restore_db_name" "$db_config_path"; then
    backend_exit_code=0
else
    backend_exit_code=$?
fi
time_end_backend=$(date +%s%N)
elapsed_ms_backend=$(( (time_end_backend - time_start_backend) / 1000000 ))

if [ "$backend_exit_code" -ne 0 ]; then
    # A failed backend can leave a partially transformed database. Remove it so
    # that the same restore can be retried safely.
    drop_restore_database

    fail "$backend_exit_code" "backend_error" "BACKEND_FAILED" "backend_execution" \
        "FHE backend exited with status $backend_exit_code."
fi

execution_times+=("FHE backend execution = ${elapsed_ms_backend} ms")
total_time=$((total_time + elapsed_ms_backend))
echo "[End of FHE backend]"
echo

# Print the human-readable timing summary and write_result handles structured output.
execution_times+=("Total execution time = ${total_time} ms")
echo "Execution times:"
for item in "${execution_times[@]}"; do
    echo "   ├── $item"
done
echo "   └── End of execution time summary"
echo
