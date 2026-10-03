# Release Packaging

## Purpose

This document describes the maintained Kimai Invoice Emailer distribution
artifact and the workflow that produces it.

The governing architectural decision is
[ADR-004](adr/ADR-004-deterministic-release-artifacts.md).

## Supported Artifact

The supported Kimai installation package is:

```text
InvoiceEmailerBundle-<version>.zip
```

A SHA-256 sidecar is published beside it:

```text
InvoiceEmailerBundle-<version>.zip.sha256
```

The ZIP contains one top-level directory:

```text
InvoiceEmailerBundle/
```

Extracting it directly beneath Kimai's `var/plugins/` directory therefore
produces:

```text
var/plugins/InvoiceEmailerBundle/InvoiceEmailerBundle.php
```

No post-extraction rename is required.

## Included Content

The release archive includes runtime code and resources together with
consumer-relevant metadata and licensing/provenance material.

At minimum it contains:

```text
InvoiceEmailerBundle/
├── Controller/
├── DependencyInjection/
├── EventSubscriber/
├── Exception/
├── Model/
├── Resources/
├── Service/
├── InvoiceEmailerBundle.php
├── composer.json
├── README.md
├── CHANGELOG.md
├── LICENSE
└── UPSTREAM.md
```

Development tests, CI workflows, repository governance, coding-standard
snapshots, and build scripts are not part of the supported runtime package.

## Building Locally

The builder requires Git, PHP, Python 3, tar, unzip, and `sha256sum`.

From the repository root:

```bash
scripts/build-release.bash 0.2.0 dist
```

The version argument must exactly match `composer.json`.

An optional third argument selects a committed Git reference:

```bash
scripts/build-release.bash 0.2.0 dist HEAD
```

The builder writes the absolute ZIP path to STDOUT and the checksum path to
STDERR.

## Determinism

The builder exports a committed Git tree using `.gitattributes` and then
serializes the exported files with:

- stable lexical ordering;
- fixed ZIP timestamps;
- normalized Unix permissions;
- no symlinks; and
- stored, uncompressed ZIP entries.

Two builds from the same exported tree and package version are expected to be
byte-identical.

The compatibility workflow verifies this with `cmp` before installing the
archive.

## Artifact Verification

Verify the checksum from the directory containing both downloaded files:

```bash
sha256sum -c InvoiceEmailerBundle-0.2.0.zip.sha256
```

Inspect the archive layout when needed:

```bash
unzip -Z1 InvoiceEmailerBundle-0.2.0.zip
```

The archive must not place files outside `InvoiceEmailerBundle/`.

## CI Validation

Pull requests and pushes to `main` build the package from the exact commit
under test.

The integration matrix:

1. builds the ZIP twice;
2. verifies byte-for-byte identity;
3. verifies the checksum;
4. extracts the ZIP beneath Kimai's `var/plugins/`;
5. verifies the required bundle entry point;
6. confirms development-only paths are absent; and
7. runs the existing Kimai 2.67.0 integration checks and controller suite.

The integration tests remain outside the release ZIP and execute against the
installed packaged plugin.

## Release Ordering

The release workflow is downstream of successful compatibility validation on
`main`.

It calculates the next semantic version without creating a tag, verifies that
the result matches `composer.json`, builds and re-verifies the package, and
only then creates the Git tag and GitHub release.

Both the ZIP and checksum are uploaded as release assets.

## Source Archives

GitHub also creates generic repository source archives for each tag.

Those archives are useful for source review, but they are not the supported
Kimai installation artifact because their root layout and contents are not the
distribution contract tested by this repository.
