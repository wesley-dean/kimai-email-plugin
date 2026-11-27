# ADR-007: Move Routine Dependency Updates to Central Renovate

Date: 2026-11-26

## Status

Accepted

## Decision

This repository SHALL enroll in the centrally managed, Jenkins-run Renovate
service using a minimal root `renovate.json`. Scheduled routine GitHub Actions
Dependabot version-update PR generation SHALL be disabled through
`open-pull-requests-limit: 0` in the existing ecosystem entry. All other
Dependabot configuration is retained, including the distinction between
version updates and independently triggered security updates.

The existing Dependabot-only, non-major auto-merge mechanism remains scoped
to `dependabot[bot]`. Renovate must continue to respect required checks,
human code-owner review and repository protections; neither automatic
approval nor bypassing review is authorized.

This ADR partially supersedes ADR-006's selection of Dependabot as the
active routine-update provider, its active weekly grouping policy and its
rejection of immediate routine PR retirement. ADR-006's separate security
response, risk-based review, and release trust boundaries remain governing.

## Context and Constraints

The maintainer has established central Renovate orchestration in Jenkins
for `wesley-dean/*`, including scheduling and signing. Continued scheduled
Dependabot update PRs would duplicate that provider. The repository-specific
trust boundaries include invoice authorization, CSRF, email delivery, compatibility tests and ZIP release packaging. This change SHALL NOT modify
application behavior, source/test contracts, artifact formats, token permissions,
published releases, or imported shared coding standards.

## Assumptions

- The existing Jenkins Renovate runner discovers repositories explicitly
  enrolled through root `renovate.json`.
- The maintainer provisions machine account access during PR approval.
- Native auto-merge remains gated by required CI and human code-owner approval.
- Dependabot vulnerability alerts and security updates remain independently
  enabled through GitHub settings.
- The migration PR itself is not evidence of successful Renovate execution.

## Alternatives Considered

1. Keep scheduled Dependabot version updates alongside Renovate. Rejected:
   duplicate PR generation and review work.
2. Delete Dependabot configuration entirely. Rejected: preserving ecosystem
   metadata and independent security-update operations is preferable.
3. Install a repository-specific Renovate Action and credentials. Rejected:
   central orchestration already provides the required workflow.
4. Broaden Dependabot auto-merge to include Renovate. Rejected: this would
   weaken bot identity and review boundaries.
5. Convert every verified SHA pin to a moving release ref. Rejected:
   immutable verified pins should remain intact.

## Migration and Consequences

Add minimal Renovate enrollment, disable routine Dependabot version
proposals, normalize existing GitHub Actions SHA annotations when upstream
tag-to-commit mappings are verifiable, and update maintained documentation.
Where a SHA cannot be matched to a release, it may be replaced by a valid
`@version` GitHub Actions ref and its stale `# pin@` annotation removed,
so Renovate can resolve it in a subsequent PR. This is an explicitly
documented temporary supply-chain downgrade, and must be reviewed before
using the moving ref in a privileged publication path.

After the migration PR is created, non-security Dependabot version PRs may
be closed with an explanatory comment linking the migration. Security or
ambiguous PRs remain open. Neither successful future pin restoration nor a
subsequent Jenkins run is asserted by this repository change.

## Risks and Mitigations

The central Renovate account gains PR-writing authority. Upstream actions,
dependencies, credentials, and write-capable release workflows remain
supply-chain attack surfaces. Preserve immutable verified pins where possible,
required checks, human approval, scoped permissions, and independent
security-update handling. When pin fallback is required, track the temporarily
moving ref in the PR description for further Renovate review.

## Related Decisions

ADR-006 governs the former routine update approach and retains security
and review requirements. All other accepted repository ADRs governing
runtime contracts, compatibility, documentation, and release artifacts remain
in force.
