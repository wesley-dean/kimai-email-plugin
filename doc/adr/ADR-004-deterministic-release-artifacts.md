# ADR-004: Define Deterministic Kimai Release Artifacts

Date: 2026-10-03

## Status

Accepted

## Context

The maintained plugin has reached the point where source-level installation is
no longer an adequate distribution contract.

Kimai's current plugin documentation requires plugins to reside beneath
`var/plugins/`, with this plugin ultimately installed as:

```text
var/plugins/InvoiceEmailerBundle/
```

and with `InvoiceEmailerBundle.php` directly inside that directory.

Kimai also recommends release ZIP filenames in the form
`Namespace-<full-version>.zip`.

The repository name, `kimai-email-plugin`, does not match the required
installed bundle directory.  GitHub-generated source ZIPs also include
development files and use repository/version-derived root directories that are
not the final Kimai installation path.

The repository's testing standard further requires public distribution
artifacts to receive behavioral coverage rather than assuming that successful
source tests prove packaging preserved behavior.

Finally, the existing release workflow creates the semantic-version tag before
any distribution artifact exists.  Once the ZIP becomes a public deliverable,
that ordering could leave a valid-looking release tag behind when packaging
fails.

## Decision Drivers

- Produce a Kimai-native installation layout.
- Make the public ZIP byte-for-byte reproducible for the same exported tree.
- Keep the archive filename compatible with Kimai's documented convention.
- Preserve the MIT license and upstream provenance in distributed artifacts.
- Exclude CI, tests, governance snapshots, and development tooling from the
  runtime ZIP.
- Verify the artifact itself against Kimai rather than testing source only.
- Publish a checksum beside every ZIP.
- Prevent a release tag from being created before packaging and validation
  succeed.
- Keep package construction inspectable and repository-owned.

## Decision

The supported distribution artifact SHALL be a ZIP named:

```text
InvoiceEmailerBundle-<major.minor.patch>.zip
```

The ZIP SHALL contain exactly one top-level bundle directory:

```text
InvoiceEmailerBundle/
```

so that extracting the ZIP directly under Kimai's `var/plugins/` directory
produces the final supported installation path without a rename.

The archive SHALL contain the runtime plugin source and resources together with
consumer-relevant metadata and legal/provenance files, including:

- `InvoiceEmailerBundle.php`;
- maintained runtime PHP source directories;
- `Resources/`;
- `composer.json`;
- `README.md`;
- `CHANGELOG.md`;
- `LICENSE`; and
- `UPSTREAM.md`.

The archive SHALL exclude development-only and repository-governance material,
including:

- GitHub workflow configuration;
- tests;
- repository coding-standard snapshots and ADRs;
- release-builder scripts;
- static-analysis and PHPUnit configuration;
- contributor/agent workflow files; and
- unrelated repository tooling configuration.

Tracked-file inclusion and exclusion SHALL be controlled through
`.gitattributes` using `export-ignore`.

The maintained release builder SHALL build from a committed Git tree, not from
uncommitted working-tree state.

The builder SHALL verify that the requested release version matches the version
in `composer.json`.

The ZIP SHALL be serialized deterministically using:

- lexicographically stable entry ordering;
- a fixed ZIP timestamp;
- normalized file and directory permissions;
- no symlinks; and
- uncompressed ZIP entries to avoid compressor-version variability.

A second build from the same tree SHALL produce byte-identical output.

Every release ZIP SHALL have a sidecar SHA-256 file named:

```text
InvoiceEmailerBundle-<version>.zip.sha256
```

The compatibility workflow SHALL install the generated ZIP into a clean Kimai
2.67.0 checkout and run the existing integration suite against that packaged
runtime representation.

The compatibility workflow SHALL run on pull requests and on pushes to
`main`.

For pushes to `main`, the compatibility workflow SHALL contain a read-only
release-candidate job that depends directly on successful completion of both
the unit and integration matrices.  That job SHALL:

1. check out the validated `main` commit with persisted credentials disabled;
2. calculate the semantic version without creating a tag;
3. require that the calculated version matches `composer.json`;
4. build and verify the deterministic ZIP and checksum;
5. rebuild the ZIP and confirm byte-for-byte identity; and
6. upload only the validated ZIP and SHA-256 sidecar as a short-lived workflow
   artifact.

A separate publish job SHALL depend directly on the release-candidate job and
SHALL be the only job with `contents: write`.  The publish job SHALL NOT check
out repository source or execute repository-controlled code.  It SHALL only:

1. download the validated ZIP and checksum produced by the read-only job;
2. verify the exact transferred file set;
3. verify the SHA-256 checksum and ZIP integrity; and
4. create the GitHub release and tag from those already-validated bytes.

The GitHub release SHALL upload both the ZIP and its SHA-256 sidecar.

GitHub-generated source archives remain available as repository artifacts but
are not supported Kimai installation packages.

## Alternatives Considered

### Use GitHub's automatically generated source ZIP

This requires consumers to understand repository-specific root naming and
includes development-only files.  It also does not provide a repository-owned
artifact contract or checksum.

Rejected.

### Put a versioned directory inside the ZIP

Kimai documents this common layout, followed by a manual rename after
extraction.

It is compatible, but the rename creates unnecessary installation work and
another opportunity for directory-name mistakes.  The version remains in the
artifact filename while the internal directory uses Kimai's final required
name.

Rejected.

### Build the ZIP directly from the working tree

This could accidentally publish uncommitted or ignored local state and would
make the release less auditable.

Rejected.

### Use ordinary compressed ZIP output

Compression can vary with tool and library versions even when file contents are
unchanged.

The plugin is small enough that storage overhead is not a meaningful tradeoff.
Stored ZIP entries are therefore preferred for stronger byte reproducibility.

Rejected.

### Create the release tag before building the artifact

This preserves the previous workflow order but allows packaging failure to
leave a release marker that has not passed its distribution checks.

Rejected.

### Distribute through Composer

Kimai deliberately loads plugins from `var/plugins/` to avoid modifying the
main application's Composer-managed dependency state.

Composer metadata remains useful for development, but Composer is not the
supported installation path for this plugin.

Rejected.

## Consequences

### Positive

- Extraction directly under `var/plugins/` produces the correct bundle path.
- Consumers receive only runtime and consumer-relevant files.
- The ZIP is reproducible for the same exported tree.
- Every supported release asset has a SHA-256 checksum.
- CI exercises the same representation users install.
- A failed package build cannot create a release tag.
- The write-capable publication boundary never checks out or executes
  repository source.
- Release contents and exclusions are inspectable in repository source.
- License and upstream provenance travel with the distributed plugin.

### Negative

- The release pipeline becomes dependent on the maintained builder and its
  required local tools.
- Uncompressed ZIPs are larger than compressed archives.
- Development docs and tests are intentionally absent from the release ZIP.
- Changes to the public artifact contract require coordinated changes to the
  builder, tests, documentation, and this decision.

## Compatibility and Migration

The first release using this contract is expected to be `v0.2.0`.

Earlier GitHub releases remain historical source releases.  Their automatically
generated source archives are not retroactively promoted to supported plugin
packages.

Consumers moving from a Git clone may remove the existing
`var/plugins/InvoiceEmailerBundle/` directory, extract the release ZIP beneath
`var/plugins/`, verify the checksum, and reload Kimai.

No database migration is involved.

## Expected Outcome

A reviewer should be able to establish that:

- the release filename carries the semantic version;
- the archive extracts directly to `InvoiceEmailerBundle/`;
- required runtime and legal files are present;
- development-only files are absent;
- repeated builds of the same tree are byte-identical;
- the checksum verifies;
- the packaged plugin passes the same Kimai integration contract as maintained
  source; and
- the Git tag/release is created only after validation succeeds; and
- the privileged publish job handles only previously validated release bytes.

## Related Decisions

- [ADR-001: Adopt Released Coding Standards](ADR-001-adopt-released-coding-standards.md)
- [ADR-002: Adopt MIT License for Project-Owned Work](ADR-002-adopt-mit-license.md)
- [ADR-003: Establish a Secured Manual Invoice Email Workflow](ADR-003-secured-manual-invoice-email-workflow.md)

## Related Documentation

- [Release Packaging](../release.md)
- [Testing](../testing.md)
- [Compatibility](../compatibility.md)
