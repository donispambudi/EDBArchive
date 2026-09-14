---
layout: default
title: Paper Reproducibility
---

# Paper Reproducibility

This guide provides a small end-to-end experiment for verifying the
EDBArchive workflow. It uses the included TPC-C database and protects only
`district.d_next_o_id` so that bundle generation and restoration can complete
more quickly than the full workloads evaluated in the paper.

This procedure is intended to make the software artifact easy to evaluate. It
validates the workflow and generated artifacts; it is not intended to
reproduce the execution times or bundle sizes reported for the largest paper
workload. Restoring a full workload can take approximately 20 minutes or more,
depending on the available hardware, whereas this small scenario is intended
as a faster functional check.

## Workflow summary

```text
+-------------------------------------------+
| Installed EDBArchive with the TPC-C data  |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Create or reuse a BFV context and key     |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Create or reuse the TPC-C database        |
| configuration template                    |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Include all TPC-C tables and columns      |
| Encrypt only district.d_next_o_id         |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Generate and download bundle.tar.gz       |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Restore the bundle with the third-party   |
| container into a new MariaDB database     |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Verify the restored tables, row count,    |
| plaintext columns, and FHE ciphertext     |
+-------------------------------------------+
```

## Reproduction scenario

Use the following configuration:

| Setting | Value |
|---|---|
| Source database | `tpcc` |
| Included data | All tables and columns in `tpcc` |
| FHE-protected column | `district.d_next_o_id` |
| FHE library | OpenFHE 1.4.0 |
| Scheme | BFV |

Include every configured TPC-C table and column in the share. Keep all columns
in plaintext except `district.d_next_o_id`, which is the only column protected
with FHE.

When a new FHE context is required, use the default BFV configuration presented
by the web application. These defaults are loaded from the scheme configuration
stored in the database.

This guide assumes that [Installation and Setup](installation.md) has been
completed and the EDBArchive services are running. The `tpcc` database is
created and populated automatically during that installation process.

## 1. Prepare or reuse the FHE resources

Open <http://localhost:8000> and sign in as the Data Provider:

| Email | Password |
|---|---|
| `admin@edbarchive.com` | `edbarchive` |

If a generated OpenFHE BFV context and a generated Third Party key for that
context already exist, reuse them for this scenario. Otherwise, follow
[FHE Resources](data-provider/fhe-resources.md) to:

1. create an OpenFHE BFV context using the default scheme configuration.
2. generate the context through the job queue.
3. create and generate a key pair.

For newly created resources, wait until each related FHE job has status
`completed` before proceeding.

## 2. Prepare or reuse the database template

If `tpcc` is already registered with all of its tables and columns, reuse that
database template. Otherwise, follow
[Database Configuration](data-provider/database-configuration.md) to create
it.

Confirm that only `district.d_next_o_id` uses the FHE default and every other
column uses the plaintext default.

## 3. Create the share

Create a share containing all configured TPC-C tables and columns, then set:

- `district.d_next_o_id` to FHE protection using the generated context and key pair.
- every other column to plaintext.

Do not apply FHE protection to any other column for this small reproduction
scenario.

## 4. Generate the bundle

Follow [Bundle Generation](data-provider/bundle-generation.md) to start the
backup job and download the resulting `bundle.tar.gz`.

The experiment passes this stage when:

- the backup job has status `completed`.
- its result payload identifies a `backup_bundle` artifact.
- the downloaded `bundle.tar.gz` is not empty.

Optionally record its exact size and SHA-256 digest on Linux:

```bash
stat -c '%s bytes' /absolute/path/to/bundle.tar.gz
sha256sum /absolute/path/to/bundle.tar.gz
```

## 5. Restore the bundle

Create a `db.json` file outside the repository with the connection details for
the MariaDB service:

```json
{
  "host": "mariadb",
  "port": 3306,
  "user": "edbarchive",
  "pass": "edbarchive"
}
```

Run the third-party restoration command from the repository root:

```bash
docker compose run --rm \
  -v /absolute/path/to/bundle.tar.gz:/input/bundle.tar.gz:ro \
  -v /absolute/path/to/db.json:/input/db.json:ro \
  third-party \
  --bundle /input/bundle.tar.gz \
  --restore-db tpcc_restored \
  --db-config /input/db.json
```

Replace both `/absolute/path/to/...` values with the actual locations of the
downloaded bundle and `db.json` on the host. The paths after the colon are
their locations inside the container and should remain unchanged.

The restoration command must finish successfully and create the requested
database. The wrapper refuses to overwrite a database that already exists.
Use a different value for `--restore-db` when repeating the experiment.

For further explanation of the command and external database connections, see
the [Third-party Restoration Guide](third-party-guide.md).

## 6. Verify the restored database

Confirm that the restored `district` table exists and has the expected number
of rows:

```bash
docker compose exec mariadb mariadb \
  -u edbarchive -pedbarchive \
  tpcc_restored \
  -e "SELECT COUNT(*) AS district_rows FROM district;"
```

Inspect its column definitions:

```bash
docker compose exec mariadb mariadb \
  -u edbarchive -pedbarchive \
  tpcc_restored \
  -e "SHOW COLUMNS FROM district;"
```

A successful end-to-end reproduction has the following outcome:

- the source database remains unchanged.
- the bundle is generated and can be transferred as a single file.
- the bundle can be restored without the private key.
- all selected TPC-C tables and columns are present in the restored database.
- plaintext columns retain their original values.
- the restored `district` table preserves its row count.
- the protected `d_next_o_id` values are stored as serialized OpenFHE
  ciphertexts rather than plaintext integers.

The bundle does not contain the public or private key. A third party can use
the restored ciphertexts for supported homomorphic operations, but cannot
decrypt them or encrypt new plaintext values using the bundle alone.

## Navigation

- Previous: [Third-party Restoration Guide](third-party-guide.md)
- Next: [Troubleshooting](troubleshooting.md)
- [Documentation index](index.md)
