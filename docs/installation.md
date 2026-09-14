---
layout: default
title: Installation and Setup
---

# Installation and Setup

This guide prepares and starts the complete EDBArchive data-provider stack. It
also builds the optional third-party image so that it is ready for the
restoration workflow.

## Prerequisites

Ensure that the following tools and resources are available:

- Git
- Docker Engine or Docker Desktop capable of running Linux containers on amd64
  or ARM64
- Docker Compose v2 (`docker compose`)
- Sufficient disk space and memory to compile OpenFHE and build the images

Confirm that Docker is running:

```bash
docker info
docker compose version
```

## Clone the repository

If GitHub SSH access is configured, clone the repository and all of its
submodules with:

```bash
git clone --recurse-submodules git@github.com:donispambudi/EDBArchive.git
cd EDBArchive
```

OpenFHE is stored as a Git submodule and must be initialized before building
the Docker images. Do not run `git pull` directly inside
`deps/openfhe-development`; the submodule must remain at the commit recorded by
EDBArchive.

### Clone without GitHub SSH access

If GitHub SSH access is unavailable, clone the main repository over HTTPS and
set a local HTTPS URL for the OpenFHE submodule:

```bash
git clone https://github.com/donispambudi/EDBArchive.git
cd EDBArchive
git submodule sync --recursive
git config submodule.deps/openfhe-development.url https://github.com/openfheorg/openfhe-development.git
git submodule update --init --recursive
```

If the repository has already been cloned and the submodule URL is usable,
initialize any missing submodules from the repository root:

```bash
git submodule sync --recursive
git submodule update --init --recursive
```

From this point forward, run all commands from the cloned `EDBArchive`
repository root. This is the directory containing `compose.yaml`.

## Prepare the environment files

Create the root Compose environment file:

```bash
cp .env.example .env
```

The default reproducibility configuration includes:

```dotenv
EDB_WEB_PORT=8000
EDB_DB_HOST_PORT=3307
EDB_WORKER_PORT=9100
EDB_WORKER_POLL_INTERVAL=60
EDB_DB_NAME=EDBArchive
EDB_DB_PASSWORD=edbarchive
```

Create the Laravel environment file:

```bash
cp web/.env.example web/.env
```

Compose injects the database connection and worker address into the web
container. Therefore, the Docker database values do not need to be duplicated
in `web/.env`.

The provided credentials and application key are intended only for the paper
reproducibility environment. Do not use them for a production deployment.

## Validate the Compose configuration

Check the fully interpolated configuration before building:

```bash
docker compose config
```

The command should complete without reporting missing environment variables or
invalid Compose syntax.

## Build the images

Build the data-provider images and the optional third-party image:

```bash
docker compose --profile third-party --progress plain build --pull
```

This command builds:

- the Laravel web image;
- the Python job-queue image and OpenFHE backend;
- the standalone third-party restoration image.

The MariaDB image is pulled from the container registry. OpenFHE is compiled
during the job-queue and third-party image build. On ARM64 hosts, Docker
automatically selects the ARM64 MariaDB Connector/C++ package.

For a clean reproducibility test that does not reuse Docker build cache:

```bash
docker compose --profile third-party --progress plain build --no-cache --pull
```

A cache-free OpenFHE build may take a considerable amount of time.

## Start EDBArchive

On the first run, start the data-provider stack in the foreground so that its
logs remain visible:

```bash
docker compose up
```

The first startup performs the following operations:

1. creates the MariaDB and EDBArchive storage volumes;
2. creates the application database and database user;
3. creates the `tpcc` database;
4. loads the included `docker/mariadb/03-tpcc.sql.gz` dataset;
5. runs the Laravel migrations and database seeder;
6. starts Apache and the Python job worker.

MariaDB initialization scripts run only when the MariaDB volume is empty.
Laravel migrations and seeders run whenever the web container starts. The
seeder uses `firstOrCreate()` so that existing records are not overwritten.

After a successful first run, the stack can be started in the background:

```bash
docker compose up -d
```

The third-party component is a one-off CLI and is not started by
`docker compose up`.

## Open the web application

Open the following address:

<http://localhost:8000>

The port can be changed through `EDB_WEB_PORT` in the root `.env` file.

Default reproducibility accounts:

| Role | Email | Password |
|---|---|---|
| Data Provider | `admin@edbarchive.com` | `edbarchive` |
| Third Party | `thirdparty@edbarchive.com` | `edbarchive` |

## Verify the services

Check the state of every container:

```bash
docker compose ps -a
```

The expected state is:

- `mariadb`: `healthy`
- `job-queue`: `healthy`
- `web`: `Up`

Test the Python worker health protocol:

```bash
docker compose exec job-queue python -c "import socket; s=socket.create_connection(('127.0.0.1', 9100), 2); s.sendall(b'HEALTH\n'); print(s.recv(32).decode().strip())"
```

Expected response:

```text
OK RUNNING
```

Confirm that MariaDB contains the application and TPCC databases:

```bash
docker compose exec mariadb mariadb -u edbarchive -pedbarchive -e "SHOW DATABASES;"
```

To inspect startup or runtime errors, view the service logs:

```bash
docker compose logs --tail=200 mariadb
docker compose logs --tail=200 job-queue
docker compose logs --tail=200 web
```

## Stop EDBArchive

Stop and remove the containers while preserving the database and generated
artifacts:

```bash
docker compose down
```

Do not add `--volumes` unless the MariaDB database and all artifacts in shared
storage may be deleted.

## Next steps

- Continue with the [Data Provider Guide](data-provider/index.md) to create FHE
  resources and generate a bundle.
- After generating a bundle, use the
  [Third-party Restoration Guide](third-party-guide.md) to restore it.
- See [Troubleshooting](troubleshooting.md) if the build or startup fails.
- Return to the [documentation index](index.md).
