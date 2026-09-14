---
layout: default
title: Third-party Restoration Guide
---

# Third-party Restoration Guide

This guide restores an EDBArchive bundle into a new MariaDB database and
reconstructs the encrypted columns for homomorphic processing. The
third-party component is a standalone command-line operation that
is not continuously running and is not started by `docker compose up`.

## Prerequisites

Before starting, ensure that:

- EDBArchive has been installed as described in
  [Installation and Setup](installation.md).
- the `third-party` image has been built.
- a generated `bundle.tar.gz` is available on the host.
- the target MariaDB server is running and reachable from the container.
- the database user can create, modify, and drop databases.

Run the commands in this guide from the repository root, where `compose.yaml` is located.

## Build the third-party image

If the image was not built during installation, build it with:

```bash
docker compose build third-party
```

The build compiles the third-party OpenFHE backend and installs it as
`/app/third-party/bin/openfhe_bfv` inside the image.

## Prepare the database configuration

Create a file named `db.json` outside the repository. To restore into the
MariaDB service provided by EDBArchive, use:

```json
{
  "host": "mariadb",
  "port": 3306,
  "user": "edbarchive",
  "pass": "edbarchive"
}
```

Here, `mariadb` is the Compose service name and `3306` is its port inside the
Compose network. Do not use the host-facing port `3307` for this configuration.

The `db.json` file contains database credentials and must not be committed to
the repository. It is different from the `config.json` stored inside the
bundle: `db.json` describes the target database connection, whereas
`config.json` describes the bundle and its FHE configuration.

## Start the target database

Start MariaDB if the EDBArchive stack is not already running:

```bash
docker compose up -d mariadb
```

Confirm that it is healthy:

```bash
docker compose ps mariadb
```

## Restore a bundle

Run the third-party container with the absolute paths of the bundle and
database configuration file:

```bash
docker compose run --rm \
  -v /absolute/path/to/bundle.tar.gz:/input/bundle.tar.gz:ro \
  -v /absolute/path/to/db.json:/input/db.json:ro \
  third-party \
  --bundle /input/bundle.tar.gz \
  --restore-db tpcc_restored \
  --db-config /input/db.json
```

Replace both `/absolute/path/to/...` values with files on the host. For
example, if both files are in `/home/alice/Downloads`, use
`/home/alice/Downloads/bundle.tar.gz` and
`/home/alice/Downloads/db.json`.

Each `-v` option uses the format
`host-file:container-file:access-mode`. The `ro` suffix mounts the input file
as read-only. Quote the complete volume argument if its host path contains
spaces.

The value passed to `--restore-db` is the name of the database that will be
created. It must not already exist. Use a different name for each independent
restoration, or remove an earlier test database before repeating the command.

## Restoration workflow

The command performs the following operations:

1. validates its arguments and the structure of `db.json`.
2. confirms that the requested target database does not exist.
3. extracts the bundle into an isolated temporary directory.
4. validates the FHE library and scheme recorded in `config.json`.
5. creates the target database and imports `dump.sql`.
6. selects and runs the corresponding third-party FHE backend.
7. reconstructs the encrypted database columns from the bundled ciphertexts.
8. removes the temporary extraction directory.

If importing the dump or running the FHE backend fails, the wrapper removes
the partially restored database so that the operation can be retried safely.

## Verify the restored database

List the available databases:

```bash
docker compose exec mariadb mariadb \
  -u edbarchive -pedbarchive \
  -e "SHOW DATABASES;"
```

List the tables in the restored database:

```bash
docker compose exec mariadb mariadb \
  -u edbarchive -pedbarchive \
  tpcc_restored \
  -e "SHOW TABLES;"
```

Replace `tpcc_restored` if a different value was supplied to `--restore-db`.

## Restore into another MariaDB server

The third-party image can also restore into a MariaDB server outside the
EDBArchive Compose stack. Set `host` and `port` in `db.json` to an address that
is reachable from inside the container, and use credentials that can create
and alter the target database.

The address `localhost` inside `db.json` refers to the third-party container,
not to the Docker host. Use the appropriate Docker host address or a reachable
network hostname when MariaDB runs elsewhere.

## Bundle security boundary

The bundle does not contain the public or private key. The third party can
restore the encrypted data and perform the supported homomorphic operations
using the included context and evaluation material, but cannot decrypt the
protected values or encrypt new plaintext values using the bundle alone.

## Navigation

- Previous: [Bundle Generation](data-provider/bundle-generation.md)
- Next: [Paper Reproducibility](reproducibility.md)
- [Documentation index](index.md)
