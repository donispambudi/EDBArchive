#!/usr/bin/env bash

set -Eeuo pipefail

gzip -dc /seed/tpcc.sql.gz \
    | mariadb \
        --protocol=socket \
        --user="${MARIADB_USER}" \
        --password="${MARIADB_PASSWORD}" \
        tpcc
