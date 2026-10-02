# ADR-001: Adopt Released Coding Standards

Date: 2026-10-02

## Status

Accepted

## Intent and Documentation Posture

This ADR records the repository's decision to adopt a concrete released snapshot
of the shared coding standards maintained in
`wesley-dean/coding_standards`.  It establishes provenance, authority,
applicability, update boundaries, and the relationship between imported
standards and repository-specific governance.

## Context

This repository was created from a general project template and initially
contained no `.codingstandardrc` file and no `doc/standards/` managed
snapshot.  Development is beginning with a substantial PHP/Kimai plugin, so
repository-wide expectations for architecture, security, testing,
documentation, release semantics, and development workflow should be explicit
before implementation expands.

The shared `coding_standards` repository publishes immutable releases with a
versioned standards archive and companion checksum.  The latest stable release
resolved for this adoption is `v1.4.0`, whose tag resolves to commit
`b0ebcf6f3a6b19b61c68c07e46cdd12abe5a8251`.  GitHub reports the SHA-256
digest of `coding_standards.tar.gz` as
`fdd14857d9bb9efd27901f5751da3487e0df814b467928450791040b8d9e0f49`.
The companion 90-byte checksum asset is consistent with that archive digest.

ADR-000 remains the repository's foundational decision concerning capability
scope, epistemic honesty, separation of concerns, evidence-oriented reasoning,
and avoidance of over-commitment or sycophantic behavior.  This ADR does not
supersede ADR-000.

## Decision Drivers

- Establish shared engineering governance before substantive implementation.
- Make the adopted standards release and provenance independently inspectable.
- Keep standards readable in the repository rather than depending on network
  access during ordinary development and review.
- Preserve repository-specific ADRs as the proper mechanism for explicit local
  refinements or exceptions.
- Avoid silently drifting from a released standards snapshot.
- Apply language-specific guidance only where the repository actually maintains
  content in that language.
- Keep imported standards externally managed instead of editing them in place.

## Decision

The repository SHALL adopt `coding_standards@v1.4.0` from
`https://github.com/wesley-dean/coding_standards.git`.

The complete released `standards/` tree SHALL be materialized under
`doc/standards/`.  The project-root `.codingstandardrc` SHALL record the
canonical source repository, concrete released version, verified archive
SHA-256 digest, and managed destination.

Applicable standards under `doc/standards/` are governing project
requirements.  Presence alone does not make every standard applicable:

- general and cross-cutting standards apply where relevant;
- language-specific standards apply to maintained content in that language;
- `doc/standards/examples/` remains illustrative and non-normative unless a
  governing standard explicitly says otherwise; and
- accepted repository-specific ADRs and explicit local policies may refine or
  supersede imported standards for this repository.

Files under `doc/standards/` SHALL NOT be locally edited to encode
repository-specific exceptions.  Future upgrades, downgrades, repairs, or
refreshes SHALL replace the managed snapshot from a concrete upstream release
and update `.codingstandardrc` accordingly.

No permanent standards-fetching or synchronization machinery is introduced by
this decision.

## Considered Alternatives

### Continue with only the template defaults

This would avoid adding a managed standards snapshot, but it would leave
important engineering expectations implicit and would make later conformance
dependent on conversational or maintainer knowledge.  It was rejected because
the repository is at the ideal point to establish governance before
implementation.

### Track the upstream default branch

Tracking `main` would provide newer content more quickly, but it would make
the repository's governing requirements mutable and harder to audit.  It was
rejected in favor of an immutable released version with recorded provenance.

### Copy selected standards only

Copying only standards known to be immediately relevant would reduce repository
size, but it would create an ad hoc profile and complicate future upgrades.
The released standards library is therefore imported as a complete snapshot,
while applicability remains explicit.

### Edit imported standards for local exceptions

Local edits would make the snapshot diverge from its declared release and blur
provenance.  Repository-specific exceptions instead belong in accepted ADRs or
other explicit local policy.

### Add an automatic updater

A repository-local downloader, action, submodule, or synchronization target
could automate updates, but it would add machinery and change governance
implicitly when upstream changes.  Standards updates remain deliberate,
reviewable repository changes.

## Consequences

The repository gains a concrete, reviewable engineering governance baseline and
a recorded provenance chain for that baseline.  Contributors and automated
agents must review the applicable standards before changing governed content.

The repository also carries the complete standards library, including standards
that may not apply to the current codebase.  Contributors must therefore
evaluate applicability rather than assuming that presence alone creates a
requirement.

Repository-specific exceptions require explicit governance rather than edits to
the imported snapshot.  Updating the standards becomes a deliberate release
adoption operation that may introduce new requirements and therefore requires
normal review.

For the planned Kimai plugin implementation, the general architecture,
security, testing, workflow, release-governance, ADR, AI-safety, and PHP
documentation standards are expected to be relevant.  Other language-specific
standards apply only if maintained content in those languages is introduced.

## Open Questions and Follow-Ups

No additional standards-fetching mechanism is required by this decision.

Future standards releases may introduce requirements that conflict with an
accepted repository-specific ADR or an established compatibility contract.
Such conflicts must be surfaced and resolved explicitly during the adoption of
that future release.

## Related Decisions

Related to: [ADR-000: Capability Scope, Epistemic Honesty, and Separation of Concerns](ADR-000-capability-scope-and-epistemic-honesty.md)
