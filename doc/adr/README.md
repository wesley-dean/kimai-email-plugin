# Architecture Decision Records

## Current Decisions

### ADR-000: Capability Scope, Epistemic Honesty, and Separation of Concerns

The repository requires capability honesty, evidence-oriented reasoning,
separation of concerns, and explicit treatment of uncertainty and limits.
Accuracy and architectural discipline take precedence over performative
helpfulness or agreement.  These constraints govern project work regardless of
the implementation domain.  See
[ADR-000](ADR-000-capability-scope-and-epistemic-honesty.md).

### ADR-001: Adopt Released Coding Standards

The repository adopts the immutable `coding_standards@v1.4.0` release and
materializes its complete standards snapshot under `doc/standards/`.
Applicable imported standards are governing requirements, while accepted local
ADRs or explicit repository policy may refine or supersede them.  Imported
standards are not edited locally; future changes use deliberate release
adoption.  See
[ADR-001](ADR-001-adopt-released-coding-standards.md).

### ADR-002: Adopt MIT License for Project-Owned Work

New project-owned work is distributed under the MIT License, while rights
already granted for historical CC0 material are not revoked.  Third-party code
must preserve its original copyright, attribution, and license obligations
instead of being represented as project-owned work.  The planned ADK plugin
import must therefore carry explicit upstream provenance and preserve its MIT
notice.  See
[ADR-002](ADR-002-adopt-mit-license.md).

<!-- adrctl-generated-footer -->

## Complete ADR Inventory

- [ADR-000: Capability Scope, Epistemic Honesty, and Separation of Concerns](ADR-000-capability-scope-and-epistemic-honesty.md)
- [ADR-001: Adopt Released Coding Standards](ADR-001-adopt-released-coding-standards.md)
- [ADR-002: Adopt MIT License for Project-Owned Work](ADR-002-adopt-mit-license.md)
