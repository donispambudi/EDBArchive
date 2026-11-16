# Packing-method benchmark

This directory contains a standalone command-line benchmark for comparing the
backup and restore behavior of four packing methods: baseline, Carpov, Erkin,
and EDBArchive. It does not call the EDBArchive web application or job queue.

Context and key-pair generation are intentionally excluded. Every method uses
the same existing OpenFHE context and key pair. During backup, every method
generates and serializes `relinearization.bin`, matching the original method
implementations. EDBArchive additionally generates `rotation.bin`, matching
the normal EDBArchive backup flow. Carpov and Erkin require the existing key
pair during restore because their restoration procedures decrypt and
re-encrypt the packed values.

## Directories

- `configs/`: database columns included in a run.
- `input/`: local context and key files mounted read-only in Docker.
- `results/`: generated bundles and JSON metrics; ignored by Git.
- `tmp/`: default host-backed restore workspace; generated contents are ignored.
- `build/`: optional native CMake build; ignored by Git.
- `OLD CODE/`: previous experimental implementations retained as references.

## Build

Start the main EDBArchive deployment from the repository root first. This
creates the `edbarchive_default` network and starts the MariaDB service used by
the benchmark:

```bash
docker compose up -d
```

Then build the benchmark from this directory:

```bash
docker compose --env-file ../.env build
```

The benchmark Compose project joins the main deployment's
`edbarchive_default` network and connects directly to `mariadb:3306`. MariaDB
does not need to be exposed beyond the loopback binding configured by the main
deployment.

## Seeded benchmark scenarios

The benchmark includes two explicit JSON configurations corresponding to the
two scenarios created by `DatabaseSeeder`:

- `configs/district.json`: encrypts `district.d_next_o_id`.
- `configs/district-customer-item.json`: encrypts
  `district.d_next_o_id`, `customer.c_payment_cnt`, and `item.i_im_id`.

The JSON files contain the table, primary-key, and encrypted-column metadata.
The benchmark therefore does not query EDBArchive's web metadata tables to
discover its workload. It only queries the TPCC database for the actual row
values processed during backup and restore.

Once the main deployment is running and all four seeded jobs have completed,
run the complete benchmark suite from this directory. Waiting for both seeded
backup jobs also prevents them from competing for resources during timing:

Option 1 runs with the image's default container user:

```bash
docker compose --env-file ../.env run --rm benchmark scenarios
```

Option 2 runs with the current Linux host user's UID and GID so files under
`results/` can be edited or removed without `sudo`:

```bash
docker compose --env-file ../.env run --rm \
  --user "$(id -u):$(id -g)" \
  benchmark scenarios
```

Both options execute the same benchmark and do not change its database,
OpenFHE, or timing logic. Use Option 1 if the mounted FHE artifacts are not
readable by the host UID. Results created by Option 1 may be owned by `root`
or `nobody` on hosts that use user-namespace mapping. Their ownership can be
recovered with:

```bash
sudo chown -R "$(id -u):$(id -g)" results
```

For a faster check, run only the `district.d_next_o_id` scenario across all
four methods. The same two user options apply; for host-owned output, run:

```bash
docker compose --env-file ../.env run --rm \
  --user "$(id -u):$(id -g)" \
  benchmark quick
```

The quick command performs four backups and four restores. The complete
`scenarios` command performs eight backups and eight restores.

## Temporary storage

Restore extraction uses the host-backed `tmp/` directory instead of the
container writable layer. To place this workspace on a larger drive, create a
writable directory and set `BENCHMARK_TMP_DIR` when invoking Compose:

```bash
mkdir -p /path/on/large-drive/edbarchive-benchmark-tmp
BENCHMARK_TMP_DIR=/path/on/large-drive/edbarchive-benchmark-tmp \
  docker compose --env-file ../.env run --rm \
  --user "$(id -u):$(id -g)" \
  benchmark scenarios
```

Before a `district-customer-item` or complete run starts, the runner checks
the free space on this bind mount. Less than 65 GiB aborts the benchmark.
Between 65 GiB and the recommended 97.5 GiB produces a warning but continues.
The quick `district` run does not apply this large-scenario threshold.

For the suite runners, the compressed archive is discarded after successful
extraction. `dump.sql` is removed after import, and consumed ciphertext files
are removed after the database commit that covers their final use. This keeps
the restore workspace shrinking while MariaDB grows. The temporary directory
is removed when restore finishes or fails.

This single command executes baseline, Carpov, Erkin, and EDBArchive for both
scenarios. It uses the seeded FHE artifacts directly from the main
`edbarchive-storage` volume, restores every generated bundle, and removes the
temporary restored databases after successful verification. Results are
grouped under `results/run_<UTC timestamp>/`, with a `summary.csv` containing
the main timing and bundle-size measurements.

After each compressed archive and its metrics have been written successfully,
the uncompressed `bundle/` staging directory is removed automatically. The
`quick` and `scenarios` runners record the compressed size, extract the bundle
for restore verification, and then discard `bundle.tar.gz` before MariaDB
starts expanding the restored representation. This avoids retaining the
archive, extracted bundle, and restored database at the same time. Individual
`backup` and `restore` commands retain the archive unless `restore` is called
with `--discard-bundle`.

Use a stable run identifier when desired:

```bash
docker compose --env-file ../.env run --rm benchmark scenarios \
  --run-id paper_run_1
```

Add `--keep-databases` to retain all restored databases for manual inspection.

The remaining sections describe how to execute an individual method manually.

For a manual run with artifacts other than the seeded ones, place an existing
context and key pair under `benchmark/input/`, for example:

```text
input/
├── context.bin
├── private.bin
└── public.bin
```

## Backup

The command clones the source database, modifies only the temporary clone,
produces a database dump, and writes the bundle and metrics under `results/`.

```bash
docker compose --env-file ../.env run --rm benchmark backup \
  --method edbarchive \
  --source-db tpcc \
  --context /input/context.bin \
  --public-key /input/public.bin \
  --private-key /input/private.bin \
  --name edbarchive-tpcc
```

Keep `--private-key` for every method because it is used to generate the
relinearization key included in each bundle. Change only `--method` and
`--name` when repeating the experiment so all methods use the same context,
key, database, and column configuration.

## Restore

Baseline restoration and EDBArchive restoration do not receive the key pair:

```bash
docker compose --env-file ../.env run --rm benchmark restore \
  --method edbarchive \
  --bundle /results/edbarchive-tpcc/bundle.tar.gz \
  --database tpcc_restored_edbarchive \
  --metrics /results/edbarchive-tpcc/restore-metrics.json
```

Carpov and Erkin additionally require the existing key directory:

```bash
docker compose --env-file ../.env run --rm benchmark restore \
  --method carpov \
  --bundle /results/carpov-tpcc/bundle.tar.gz \
  --database tpcc_restored_carpov \
  --key-dir /input \
  --metrics /results/carpov-tpcc/restore-metrics.json
```

The restore target must not already exist. It is retained after a successful
run so its encrypted columns can be inspected. If restoration fails, the
partially restored target database is removed.

## Fair-comparison boundaries

All methods use the same SQL row ordering, commit intervals, OpenFHE context,
and compression policy. As in the main wrapper, compression uses `pigz -8`
when `dump.sql` occupies more than half of the uncompressed tar archive and
`pigz -1` otherwise. As in the main FHE backend, an unfinished ciphertext
batch continues into the next configured column. The reported backup time
includes loading the existing FHE artifacts, generation of the
relinearization key, database transformation, encryption, and serialization.
For EDBArchive it additionally includes generation of the rotation key
performed by the normal backup flow. It excludes context and key-pair
generation. The shell wrapper creates the
portable database dump and compressed archive after the C++ backup timing has
been recorded. Each metrics file therefore reports `backend_elapsed_ms` for
the same execution boundary as the standalone FHE backend and
`total_elapsed_ms` for the complete wrapper workflow. Backup metrics
additionally report the uncompressed tar and SQL dump sizes, selected
compression level, and final compressed bundle size in bytes.
