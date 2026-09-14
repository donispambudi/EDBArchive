---
layout: default
title: Data Provider Guide
---

# Data Provider Guide

The data provider prepares the cryptographic resources, registers the source
database structure, selects which columns require protection, and generates
the transferable EDBArchive bundle. These operations are performed through
the web application, while long-running FHE and backup tasks are processed by
the job queue.

This guide assumes that [Installation and Setup](../installation.md) has been
completed and the EDBArchive services are running.

## Provider workflow

```text
Create or select a generated FHE context
                  |
                  v
Create or select the recipient key
                  |
                  v
Register or select the database template
                  |
                  v
Review columns and protection defaults
                  |
                  v
Create a share and add its items
                  |
                  v
Generate and download bundle.tar.gz
```

Complete the sections in order for the initial setup. For later bundles, reuse
the existing resources and start directly from **Bundle Generation** when the
same context, recipient key, and database template remain suitable.

## Reusable system-wide configuration

FHE and database configuration is not created separately for every share.
EDBArchive maintains reusable system-wide records that can be selected by
multiple shares:

| Resource | Scope and reuse |
|---|---|
| Library | System-wide FHE implementation definition; reusable by all compatible schemes and contexts |
| Scheme | System-wide scheme and parameter definition; reusable when creating contexts |
| FHE context | Generated once and reusable across multiple compatible keys and shares |
| FHE key | Reusable for multiple shares when the recipient, context, and key lifecycle remain appropriate |
| Database configuration | Reusable template of tables, columns, and default protection for multiple shares |
| Share | Per-delivery configuration that produces one bundle for a specific owner, recipient, and database selection |

The normal recurring workflow is therefore:

```text
Reuse context + recipient key + database template
                        |
                        v
                   Create share
                        |
                        v
             Generate a new bundle
```

Database column settings act as defaults. When configured columns are added
to a share, those defaults are copied into its share items. Later changes to
the database template do not retroactively update share items that already
exist, so each share must still be reviewed before bundle generation.

## Guide sections

### 1. [FHE Resources](fhe-resources.md)

Create reusable cryptographic resources when no suitable generated context or
recipient key exists. Existing compatible resources can be selected instead.

### 2. [Database Configuration](database-configuration.md)

Create a reusable template for an existing MariaDB database, including its
tables, enabled columns, and default plaintext or FHE protection.

### 3. [Bundle Generation](bundle-generation.md)

Create a share, associate it with a recipient, add the configured database
columns, review their protection, generate the bundle, and download the final
artifact.

## Reproducibility scenario

The documentation uses a small scenario intended to verify the complete
workflow without the restoration time of the full paper workload:

| Setting | Value |
|---|---|
| Database | `tpcc` |
| Included data | All configured tables and columns |
| FHE-protected column | `district.d_next_o_id` |
| Other columns | Plaintext |
| Library and scheme | OpenFHE BFV |
| Context parameters | Defaults provided by the scheme configuration |
| Data Provider | `admin@edbarchive.com` |
| Recipient | `thirdparty@edbarchive.com` |

The complete procedure is summarized in
[Paper Reproducibility](../reproducibility.md). The three pages in this guide
provide the detailed web-interface steps.

## Generated output

The provider workflow produces `bundle.tar.gz`. The bundle contains the
selected database structure and data, serialized packed ciphertexts, the FHE
context, evaluation material, and reconstruction metadata required by the
third-party restoration process.

The public and private keys are not included in the bundle. The recipient can
restore and homomorphically process the existing ciphertexts but cannot
decrypt them or encrypt new plaintext values using the bundle alone.

After downloading the bundle, continue with the
[Third-party Restoration Guide](../third-party-guide.md).

## Navigation

- Previous: [Installation and Setup](../installation.md)
- Next: [FHE Resources](fhe-resources.md)
- [Documentation index](../index.md)
