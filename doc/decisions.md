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
