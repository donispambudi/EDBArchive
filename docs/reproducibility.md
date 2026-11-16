---
layout: default
title: Paper Reproducibility
---

# Paper Reproducibility

This guide provides automated and manual paths for verifying the EDBArchive
workflow with the included TPC-C database. The automated path is recommended
for artifact evaluation because the Docker startup process prepares the FHE
resources, database metadata, two shares, and their bundle-generation jobs.

This procedure is intended to make the software artifact easy to evaluate. It
validates the workflow and generated artifacts; it is not intended to
reproduce the execution times or bundle sizes reported for the largest paper
workload. The seeded scenarios use one or three encrypted columns so they can
complete more quickly than the full workloads evaluated in the paper.

## Automated reproduction

This path assumes that [Installation and Setup](installation.md) has been
completed from a clean checkout and the EDBArchive services are running. No
manual creation of a context, key, database template, share, or backup job is
required.

### Seeded scenarios

Both shares include all 9 TPC-C tables and all 92 configured columns. They
reuse the same OpenFHE BFV context and the same key owned by the Third Party
account.

| Release version | FHE-protected columns |
|---|---|
| `district-workload` | `district.d_next_o_id` |
| `district-customer-item-workload` | `district.d_next_o_id`, `customer.c_payment_cnt`, `item.i_im_id` |

All columns not listed for a scenario remain plaintext in that share.

### Automated workflow

```text
+-------------------------------------------+
| Start EDBArchive with Docker Compose       |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Load TPC-C and run the Laravel seeder      |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Generate one BFV context and key pair      |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Generate the district workload bundle     |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Generate district-customer-item bundle    |
+---------------------+---------------------+
                      |
                      v
+-------------------------------------------+
| Download, restore, and verify both bundles |
+-------------------------------------------+
```

### 1. Start the services

From the repository root, start the stack if it is not already running:

```bash
docker compose up -d
```

During startup, Docker loads the included TPC-C data and the Laravel seeder
creates the following pending jobs in dependency order:

```text
create-context
create-keypair
backup: district-workload
backup: district-customer-item-workload
```

The single worker processes the jobs in that order. It can take up to the
configured polling interval before processing starts.

### 2. Monitor the jobs

Open <http://localhost:8000> and sign in as the Data Provider:

| Email | Password |
|---|---|
| `admin@edbarchive.com` | `edbarchive` |

Open **FHE Jobs** and wait until all four seeded jobs have status `completed`.
The two shares on the **Shares** page should then show that their bundles were
generated. Worker progress and errors can also be inspected from the
repository root with:

```bash
docker compose logs -f job-queue
```

If any job fails, inspect its standard output, standard error, and structured
error fields in the web application before continuing.

### 3. Download the bundles

Open **Shares** and download the bundle for each release version. Both files
are downloaded as `bundle.tar.gz`, so save or rename them separately, for
example:

```text
district-workload.tar.gz
district-customer-item-workload.tar.gz
```

The Share Detail page displays the SHA-256 hash calculated for each final
bundle. The downloaded file can be checked independently on Linux:

```bash
sha256sum /absolute/path/to/district-workload.tar.gz
sha256sum /absolute/path/to/district-customer-item-workload.tar.gz
```

### 4. Restore the bundles

Create a `db.json` file outside the repository with the connection details for
the Docker MariaDB service:

```json
{
  "host": "mariadb",
  "port": 3306,
  "user": "edbarchive",
  "pass": "edbarchive"
}
```

Restore the district workload into its own database:

```bash
docker compose run --rm \
  -v /absolute/path/to/district-workload.tar.gz:/input/bundle.tar.gz:ro \
  -v /absolute/path/to/db.json:/input/db.json:ro \
  third-party \
  --bundle /input/bundle.tar.gz \
  --restore-db tpcc_district_restored \
  --db-config /input/db.json
```

Restore the district-customer-item workload into a different database:

```bash
docker compose run --rm \
  -v /absolute/path/to/district-customer-item-workload.tar.gz:/input/bundle.tar.gz:ro \
  -v /absolute/path/to/db.json:/input/db.json:ro \
  third-party \
  --bundle /input/bundle.tar.gz \
  --restore-db tpcc_district_customer_item_restored \
  --db-config /input/db.json
```

Replace the host paths with the actual bundle and configuration locations.
The restoration tool refuses to overwrite an existing database, so use a new
database name when repeating a restoration.

### 5. Verify the restored databases

Confirm that both restored databases contain the expected TPC-C tables:

```bash
docker compose exec mariadb mariadb \
  -u edbarchive -pedbarchive \
  -e "SHOW TABLES FROM tpcc_district_restored; SHOW TABLES FROM tpcc_district_customer_item_restored;"
```

Confirm that the restored `district` tables preserve their row counts:

```bash
docker compose exec mariadb mariadb \
  -u edbarchive -pedbarchive \
  -e "SELECT COUNT(*) AS district_rows FROM tpcc_district_restored.district; SELECT COUNT(*) AS district_rows FROM tpcc_district_customer_item_restored.district;"
```

A successful automated reproduction produces two restorable bundles, retains
the plaintext columns, and stores each scenario's protected values as
serialized OpenFHE ciphertexts rather than plaintext integers. The source
`tpcc` database remains unchanged.

The bundles do not contain the public or private key. A third party can use
the restored ciphertexts for supported homomorphic operations, but cannot
decrypt them or encrypt new plaintext values using the bundle alone.

## Manual reproduction

The following procedure retains the original hands-on workflow. It is useful
for reviewing each configuration stage, changing the protected columns, or
creating another share without relying on the seeded scenarios. This manual
example protects only `district.d_next_o_id` for a faster functional check.

### Manual workflow summary

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

### Reproduction scenario

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

### 1. Prepare or reuse the FHE resources

Open <http://localhost:8000> and sign in as the Data Provider:

| Email | Password |
|---|---|
| `admin@edbarchive.com` | `edbarchive` |

Choose either of the following approaches:

- For a faster manual check, wait until the seeded `create-context` and
  `create-keypair` jobs have status `completed`, then reuse the generated
  OpenFHE BFV context and Third Party key.
- To exercise the complete resource-creation workflow or test another
  configuration, create a context and key specifically for this manual share.

To create the resources yourself, follow
[FHE Resources](data-provider/fhe-resources.md) to:

1. create an OpenFHE BFV context using the default scheme configuration.
2. generate the context through the job queue.
3. create and generate a key pair.

Wait until every job associated with the selected context and key has status
`completed` before proceeding. Use the same context and its corresponding
Third Party key consistently when configuring the share.

### 2. Prepare or reuse the database template

For a faster manual check, reuse the `tpcc` template registered by the seeder
with all 9 tables and all 92 columns. A researcher who wants to exercise the
complete configuration workflow may instead prepare an equivalent source
database template through
[Database Configuration](data-provider/database-configuration.md).

The template contains FHE defaults used by broader paper workloads. Do not
change those database-level defaults for this manual experiment. The
one-column protection configuration is applied to the new share in the next
step. The database configuration guide also explains reusable templates and
column defaults.

### 3. Create the share

Create a separate share with the following values:

| Field | Value |
|---|---|
| Owner | `Administrator` |
| Recipient | `Third Party` |
| Database | `tpcc` |
| Release Version | `manual-district-workload` |
| Status | `draft` |

Add all configured TPC-C tables and columns using the generated Third Party
key. The bulk-add action initially copies the database template defaults into
the share. Edit the share items so that:

- `district.d_next_o_id` uses FHE protection with the generated context and
  key pair.
- every other column uses plaintext.

In particular, change these additional template-default FHE columns to
plaintext at the share level:

- `customer.c_payment_cnt`
- `customer.c_delivery_cnt`
- `item.i_im_id`
- `orders.o_carrier_id`
- `orders.o_ol_cnt`
- `orders.o_all_local`

Do not apply FHE protection to any other column for this small reproduction
scenario. These share-level changes do not modify the reusable database
template or either seeded share.

### 4. Generate the bundle

Follow [Bundle Generation](data-provider/bundle-generation.md) to start the
backup job and download the resulting `bundle.tar.gz`.

This creates an additional `backup` job for `manual-district-workload`; it is
separate from the four jobs created automatically by the seeder.

The experiment passes this stage when:

- the backup job has status `completed`.
- its result payload identifies a `backup_bundle` artifact.
- the downloaded `bundle.tar.gz` is not empty.

Optionally record its exact size and SHA-256 digest on Linux:

```bash
stat -c '%s bytes' /absolute/path/to/bundle.tar.gz
sha256sum /absolute/path/to/bundle.tar.gz
```

### 5. Restore the bundle

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
  --restore-db tpcc_manual_district_restored \
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

### 6. Verify the restored database

Confirm that the restored `district` table exists and has the expected number
of rows:

```bash
docker compose exec mariadb mariadb \
  -u edbarchive -pedbarchive \
  tpcc_manual_district_restored \
  -e "SELECT COUNT(*) AS district_rows FROM district;"
```

Inspect its column definitions:

```bash
docker compose exec mariadb mariadb \
  -u edbarchive -pedbarchive \
  tpcc_manual_district_restored \
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

## Packing-method benchmark

The automated and manual procedures above verify the EDBArchive application
workflow. A separate standalone benchmark compares baseline, Carpov, Erkin,
and EDBArchive backup and restoration using the same seeded context, key pair,
TPC-C database, and two protected-column scenarios.

See [Packing-method Benchmark](benchmark.md) for the ready-to-run command,
measurement boundaries, and result format.

## Navigation

- Previous: [Third-party Restoration Guide](third-party-guide.md)
- Next: [Packing-method Benchmark](benchmark.md)
- [Documentation index](index.md)
