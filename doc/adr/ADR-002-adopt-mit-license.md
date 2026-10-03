# ADR-002: Adopt MIT License for Project-Owned Work

Date: 2026-10-02

## Status

Accepted

## Intent and Documentation Posture

This ADR records the repository's licensing policy before upstream plugin code
is imported.  The decision distinguishes the license applied to new
project-owned work from licenses and copyright notices that already govern
material obtained from other sources.

## Context

The repository was created from a template that used the CC0 1.0 Universal
public-domain dedication.  The planned implementation will incorporate and
modernize code derived from the ADK Interactive Invoice Emailer plugin, whose
upstream repository is distributed under the MIT License and carries a 2024
ADK Interactive, LLC copyright notice.

Using MIT for project-owned work creates a straightforward licensing posture
for a maintained derivative that will contain MIT-licensed upstream material.
It also keeps the project's licensing model conventional for software and makes
the obligations around preservation of copyright and permission notices
explicit.

A license change cannot revoke permissions that were already granted for
earlier material.  Content previously distributed under CC0 remains usable
under the rights already granted by CC0.  This decision governs the project's
licensing posture going forward; it does not attempt to retract or narrow
earlier grants.

## Decision Drivers

- Use a conventional open-source software license for maintained project code.
- Remain compatible with the MIT-licensed upstream plugin selected for import.
- Preserve upstream copyright, attribution, and license obligations.
- Avoid representing imported third-party code as original project-owned work.
- Establish the licensing boundary before source-code import begins.
- Keep future provenance and relicensing decisions explicit and reviewable.

## Decision

New project-owned work in this repository SHALL be distributed under the MIT
License.

The repository-root `LICENSE` file SHALL contain the MIT License with the
project copyright notice:

`Copyright (c) 2026 Wes Dean`

Repository documentation and contribution guidance SHALL describe MIT as the
license for project-owned work.

Third-party material SHALL retain the copyright, attribution, permission
notice, and other license information required by its original license.  The
repository-root MIT license does not replace or erase those notices.

When the ADK Interactive Invoice Emailer plugin is imported, its MIT provenance
SHALL be recorded explicitly.  At minimum, the import must identify the
upstream repository and imported commit, preserve the ADK Interactive
copyright and MIT permission notice for substantial copied portions, and
provide a durable provenance document such as `UPSTREAM.md`.

Material previously distributed from this repository under CC0 remains subject
to the rights already granted under CC0.  This ADR does not attempt to revoke
those grants.

Code or other material under a license whose obligations are incompatible with
this repository's intended distribution SHALL NOT be imported without an
explicit licensing review and, when consequential, an additional accepted ADR.

## Considered Alternatives

### Retain CC0 for project-owned work

CC0 is extremely permissive, but it does not align as naturally with the
notice-preservation model of the MIT-licensed code we intend to maintain as a
derivative.  Keeping two different default licensing postures would also make
the repository's provenance story harder to explain.

### Adopt the upstream ADK copyright notice as the repository license

The ADK copyright notice applies to ADK's original work, not to new work
created in this repository.  Copying that notice as though it covered all
future project-owned work would blur copyright ownership and provenance.

### Use a copyleft license

A copyleft license could be applied to new project-owned work in some
circumstances, but it would introduce additional distribution obligations and
a more complicated derivative-work analysis without a demonstrated project
need.  MIT is sufficient for the current goals.

### Defer licensing until the upstream source import

Deferral would allow implementation to begin before ownership and notice
boundaries were settled.  That would make it easier to lose provenance or
produce ambiguous commits.  The license is therefore established before code
import.

## Consequences

The repository has a conventional MIT licensing posture for new project-owned
software and documentation where that material is intended to be covered by
the project license.

Future source imports require explicit provenance review.  Upstream notices may
coexist with the repository-root MIT license, and copied third-party material
must not be represented as solely copyrighted by this project.

Previously distributed CC0 material remains available under its earlier grant,
so this change does not make historical copies more restrictive.

The upcoming ADK import will need a provenance artifact and preservation of the
upstream MIT notice.  That work belongs to the import change rather than this
licensing-only decision.

## Compatibility and Migration

No runtime behavior changes.

Existing users who received repository material under CC0 retain those rights.
New project-owned work after this decision is distributed under MIT.  Future
release artifacts and package metadata should identify MIT consistently.

## Expected Outcome

A reviewer should be able to distinguish project-owned MIT work from imported
third-party MIT work, identify the copyright holder associated with each, and
trace imported code to its upstream source without relying on conversational
history.

## Related Decisions

Related to: [ADR-001: Adopt Released Coding Standards](ADR-001-adopt-released-coding-standards.md)
