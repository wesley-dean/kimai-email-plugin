# Changelog

All notable maintained changes to Kimai Invoice Emailer are recorded here.

The repository uses Conventional Commit PR titles and squash-only merges.
Release tags are created automatically from the commit type.

## 0.2.1 - 2026-10-03

### Testing

- Add read-only post-publication staging validation for GitHub Release assets.
- Verify the downloaded ZIP against both its SHA-256 sidecar and GitHub's asset
  digest before installation.
- Exercise the packaged manual-send workflow through a real SMTP connection to
  a pinned, checksum-verified Mailpit binary.
- Verify the captured message and invoice attachment through Mailpit's API.
- Promote the published `v0.2.0` package to the documented CI-verified
  distribution baseline.

## 0.2.0 - 2026-10-03

### Added

- Add deterministic `InvoiceEmailerBundle-<version>.zip` release packaging.
- Publish a SHA-256 sidecar for every supported release ZIP.
- Add ADR-004 and maintained release-packaging documentation.
- Install and test the generated ZIP in the Kimai 2.67 compatibility matrix.

### Changed

- Run the compatibility matrix on pushes to `main` as well as pull requests.
- Create semantic-version tags and GitHub releases only after main-branch
  compatibility validation succeeds.
- Build release ZIPs with the final `InvoiceEmailerBundle/` directory already
  inside the archive, eliminating the normal post-extraction rename.
- Exclude tests, CI configuration, governance snapshots, and development
  tooling from the runtime package.

## 0.1.2 - 2026-10-03

### Documentation

- Replace the generic repository-template README with plugin installation,
  permissions, usage, security, testing, compatibility, and contribution
  guidance.
- Record Phase 4 CI evidence as bound to the released `v0.1.1` source tree.
- Add maintained support and security-reporting guidance.
- Audit maintained PHP DocBlocks against the adopted Doxygen-first PHP
  documentation standard.

## 0.1.1 - 2026-10-03

### Testing

- Add standalone unit tests and PHPStan checks against Kimai 2.67.0.
- Add real-Kimai integration tests across PHP 8.2, 8.3, 8.4, and 8.5.
- Verify plugin reload, production container compilation, routes, YAML, Twig,
  XLIFF, authorization, CSRF handling, and email-event dispatch.
- Document the Kimai 2.67.0 test-environment shims required by the exact
  upstream tag.
- Fix service discovery so repository documentation is not interpreted as
  plugin PHP services.

## 0.1.0 - 2026-10-03

### Added

- Add the secured manual invoice-email workflow.
- Add the invoice-list action and side-effect-free confirmation page.
- Add the CSRF-protected POST send endpoint.
- Require `email_invoice`, specific-invoice `view_invoice`, and customer
  access.
- Use current `InvoiceService` and Kimai's `EmailEvent` mail path.
- Add sparse HTML and text email templates and English translations.

### Security

- Do not import automatic-send behavior from the historical upstream plugin.
- Do not change invoice status after sending.
- Do not persist `email_sent_date` as delivery evidence.
- Re-resolve send-authoritative state at POST time.

## 0.0.6 - 2026-10-03

### Fixed

- Reconcile the package version after the repository switched to squash-only
  merges.

## 0.0.5 - 2026-10-03

### Added

- Add the initial current-Kimai plugin skeleton, Composer metadata, bundle entry
  point, dependency-injection extension, and service discovery.

## 0.0.4 - 2026-10-03

### Documentation

- Establish upstream provenance, architecture, security, and compatibility
  documents.
- Accept ADR-003 for the secured manual invoice-email workflow.

## 0.0.3 - 2026-10-03

### Licensing

- Adopt the MIT License for project-owned work.
- Record third-party provenance obligations.

## 0.0.2 - 2026-10-03

### Governance

- Adopt released coding standards `v1.4.0`.
- Add repository agent guidance and ADR indexes.
- Update pinned development dependencies through Dependabot.

## 0.0.1 - 2026-10-02

### Repository

- Establish the initial repository baseline.
