# Decision Summary

## Capability Scope, Epistemic Honesty, and Separation of Concerns

Project work must prioritize accuracy, evidence, explicit limits, and clear
separation of concerns over agreement or performative helpfulness.  Uncertainty,
capability boundaries, and unsupported assumptions must be surfaced rather than
hidden.  This decision applies across project domains and remains foundational.
See [ADR-000](adr/ADR-000-capability-scope-and-epistemic-honesty.md).

## Released Coding Standards

The repository adopts `coding_standards@v1.4.0` as a complete, immutable
snapshot under `doc/standards/`, with provenance recorded in
`.codingstandardrc`.  Applicable imported standards are governing
requirements; accepted repository-specific ADRs and explicit local policy may
refine them.  Local exceptions belong in repository governance rather than
edits to the managed snapshot.  See
[ADR-001](adr/ADR-001-adopt-released-coding-standards.md).

## MIT Licensing and Third-Party Provenance

New project-owned work is distributed under the MIT License, while permissions
already granted for previously distributed CC0 material remain intact.
Third-party material must retain its original copyright, attribution, and
license notices.  The planned ADK plugin import must record its upstream source
and preserve the upstream MIT notice rather than presenting imported code as
solely project-owned.  See
[ADR-002](adr/ADR-002-adopt-mit-license.md).

## Secured Manual Invoice Email Workflow

The first maintained implementation sends invoices only through an explicit
human workflow: a side-effect-free confirmation GET followed by an authorized,
CSRF-protected POST.  It uses current Kimai invoice and mail integration,
requires normal access to the specific invoice in addition to the custom email
permission, and performs no automatic invoice status transition.  Automation,
retries, and stronger delivery/idempotency semantics remain separate future
architecture work.  See
[ADR-003](adr/ADR-003-secured-manual-invoice-email-workflow.md).

## Deterministic Release Artifacts

Supported releases use a deterministic `InvoiceEmailerBundle-<version>.zip`
whose internal root is already the final Kimai bundle directory.  The package
excludes development-only repository content, includes legal and provenance
material, and ships with a SHA-256 sidecar.  CI installs and tests the packaged
artifact, and the release tag is created only after main-branch compatibility
validation and package verification succeed.  See
[ADR-004](adr/ADR-004-deterministic-release-artifacts.md).
