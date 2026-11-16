#!/usr/bin/env bash

set -euo pipefail

command_name="${1:-}"
if [[ -n "${command_name}" ]]; then
    shift
fi

case "${command_name}" in
    backup)
        exec run-backup.sh "$@"
        ;;
    restore)
        exec run-restore.sh "$@"
        ;;
    scenarios)
        exec run-scenarios.sh "$@"
        ;;
    quick)
        exec run-scenarios.sh --scenario district "$@"
        ;;
    *)
        cat >&2 <<'EOF'
Usage:
  benchmark.sh backup  [options]
  benchmark.sh restore [options]
  benchmark.sh scenarios [options]
  benchmark.sh quick [options]

Run either command with --help for its options.
EOF
        exit 2
        ;;
esac
