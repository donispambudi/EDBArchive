# Packing-method benchmark

This directory contains a standalone command-line benchmark for comparing the
backup and restore behavior of four packing methods: baseline, Carpov, Erkin,
and EDBArchive. It does not call the EDBArchive web application or job queue.

## Compared methods

All methods run on the same OpenFHE 1.4.0 build, BFV context, key pair,
MariaDB service, and seeded TPC-C database.

- **Baseline:** each protected value is encrypted as a separate ciphertext
  without SIMD packing.
- **Carpov-based:** protected values are packed into the polynomial
  coefficients of a plaintext, following
  [Carpov and Sirdey](https://doi.org/10.1145/2898445.2898452). In this
  benchmark, restoration decrypts the coefficient-packed plaintext and
  re-encrypts the individual values because the OpenFHE BFV interface used by
  the implementation does not expose the required homomorphic coefficient
  extraction operation.
- **Erkin-based:** two protected values are packed into a single plaintext
  slot using 14 bits per value, supporting integers from 0 to 2^14 - 1,
  following [Erkin et al.](https://doi.org/10.1109/TIFS.2012.2190726).
  Restoration decrypts the packed value and re-encrypts its individual
  values.
- **EDBArchive:** protected values are packed across configured tables and
  columns into SIMD slots. Restoration uses homomorphic rotations to
  reconstruct per-value ciphertexts, so no decryption is required. Its bundle
  therefore also includes rotation keys.

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

## FHE configuration

| Parameter | Value |
|---|---|
| FHE library | OpenFHE 1.4.0, Release build |
| Scheme | BFV |
| Ring dimension | 16,384 |
| SIMD slots | 16,384 |
| Multiplicative depth | 1 |
| Plaintext modulus | 536,903,681 (approximately 2^29) |
| Security level | `HEStd_128_classic` |

The benchmark image disables OpenFHE examples, tests, and internal benchmarks
during compilation. Docker provides the same software environment across
hosts, but absolute timings still depend on the host hardware and available
resources. Ciphertext counts are determined by the workload and each method's
packing capacity. Bundle sizes are additionally affected by serialized
evaluation keys, the SQL dump, archive metadata, and compression.

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

## Mapping to paper results

The comparison table in the paper is produced by `benchmark scenarios`. Its
columns are derived from the generated metrics as follows:

| Paper column | Metrics source | Conversion |
|---|---|---|
| Bundle (MiB) | `summary.csv`: `bundle_bytes` | bytes / 2^20 |
| End-to-end Backup (s) | `summary.csv`: `backup_total_ms` | milliseconds / 1,000 |
| End-to-end Restore (s) | `summary.csv`: `restore_total_ms` | milliseconds / 1,000 |
| Ctxt Count | `<scenario>-<method>/backup-metrics.json`: `ciphertexts` | None |

The end-to-end backup measurement includes database cloning, backend
execution, database dumping, archive creation, compression, and cleanup. The
end-to-end restore measurement includes extraction, database import, backend
execution, and cleanup. For backend-only analysis, use `backup_backend_ms` and
`restore_backend_ms` instead.

Each scenario-method pair is executed once per benchmark run; the runner does
not average repeated executions. Context and key-pair generation are excluded
from every reported time. Run the suite repeatedly with distinct `--run-id`
values if multiple observations are required.

## References

- Sergiu Carpov and Renaud Sirdey, “Another Compression Method for
  Homomorphic Ciphertexts,” *Proceedings of the 4th ACM International Workshop
  on Security in Cloud Computing*, pp. 44–50, 2016.
  [https://doi.org/10.1145/2898445.2898452](https://doi.org/10.1145/2898445.2898452)
- Zekeriya Erkin, Thijs Veugen, Tomas Toft, and Reginald L. Lagendijk,
  “Generating Private Recommendations Efficiently Using Homomorphic
  Encryption and Data Packing,” *IEEE Transactions on Information Forensics
  and Security*, vol. 7, no. 3, pp. 1053–1066, 2012.
  [https://doi.org/10.1109/TIFS.2012.2190726](https://doi.org/10.1109/TIFS.2012.2190726)
