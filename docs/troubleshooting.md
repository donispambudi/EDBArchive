---
layout: default
title: Troubleshooting
---

# Troubleshooting

The provided Docker configuration has been tested successfully through every
stage of the sample workflow: image build, service initialization, FHE resource
generation, bundle creation, and third-party restoration. Following the
documented installation and reproducibility scenario should therefore provide
the known-good configuration.

The issues below are primarily relevant when adapting the environment to a
different host or extending EDBArchive with different ports, credentials,
database settings, FHE schemes, libraries, or backend implementations. Local
environment issues such as missing `.env` files and occupied host ports can
still occur without modifying the application.

Run the commands from the repository root, where `compose.yaml` is located.

## Initial diagnosis

Start by checking Docker, the resolved Compose configuration, container state,
and recent logs:

```bash
docker info
docker compose config
docker compose ps -a
docker compose logs --tail=200
```

To inspect one service only:

```bash
docker compose logs --tail=300 mariadb
docker compose logs --tail=300 job-queue
docker compose logs --tail=300 web
```

For a build failure, enable plain progress output so that the actual error is
visible above the final `exit code` message:

```bash
docker compose --profile third-party --progress plain build
```

The last line usually reports only that a build step failed. Look earlier in
the output for the package, compiler, or CMake error that caused it.

## Docker is unavailable

### Cannot connect to the Docker daemon

Docker Engine or Docker Desktop must be running before any Compose command can
start a container. Confirm this with:

```bash
docker info
```

If the command fails, start Docker using the normal mechanism for the host
operating system. On Linux, also ensure that the current user has permission
to access the Docker daemon.

## Environment configuration

### Compose reports a missing variable

Create the root environment file and the Laravel environment file if they do
not exist:

```bash
cp .env.example .env
cp web/.env.example web/.env
```

Then validate interpolation again:

```bash
docker compose config
```

The root `.env` supplies Compose settings and shared database values. The
`web/.env` file supplies Laravel application settings. Database addresses used
inside Docker are injected by `compose.yaml`.

### A host port is already in use

A message such as `address already in use` means another process or container
already occupies the published host port. Change the corresponding value in
the root `.env` file, for example:

```dotenv
EDB_WEB_PORT=8001
EDB_DB_HOST_PORT=3308
```

Recreate the affected containers afterward:

```bash
docker compose up -d
```

These variables change only the host-facing ports. Connections between the
containers must continue to use `mariadb:3306` and `job-queue:9100`.

## Build problems

Although the provided images have been built successfully with the documented
configuration, build problems can still be caused by an incomplete clone,
host resource limits, network access, upstream package availability, or stale
Docker cache.

### OpenFHE source or submodules are missing

Errors may include:

```text
fatal: not a git repository
git submodule sync --recursive failed
```

Initialize the recorded submodule revision from the EDBArchive repository
root:

```bash
git submodule sync --recursive
git submodule update --init --recursive
```

If the repository was cloned over HTTPS but the OpenFHE submodule requires SSH,
configure a local HTTPS URL and retry:

```bash
git config submodule.deps/openfhe-development.url https://github.com/openfheorg/openfhe-development.git
git submodule update --init --recursive
```

Do not run `git pull` inside `deps/openfhe-development`; the submodule should
remain at the commit recorded by EDBArchive.

### APT exits with code 100

Rebuild with plain output and locate the first package or repository error:

```bash
docker compose --progress plain build job-queue
```

Typical causes include temporary network or package-repository failures, an
unsupported target architecture, or stale Docker build layers. The job-queue
and third-party images currently support `amd64` and `arm64`.

After confirming network access and architecture support, retry without cached
layers:

```bash
docker compose --progress plain build --no-cache --pull job-queue
```

Do not bypass a checksum mismatch for a downloaded dependency. A mismatch may
indicate that the upstream artifact changed and should be investigated before
the expected checksum is updated.

### Python package compilation cannot find `Python.h`

The current job-queue Dockerfile installs `python3-dev`. This error usually
means Docker is using an older cached build definition. Rebuild the image from
the current source:

```bash
docker compose --progress plain build --no-cache job-queue
```

### Laravel `package:discover` fails during the web build

The current web Dockerfile removes generated PHP files from `bootstrap/cache`
before running package discovery. Rebuild the web image without cached layers:

```bash
docker compose --progress plain build --no-cache web
```

If it still fails, inspect the lines immediately before the Docker build's
final exit-code message. Laravel normally prints the underlying exception
there.

### OpenFHE compilation stops unexpectedly

Compiling OpenFHE requires considerably more memory than running the services.
An unexplained compiler termination or an `out of memory` message can indicate
that Docker exhausted its memory limit. Increase the memory available to
Docker, close other memory-intensive processes, and rebuild the image.

## MariaDB initialization

### TPC-C initialization failed and is not retried

Scripts in `/docker-entrypoint-initdb.d` run only when the MariaDB data
directory is empty. Restarting the same initialized container or volume does
not rerun them.

To retry initialization, remove only the MariaDB volume:

```bash
docker compose down
docker volume rm edbarchive_mariadb-data
docker compose up -d
```

Warning: this permanently deletes the application database, the TPC-C
database, and all other data stored in that MariaDB volume.

If Docker reports that the volume does not exist, obtain its actual name with:

```bash
docker volume ls
```

### TPC-C loading reports `using password: NO`

The current `docker/mariadb/03-load-tpcc.sh` passes `MARIADB_USER` and
`MARIADB_PASSWORD` to the client. Ensure that the current script is present in
the checkout, then recreate the MariaDB volume because a corrected
initialization script is not rerun against an existing volume.

### MariaDB remains unhealthy

Inspect its status and initialization log:

```bash
docker compose ps mariadb
docker compose logs --tail=300 mariadb
```

Check for invalid credentials, an incomplete initialization, insufficient disk
space, or damage caused by an interrupted first startup. If the data is
disposable and initialization did not complete, recreate only the MariaDB
volume using the procedure above.

## Web application

### The web page does not open

Check whether the container is running and which host port is published:

```bash
docker compose ps web
docker compose logs --tail=300 web
```

Open `http://localhost:<EDB_WEB_PORT>` using the value from the root `.env`.
The default address is <http://localhost:8000>.

The web container waits for MariaDB and the job queue before starting. A web
container that remains stopped may therefore be caused by an unhealthy
dependency.

### Migration or seeding fails on startup

The web container runs `php artisan migrate --force` and
`php artisan db:seed --force` whenever it starts. Inspect the web and MariaDB
logs to identify the failing migration, database connection, or constraint.

Do not delete the MariaDB volume merely to hide a migration error. Reset it
only when its data is disposable and a completely fresh initialization is the
intended test.

### Source changes do not appear

Application source is copied into the image rather than bind-mounted. Rebuild
and recreate the affected service:

```bash
docker compose build web
docker compose up -d --force-recreate web
```

Use the equivalent service name for changes to `job-queue` or `third-party`.

## Job queue and FHE jobs

### The job queue is unhealthy

Check its health state and logs:

```bash
docker compose ps job-queue
docker compose logs --tail=300 job-queue
```

Test the worker health protocol directly:

```bash
docker compose exec job-queue python -c "import socket; s=socket.create_connection(('127.0.0.1', 9100), 2); s.sendall(b'HEALTH\n'); print(s.recv(32).decode().strip())"
```

The expected response is:

```text
OK RUNNING
```

If the worker cannot connect to MariaDB, confirm that Compose injects
`EDB_DB_HOST=mariadb`, `EDB_DB_PORT=3306`, and the same password used by the
MariaDB service.

### An FHE job remains pending

Confirm that the job queue is healthy, then inspect its logs. The worker polls
for pending work periodically even if the web notification is missed, so a
job that remains pending longer than the configured poll interval usually
indicates a worker or database-connection problem.

### An FHE job fails

Open the FHE Job detail page in the web application and inspect:

- the error code and error stage
- the result payload
- standard output
- standard error

Also inspect the worker log:

```bash
docker compose logs --tail=500 job-queue
```

Structured errors distinguish configuration, database, filesystem, archive,
and backend failures. Correct the reported cause before creating another job.

### The generated bundle cannot be found

Successful bundles are stored in the shared Docker volume under:

```text
public/<job-id>/bundle.tar.gz
```

List the shared storage through the job-queue container:

```bash
docker compose exec job-queue find /app/storage/public -maxdepth 3 -type f -ls
```

If the backup job failed, the final bundle may not have been created. Inspect
the job result and logs instead of relying only on the storage directory.

## Third-party restoration

### An input file does not exist inside the container

Use absolute host paths in both volume arguments:

```bash
docker compose run --rm \
  -v /absolute/path/to/bundle.tar.gz:/input/bundle.tar.gz:ro \
  -v /absolute/path/to/db.json:/input/db.json:ro \
  third-party \
  --bundle /input/bundle.tar.gz \
  --restore-db tpcc_restored \
  --db-config /input/db.json
```

The paths passed to `--bundle` and `--db-config` are container paths, not host
paths. Quote a complete `-v` argument if its host path contains spaces.

### The database connection is refused

When restoring into the MariaDB service from `compose.yaml`, use the following
values in `db.json`:

```json
{
  "host": "mariadb",
  "port": 3306,
  "user": "edbarchive",
  "pass": "edbarchive"
}
```

Do not use `localhost` or the host-facing port `3307` for communication between
these containers. Confirm that MariaDB is running with
`docker compose ps mariadb`.

For an external server, the configured address must be reachable from inside
the third-party container. `localhost` inside the container refers to the
container itself.

### The restore database already exists

The wrapper intentionally refuses to overwrite an existing database. Supply a
new value to `--restore-db`, such as `tpcc_restored_2`, or explicitly remove the
earlier test database after confirming that its contents are no longer needed.

### `db.json` is invalid

The file must contain a non-empty string `host`, an integer `port` from 1 to
65535, a non-empty string `user`, and a string `pass`. Validate it locally with
`jq` if available:

```bash
jq . /absolute/path/to/db.json
```

The database connection file is not the same as `config.json` inside the
bundle.

### The third-party backend executable is missing

Rebuild the third-party target from the current source:

```bash
docker compose --progress plain build --no-cache third-party
```

The expected executable inside the image is
`/app/third-party/bin/openfhe_bfv`. The library and scheme names recorded in the
bundle determine which backend executable the wrapper selects.

## Reset and rebuild

Stop containers while preserving the database and generated artifacts:

```bash
docker compose down
```

Perform a full reset and cache-free rebuild only when all project data may be
discarded:

```bash
docker compose down --volumes --rmi local --remove-orphans
docker compose --profile third-party --progress plain build --no-cache --pull
docker compose up -d
```

Warning: `--volumes` permanently deletes both the MariaDB database and all
generated artifacts in the shared EDBArchive storage volume.

## Navigation

- Previous: [Paper Reproducibility](reproducibility.md)
- [Documentation index](index.md)
