#!/usr/bin/env bash

# Preserve failures from either side of shell pipelines.
set -o pipefail

# Shared execution state used to build the final result.json.
wrapper_started_at=$(date --iso-8601=seconds)
wrapper_started_ns=$(date +%s%N)
wrapper_dir=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
project_dir=$(cd -- "$wrapper_dir/.." && pwd)
result_enabled=0
result_path=""
result_temp_path=""
job_type=""
error_type=""
error_code=""
error_stage=""
error_message=""
backend_exit_code=0
artifacts_json="[]"
elapsed_ms_parsing=null
elapsed_ms_duplicatedb=null
elapsed_ms_purge=null
elapsed_ms_backend=null
elapsed_ms_dump=null
elapsed_ms_tar=null
elapsed_ms_compress=null

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

# Quote MariaDB identifiers while preserving spaces and reserved words.
quote_identifier() {
    local escaped_identifier="${1//\`/\`\`}"
    printf '`%s`' "$escaped_identifier"
}

# Finalize result.json for both successful and failed executions.
write_result() {
    local process_exit_code="$?"

    trap - EXIT

    if [ "$result_enabled" -ne 1 ]; then
        exit "$process_exit_code"
    fi

    local wrapper_finished_at
    local wrapper_finished_ns
    local duration_ms
    local status
    local ok

    wrapper_finished_at=$(date --iso-8601=seconds)
    wrapper_finished_ns=$(date +%s%N)
    duration_ms=$(( (wrapper_finished_ns - wrapper_started_ns) / 1000000 ))

    if [ "$process_exit_code" -eq 0 ]; then
        status="completed"
        ok=true
    else
        status="failed"
        ok=false

        if [ -z "$error_code" ]; then
            error_type="wrapper_error"
            error_code="UNEXPECTED_WRAPPER_FAILURE"
            error_stage="wrapper"
            error_message="The wrapper exited unexpectedly."
        fi
    fi

    if ! jq -n \
        --argjson schema_version 1 \
        --argjson ok "$ok" \
        --arg status "$status" \
        --argjson job_id "$job_id" \
        --arg job_type "$job_type" \
        --argjson exit_code "$process_exit_code" \
        --arg started_at "$wrapper_started_at" \
        --arg finished_at "$wrapper_finished_at" \
        --argjson duration_ms "$duration_ms" \
        --argjson parameter_parsing "$elapsed_ms_parsing" \
        --argjson database_preparation "$elapsed_ms_duplicatedb" \
        --argjson database_purge "$elapsed_ms_purge" \
        --argjson backend_execution "$elapsed_ms_backend" \
        --argjson database_dump "$elapsed_ms_dump" \
        --argjson archive_creation "$elapsed_ms_tar" \
        --argjson compression "$elapsed_ms_compress" \
        --argjson artifacts "$artifacts_json" \
        --arg error_type "$error_type" \
        --arg error_code "$error_code" \
        --arg error_stage "$error_stage" \
        --arg error_message "$error_message" \
        --argjson backend_exit_code "$backend_exit_code" \
        '{
            schema_version: $schema_version,
            ok: $ok,
            status: $status,
            job_id: $job_id,
            job_type: $job_type,
            exit_code: $exit_code,
            started_at: $started_at,
            finished_at: $finished_at,
            duration_ms: $duration_ms,
            timings_ms: {
                parameter_parsing: $parameter_parsing,
                database_preparation: $database_preparation,
                database_purge: $database_purge,
                backend_execution: $backend_execution,
                database_dump: $database_dump,
                archive_creation: $archive_creation,
                compression: $compression
            },
            artifacts: $artifacts,
            error: (
                if $ok then null
                else {
                    type: $error_type,
                    code: $error_code,
                    stage: $error_stage,
                    message: $error_message,
                    backend_exit_code: (
                        if $backend_exit_code == 0 then null
                        else $backend_exit_code
                        end
                    )
                }
                end
            )
        }' > "$result_temp_path"
    then
        echo "Failed to write result file: $result_temp_path" >&2

        if [ "$process_exit_code" -eq 0 ]; then
            process_exit_code=74
        fi
    elif ! mv -f "$result_temp_path" "$result_path"; then
        echo "Failed to finalize result file: $result_path" >&2

        if [ "$process_exit_code" -eq 0 ]; then
            process_exit_code=74
        fi
    fi

    exit "$process_exit_code"
}

echo "Wrapper started at: $(date '+%Y-%m-%d %H:%M:%S')"
echo

# Parse and validate the wrapper command-line arguments.
execution_times=()

workspace_path=""
job_id=""
db_config_path=""
total_time=0

time_start_parsing=$(date +%s%N)
while [ "$#" -gt 0 ]; do
    case "$1" in
        --workspace)
            if [ "$#" -lt 2 ]; then
                echo "Missing value for --workspace" >&2
                exit 1
            fi

            workspace_path="$2"
            shift 2
            ;;

        --id)
            if [ "$#" -lt 2 ]; then
                echo "Missing value for --id" >&2
                exit 1
            fi

            job_id="$2"
            shift 2
            ;;

        --db)
            if [ "$#" -lt 2 ]; then
                echo "Missing value for --db" >&2
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

if [ -z "$workspace_path" ]; then
    echo "Missing --workspace" >&2
    exit 1
fi

if [ -z "$job_id" ]; then
    echo "Missing --id" >&2
    exit 1
fi

if [[ ! "$job_id" =~ ^[1-9][0-9]*$ ]]; then
    echo "Invalid --id: expected a positive integer" >&2
    exit 64
fi

if [ ! -d "$workspace_path" ]; then
    echo "Workspace does not exist or is not a directory: $workspace_path" >&2
    exit 1
fi

job_dir="$workspace_path/fhe-jobs/$job_id"

if ! mkdir -p "$job_dir"; then
    echo "Failed to create job directory: $job_dir" >&2
    exit 73
fi

result_path="$job_dir/result.json"
result_temp_path="${result_path}.tmp.$$"

if ! rm -f "$result_path" "$result_temp_path"; then
    echo "Failed to clear stale result files for job $job_id" >&2
    exit 74
fi

result_enabled=1
trap write_result EXIT

# Validate files required before job processing can begin.
if [ -z "$db_config_path" ]; then
    fail 64 "validation_error" "DB_CONFIG_ARGUMENT_MISSING" "parameter_parsing" \
        "Missing --db"
fi

if [ ! -f "$db_config_path" ]; then
    fail 66 "validation_error" "DB_CONFIG_NOT_FOUND" "parameter_parsing" \
        "DB config file does not exist: $db_config_path"
fi

time_end_parsing=$(date +%s%N)
elapsed_ms_parsing=$(( (time_end_parsing - time_start_parsing) / 1000000 ))
execution_times+=("Parameter parsing = ${elapsed_ms_parsing} ms")
((total_time += elapsed_ms_parsing))

echo "Output directories check:"

# Recreate clean public and private output directories for this job.
public_dir="$workspace_path/public/$job_id"
private_dir="$workspace_path/private/$job_id"

if ! mkdir -p "$public_dir" "$private_dir"; then
    fail 73 "filesystem_error" "OUTPUT_DIRECTORY_CREATION_FAILED" "directory_setup" \
        "Failed to create output directories."
fi

if ! find "$public_dir" "$private_dir" -mindepth 1 -maxdepth 1 -exec rm -rf {} +; then
    fail 74 "filesystem_error" "OUTPUT_DIRECTORY_CLEANUP_FAILED" "directory_setup" \
        "Failed to clean output directories."
fi

echo "   └── All output directories checked"
echo

job_config_path="$job_dir/config.json"

# Load the job type and resolve which FHE backend implementation to use.
if [ ! -f "$job_config_path" ]; then
    fail 66 "validation_error" "JOB_CONFIG_NOT_FOUND" "configuration" \
        "Job config file does not exist: $job_config_path"
fi

if ! job_type=$(jq -er '.job_type | strings | select(length > 0)' "$job_config_path"); then
    fail 65 "validation_error" "INVALID_JOB_CONFIG" "configuration" \
        "Job config must contain a non-empty job_type string."
fi

library_name=""
scheme_name=""

if [[ "$job_type" == "create-context" || "$job_type" == "create-keypair" ]]; then
    library_name=$(jq -r '.input.library_name? // empty | ascii_downcase' "$job_config_path")
    scheme_name=$(jq -r '.input.scheme_name? // empty | ascii_downcase' "$job_config_path")
fi

if [ "$job_type" = "backup" ]; then
    # Prepare the public restore metadata required by a backup artifact.
    library_name=$(jq -r '
        .input.items[]
        | select(.library_name? != null and .library_name != "")
        | .library_name
        | ascii_downcase
    ' "$job_config_path" | head -n 1)

    scheme_name=$(jq -r '
        .input.items[]
        | select(.scheme_name? != null and .scheme_name != "")
        | .scheme_name
        | ascii_downcase
    ' "$job_config_path" | head -n 1)

    echo "Copying context file"
    context_file_ref=$(jq -r '
        .input.items[]
        | select(.context_file_ref? != null and .context_file_ref != "")
        | .context_file_ref
    ' "$job_config_path" | head -n 1)

    if [[ -n "$context_file_ref" ]]; then
        context_path="$workspace_path/$context_file_ref"

        if ! cp "$context_path" "$public_dir"; then
            fail 74 "filesystem_error" "CONTEXT_COPY_FAILED" "context_copy" \
                "Failed to copy context file."
        fi

        echo "   └── Context file copied successfully to public directory"
        echo
    else
        fail 65 "validation_error" "CONTEXT_REFERENCE_NOT_FOUND" "context_copy" \
            "Context file reference not found."
    fi

    echo "Create restoration config file"
    restore_config_path="$public_dir/config.json"

    # Store the FHE backend identity and grouped table metadata for automatic restore.
    if ! jq \
        --arg library_name "$library_name" \
        --arg scheme_name "$scheme_name" '
        {
            fhe: {
                library_name: $library_name,
                scheme_name: $scheme_name
            },
            tables: (
                .input.items
                | group_by(.table_name)
                | map(
                    {
                        name: .[0].table_name,
                        primary_keys: [
                            .[]
                            | select(.pk == true)
                            | .column_name
                        ],
                        encrypted_columns: [
                            .[]
                            | select(.encryption == "fhe-secure")
                            | .column_name
                        ]
                    }
                )
                | map(select(.encrypted_columns | length > 0))
            )
        }
    ' "$job_config_path" > "$restore_config_path"; then
        fail 65 "validation_error" "RESTORE_CONFIG_CREATION_FAILED" "configuration" \
            "Failed to create restoration config file."
    fi
    echo "   └── Restore config created successfully"
    echo

    # Load the shared DB credentials and target database name.
    db_host=$(jq -r '.host' "$db_config_path")
    db_port=$(jq -r '.port' "$db_config_path")
    db_user=$(jq -r '.user' "$db_config_path")
    db_pass=$(jq -r '.pass' "$db_config_path")
    db_dbname=$(jq -r '.input.database_name' "$job_config_path")

    if [[ -z "$db_host" || "$db_host" == "null" ||
          ! "$db_port" =~ ^[0-9]+$ ||
          -z "$db_user" || "$db_user" == "null" ||
          "$db_pass" == "null" ||
          -z "$db_dbname" || "$db_dbname" == "null" ]]; then
        fail 65 "validation_error" "INVALID_DATABASE_CONFIG" "configuration" \
            "Database configuration is incomplete or invalid."
    fi

    # use _edb_<job_id> as temporary duplicated database name
    db_name_enc="${db_dbname}_edb_${job_id}"
    quoted_db_name_enc=$(quote_identifier "$db_name_enc")

    # Print DB info
    echo "Database connection info:"
    echo "   ├── Host   = $db_host"
    echo "   ├── Port   = $db_port"
    echo "   ├── User   = $db_user"
    echo "   ├── DB     = $db_dbname"
    echo "   └── DB Enc = $db_name_enc"
    echo

    time_start_duplicatedb=$(date +%s%N)
    # Recreate and populate the temporary database used by the FHE backend.
    if ! mariadb --protocol=TCP \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        --password="$db_pass" \
        -e "DROP DATABASE IF EXISTS $quoted_db_name_enc; CREATE DATABASE $quoted_db_name_enc;"; then
        fail 69 "database_error" "DATABASE_RECREATE_FAILED" "database_preparation" \
            "Failed to recreate database '$db_name_enc'."
    fi

    echo "Duplicate database"

    if ! mariadb-dump --protocol=TCP \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        --password="$db_pass" \
        --single-transaction \
        --quick \
        "$db_dbname" \
    | mariadb --protocol=TCP \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        --password="$db_pass" \
        "$db_name_enc"; then
        fail 69 "database_error" "DATABASE_DUPLICATION_FAILED" "database_preparation" \
            "Failed to duplicate database '$db_dbname'."
    fi

    time_end_duplicatedb=$(date +%s%N)
    elapsed_ms_duplicatedb=$(( (time_end_duplicatedb - time_start_duplicatedb) / 1000000 ))
    execution_times+=("DB preparation = ${elapsed_ms_duplicatedb} ms")
    ((total_time += elapsed_ms_duplicatedb))

    echo "   └── Database duplicated successfully"
    echo

    # Purge tables and columns that are not listed in input.items.
    echo "Purging unselected tables and columns"
    time_start_purge=$(date +%s%N)

    if ! selected_items_tsv=$(jq -er '
        .input.items
        | if type != "array" or length == 0 then
            error("input.items must be a non-empty array")
          else
            .[]
            | if (.table_name | type) != "string"
                 or (.column_name | type) != "string"
                 or .table_name == ""
                 or .column_name == "" then
                error("every item must contain table_name and column_name")
              else
                [.table_name, .column_name] | @tsv
              end
          end
    ' "$job_config_path"); then
        fail 65 "validation_error" "INVALID_SHARE_ITEMS" "database_purge" \
            "input.items must contain valid table_name and column_name values."
    fi

    declare -A selected_tables=()
    declare -A selected_columns=()

    # Use a non-printable separator so names containing spaces remain intact.
    identifier_separator=$'\x1f'

    while IFS=$'\t' read -r selected_table selected_column; do
        selected_column_key="$selected_table$identifier_separator$selected_column"
        selected_tables["$selected_table"]=1
        selected_columns["$selected_column_key"]=1
    done <<< "$selected_items_tsv"

    # Read the actual schema before deciding which objects must be removed.
    if ! schema_tsv=$(mariadb --protocol=TCP \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        --password="$db_pass" \
        --batch \
        --skip-column-names \
        "$db_name_enc" \
        -e "
            SELECT c.TABLE_NAME, c.COLUMN_NAME
            FROM information_schema.COLUMNS AS c
            INNER JOIN information_schema.TABLES AS t
                ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
               AND t.TABLE_NAME = c.TABLE_NAME
            WHERE c.TABLE_SCHEMA = DATABASE()
              AND t.TABLE_TYPE = 'BASE TABLE'
            ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;
        "); then
        fail 69 "database_error" "DATABASE_SCHEMA_READ_FAILED" "database_purge" \
            "Failed to read the duplicated database schema."
    fi

    declare -A actual_tables=()
    declare -A actual_columns=()

    while IFS=$'\t' read -r actual_table actual_column; do
        [ -z "$actual_table" ] && continue

        actual_column_key="$actual_table$identifier_separator$actual_column"
        actual_tables["$actual_table"]=1
        actual_columns["$actual_column_key"]=1
    done <<< "$schema_tsv"

    # Refuse to purge when the selection references missing schema objects.
    for selected_table in "${!selected_tables[@]}"; do
        if [[ -z "${actual_tables[$selected_table]+present}" ]]; then
            fail 65 "validation_error" "SELECTED_TABLE_NOT_FOUND" "database_purge" \
                "Selected table does not exist: $selected_table"
        fi
    done

    for selected_item in "${!selected_columns[@]}"; do
        if [[ -z "${actual_columns[$selected_item]+present}" ]]; then
            fail 65 "validation_error" "SELECTED_COLUMN_NOT_FOUND" "database_purge" \
                "Selected table and column do not exist: $selected_item"
        fi
    done

    # Find foreign keys affected by tables or columns that will be removed.
    if ! foreign_keys_tsv=$(mariadb --protocol=TCP \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        --password="$db_pass" \
        --batch \
        --skip-column-names \
        "$db_name_enc" \
        -e "
            SELECT
                TABLE_NAME,
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE CONSTRAINT_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL;
        "); then
        fail 69 "database_error" "FOREIGN_KEY_READ_FAILED" "database_purge" \
            "Failed to read foreign-key constraints."
    fi

    # Build one SQL batch so foreign-key settings remain in the same session.
    purge_sql="SET FOREIGN_KEY_CHECKS = 0;"
    declare -A foreign_keys_to_drop=()

    while IFS=$'\t' read -r fk_table fk_name fk_column referenced_table referenced_column; do
        [ -z "$fk_table" ] && continue

        fk_column_key="$fk_table$identifier_separator$fk_column"
        referenced_column_key="$referenced_table$identifier_separator$referenced_column"
        foreign_key_key="$fk_table$identifier_separator$fk_name"

        if [[ -z "${selected_tables[$fk_table]+selected}" ||
              -z "${selected_tables[$referenced_table]+selected}" ||
              -z "${selected_columns[$fk_column_key]+selected}" ||
              -z "${selected_columns[$referenced_column_key]+selected}" ]]; then
            foreign_keys_to_drop["$foreign_key_key"]=1
        fi
    done <<< "$foreign_keys_tsv"

    for foreign_key in "${!foreign_keys_to_drop[@]}"; do
        fk_table="${foreign_key%%$identifier_separator*}"
        fk_name="${foreign_key#*$identifier_separator}"
        quoted_fk_table=$(quote_identifier "$fk_table")
        quoted_fk_name=$(quote_identifier "$fk_name")
        purge_sql+=$'\n'"ALTER TABLE $quoted_fk_table DROP FOREIGN KEY $quoted_fk_name;"
    done

    for actual_table in "${!actual_tables[@]}"; do
        quoted_table=$(quote_identifier "$actual_table")

        if [[ -z "${selected_tables[$actual_table]+selected}" ]]; then
            purge_sql+=$'\n'"DROP TABLE $quoted_table;"
            continue
        fi

        while IFS=$'\t' read -r schema_table schema_column; do
            schema_column_key="$schema_table$identifier_separator$schema_column"

            if [[ "$schema_table" != "$actual_table" ||
                  -n "${selected_columns[$schema_column_key]+selected}" ]]; then
                continue
            fi

            quoted_column=$(quote_identifier "$schema_column")
            purge_sql+=$'\n'"ALTER TABLE $quoted_table DROP COLUMN $quoted_column;"
        done <<< "$schema_tsv"
    done

    purge_sql+=$'\nSET FOREIGN_KEY_CHECKS = 1;'

    # Apply the generated purge plan to the duplicated database only.
    if ! printf '%s\n' "$purge_sql" | mariadb --protocol=TCP \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        --password="$db_pass" \
        "$db_name_enc"; then
        fail 69 "database_error" "DATABASE_PURGE_FAILED" "database_purge" \
            "Failed to purge unselected tables or columns."
    fi

    time_end_purge=$(date +%s%N)
    elapsed_ms_purge=$(( (time_end_purge - time_start_purge) / 1000000 ))
    execution_times+=("Database purge = ${elapsed_ms_purge} ms")
    ((total_time += elapsed_ms_purge))

    echo "   └── Unselected tables and columns purged successfully"
    echo
fi

# Reject unsupported jobs before resolving or invoking an executable.
if [[ "$job_type" != "create-context" &&
      "$job_type" != "create-keypair" &&
      "$job_type" != "backup" ]]; then
    fail 65 "validation_error" "UNSUPPORTED_JOB_TYPE" "configuration" \
        "Unsupported job type: $job_type"
fi

if [[ -z "$library_name" || -z "$scheme_name" ]]; then
    fail 65 "validation_error" "BACKEND_NOT_CONFIGURED" "configuration" \
        "The job config does not define a usable library and scheme."
fi

# Run the selected backend and preserve its original exit code.
echo "[Execute FHE Backend]"
export RUN_FROM_WRAPPER=1
time_start_backend=$(date +%s%N)

backend_name="${library_name}_${scheme_name}"
backend_path="$project_dir/bin/${backend_name}"

if [ ! -x "$backend_path" ]; then
    fail 66 "backend_error" "BACKEND_EXECUTABLE_NOT_FOUND" "backend_execution" \
        "Backend executable does not exist or is not executable: $backend_path"
fi

"$backend_path" "$workspace_path" "$job_id" "$db_config_path"
backend_exit_code=$?
time_end_backend=$(date +%s%N)
elapsed_ms_backend=$(( (time_end_backend - time_start_backend) / 1000000 ))

if [ "$backend_exit_code" -ne 0 ]; then
    fail "$backend_exit_code" "backend_error" "BACKEND_FAILED" "backend_execution" \
        "FHE backend exited with status $backend_exit_code."
fi

execution_times+=("FHE backend execution = ${elapsed_ms_backend} ms")
((total_time += elapsed_ms_backend))
echo "[End of FHE backend]"
echo

# Describe artifacts produced directly by context and keypair jobs.
if [ "$job_type" = "create-context" ]; then
    context_artifact="$private_dir/context.bin"

    if ! context_size=$(stat -c%s "$context_artifact"); then
        fail 74 "filesystem_error" "CONTEXT_ARTIFACT_NOT_FOUND" "artifact_collection" \
            "The backend completed without producing context.bin."
    fi

    if ! artifacts_json=$(jq -n \
        --arg path "private/$job_id/context.bin" \
        --argjson size "$context_size" \
        '[{type: "fhe_context", path: $path, size_bytes: $size}]'); then
        fail 70 "wrapper_error" "ARTIFACT_METADATA_FAILED" "artifact_collection" \
            "Failed to build context artifact metadata."
    fi
elif [ "$job_type" = "create-keypair" ]; then
    private_key_artifact="$private_dir/private.bin"
    public_key_artifact="$private_dir/public.bin"

    if ! private_key_size=$(stat -c%s "$private_key_artifact") ||
       ! public_key_size=$(stat -c%s "$public_key_artifact"); then
        fail 74 "filesystem_error" "KEYPAIR_ARTIFACT_NOT_FOUND" "artifact_collection" \
            "The backend completed without producing both keypair files."
    fi

    if ! artifacts_json=$(jq -n \
        --arg private_path "private/$job_id/private.bin" \
        --argjson private_size "$private_key_size" \
        --arg public_path "private/$job_id/public.bin" \
        --argjson public_size "$public_key_size" \
        '[
            {type: "fhe_private_key", path: $private_path, size_bytes: $private_size},
            {type: "fhe_public_key", path: $public_path, size_bytes: $public_size}
        ]'); then
        fail 70 "wrapper_error" "ARTIFACT_METADATA_FAILED" "artifact_collection" \
            "Failed to build keypair artifact metadata."
    fi
fi

if [ "$job_type" = "backup" ]; then
    # Export the encrypted temporary database into the public artifact directory.
    dump_db_path="$public_dir/dump.sql"
    echo "Dumping the duplicated database to $dump_db_path"
    time_start_dump=$(date +%s%N)

    if ! mariadb-dump --protocol=TCP \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        --password="$db_pass" \
        --single-transaction \
        --compact \
        "$db_name_enc" \
        > "$dump_db_path"; then
        fail 69 "database_error" "DATABASE_DUMP_FAILED" "database_dump" \
            "Database dump failed."
    fi

    time_end_dump=$(date +%s%N)
    elapsed_ms_dump=$(( (time_end_dump - time_start_dump) / 1000000 ))
    execution_times+=("Dumping the duplicated database = ${elapsed_ms_dump} ms")
    ((total_time += elapsed_ms_dump))

    echo "   └── Database dump completed successfully"
    echo

    # Package the public files into an uncompressed archive.
    echo "Creating bundle.tar"
    tar_file_path="$public_dir/bundle.tar"
    time_start_tar=$(date +%s%N)

    if ! tar -cf "$tar_file_path" --exclude=bundle.tar -C "$public_dir" .; then
        fail 74 "filesystem_error" "ARCHIVE_CREATION_FAILED" "archive_creation" \
            "Failed to create bundle.tar."
    fi

    time_end_tar=$(date +%s%N)
    elapsed_ms_tar=$(( (time_end_tar - time_start_tar) / 1000000 ))
    execution_times+=("Create tar archive = ${elapsed_ms_tar} ms")
    ((total_time += elapsed_ms_tar))
    echo "   └── Tar archive created successfully"
    echo

    if ! tar_size=$(stat -c%s "$tar_file_path") ||
       ! dump_size=$(stat -c%s "$dump_db_path"); then
        fail 74 "filesystem_error" "ARTIFACT_STAT_FAILED" "archive_creation" \
            "Failed to read archive or dump size."
    fi

    # Compress the archive and record its artifact metadata.
    echo "Compressing bundle.tar"
    gz_file_path="${tar_file_path}.gz"
    time_start_compress=$(date +%s%N)
    if [ "$dump_size" -gt $((tar_size / 2)) ]; then
        pigz_level=8
    else
        pigz_level=1
    fi
    echo "   ├── Tar size = $tar_size bytes"
    echo "   ├── Dump size = $dump_size bytes"
    echo "   ├── Using pigz level = $pigz_level"

    if ! pigz -f -"$pigz_level" "$tar_file_path"; then
        fail 74 "filesystem_error" "ARCHIVE_COMPRESSION_FAILED" "compression" \
            "Failed to compress bundle.tar."
    fi

    time_end_compress=$(date +%s%N)
    elapsed_ms_compress=$(( (time_end_compress - time_start_compress) / 1000000 ))
    execution_times+=("Compressing bundle = ${elapsed_ms_compress} ms")
    ((total_time += elapsed_ms_compress))

    if ! bundle_size=$(stat -c%s "$gz_file_path"); then
        fail 74 "filesystem_error" "COMPRESSED_ARTIFACT_STAT_FAILED" "compression" \
            "Failed to read compressed bundle size."
    fi

    if ! artifacts_json=$(jq -n \
        --arg path "public/$job_id/bundle.tar.gz" \
        --argjson size "$bundle_size" \
        '[{type: "backup_bundle", path: $path, size_bytes: $size}]'); then
        fail 70 "wrapper_error" "ARTIFACT_METADATA_FAILED" "artifact_collection" \
            "Failed to build backup artifact metadata."
    fi

    echo "   ├── Compressed bundle = $bundle_size bytes" 
    echo "   └── Bundle compressed successfully"
    echo

    # Keep only the final bundle and remove the temporary duplicated database.
    echo "Cleaning public directory"
    if ! find "$public_dir" -mindepth 1 -maxdepth 1 ! -name "bundle.tar.gz" -exec rm -rf {} +; then
        fail 74 "filesystem_error" "PUBLIC_DIRECTORY_CLEANUP_FAILED" "cleanup" \
            "Failed to clean the public directory."
    fi
    echo "   └── Public directory cleaned successfully"
    echo

    echo "Cleaning duplicated database = $db_name_enc"
    mariadb --protocol=TCP \
    -h "$db_host" \
    -P "$db_port" \
    -u "$db_user" \
    --password="$db_pass" \
    -e "DROP DATABASE IF EXISTS $quoted_db_name_enc;"

    if [ $? -eq 0 ]; then
        echo "   └── Database '$db_name_enc' has been removed."
    else
        fail 69 "database_error" "TEMP_DATABASE_CLEANUP_FAILED" "cleanup" \
            "Failed to remove database '$db_name_enc'."
    fi
    echo
fi

# Print the human-readable timing summary and write_result handles structured output.
execution_times+=("Total execution time = ${total_time} ms")
echo "Execution times:"
for item in "${execution_times[@]}"; do
    echo "   ├── $item"
done
echo "   └── End of execution time summary"
echo
