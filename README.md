# EDBArchive

EDBArchive is a storage-efficient backup and restoration system for fully
homomorphically encrypted relational database sharing. It enables a data
provider to select database columns for protection, pack their values into
OpenFHE ciphertexts, and produce a transferable bundle that can be restored by
a third party without decrypting the protected data.

## Features

- Management interface for databases, FHE contexts, keys, and sharing
  configurations.
- Column-level selection between plaintext and FHE protection.
- SIMD-packed ciphertext generation for storage-efficient backups.
- Transferable bundles containing the database dump, reconstruction metadata,
  ciphertexts, FHE context, and evaluation material.
- Exclusion of both public and private keys from shared bundles, restricting
  recipients to supported homomorphic evaluation on existing ciphertexts.
- Standalone third-party restoration workflow.
- SHA-256 hash generation for identifying and checking generated bundles.
- Docker Compose environment with the included TPCC dataset.

## Architecture

EDBArchive separates the management interface from the long-running
cryptographic and database operations:

![EDBArchive architecture](docs/images/architecture.svg)

The data-provider stack consists of the Laravel application, Python job worker,
OpenFHE backend, and MariaDB. Bundle restoration is performed separately by the
third-party CLI.

## Supported implementation

- OpenFHE 1.4.0
- BFV scheme
- MariaDB 12.1.2
- MariaDB Connector/C++ 1.1.7
- Integer-valued protected columns
- One FHE library, scheme, context, and key pair per bundle
- Linux amd64 and ARM64 container builds

The backend dispatch mechanism is designed to accommodate additional FHE
libraries, schemes, and packing strategies. Shared backend definitions are
provided under `backend/common/` and `third-party/common/` so researchers can
implement paired provider and restoration backends for their own experiments.
The current implementation provides OpenFHE BFV.

## Documentation

Complete installation, configuration, usage, architecture, and reproducibility
instructions are available in the
[EDBArchive documentation](https://donispambudi.github.io/EDBArchive/).

The documentation source is maintained in the [`docs/`](docs/) directory.

## Paper

This repository accompanies:

> *EDBArchive: A storage-efficient backup and restoration system for fully
> homomorphically encrypted database sharing*

## License

EDBArchive is distributed under the [MIT License](LICENSE).

## Contact

For questions and support, contact Doni Setio Pambudi at
[doni.pambudi@uisi.ac.id](mailto:doni.pambudi@uisi.ac.id).
