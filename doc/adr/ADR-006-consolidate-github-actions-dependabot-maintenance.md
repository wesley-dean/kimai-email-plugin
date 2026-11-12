# ADR-006: Consolidate GitHub Actions Dependabot Version Updates

Date: 2026-11-12

## Status

Accepted

## Decision

Keep the repository's weekly GitHub Actions Dependabot version-update
schedule and seven-day cooldown. Replace the `minor-and-patch` routine
version-update group with a single wildcard `actions-version-updates`
group, explicitly using `applies-to: version-updates` and
`patterns: ["*"]`, without restricting `update-types`. Eligible major,
minor, and patch version updates may therefore be proposed together.

Security updates remain independent of routine version-update grouping and
must not be delayed until the weekly cycle. The current
`dependabot/fetch-metadata` non-major-only auto-merge condition remains
unchanged. A group containing any major update must be reviewed manually
against its highest-risk component and relevant compatibility evidence.
Do not close existing Dependabot PRs until their updates are demonstrably
superseded and verified.

## Context and Drivers

The existing Dependabot configuration schedules GitHub Actions version
updates weekly, includes a seven-day cooldown, and groups minor/patch
updates while producing separate major-version proposals. This increases
maintainer review volume across multiple repositories. The verified
`coding_standards@v3.2.0` release describes a balanced per-ecosystem model
that allows grouping all eligible routine SemVer categories with stronger
review for major-containing groups.

This project is a Kimai 2 invoice-mail plugin. Its accepted ADRs govern
authentication, authorization, CSRF boundaries, invoice-to-attachment
selection, opt-in automatic sending, tested Kimai compatibility, and
deterministic distribution archives. GitHub Actions version changes could
affect CI evidence, release publishing, and artifact provenance; grouping
updates must not waive those security and correctness constraints.

## Assumptions and Constraints

- `github-actions` is the currently configured Dependabot ecosystem.
- The weekly cadence and seven-day cooldown remain in effect.
- The existing non-major auto-merge condition remains unchanged and must
  not be broadened to include grouped major updates.
- GitHub's effective required checks, CODEOWNERS requirements, branch
  protections, and Dependabot security settings require independent
  verification; the repository YAML does not establish them.
- No additional workflow privileges or access to invoice data, mail
  credentials, release tokens, or deployment environments is authorized.
- No change to Kimai compatibility claims or released ZIP contents follows
  automatically from these maintenance settings.

## Alternatives Considered

1. **Keep the current minor/patch group.** Rejected as the default because
   it maintains separate major PRs without changing their required scrutiny.
2. **Group eligible major, minor, and patch routine updates.** Selected to
   reduce fragmentation while requiring targeted review for major groups.
3. **Use a monthly cadence or longer cooldown.** Rejected for now because
   delaying routine visibility has not been justified by activity evidence.
4. **Auto-merge major-containing groups.** Rejected because breakage or new
   privileges in GitHub Actions could affect release integrity and delivery.
5. **Close existing PRs once the wildcard group is configured.** Rejected:
   configuration does not demonstrate that old updates are replaced or tested.

## Consequences and Failure Model

Routine version-update PR volume may decrease, but no exact reduction is
promised. Resolver conflicts, unsupported versions, or separate ecosystems
may still create multiple PRs. Grouping can make failures harder to
attribute, and a narrow documented exception may be appropriate for a
high-risk dependency or incompatible upgrade.

Review the release notes, changed action privileges, workflows exercised,
relevant CI results, and release-publication implications of each grouped
dependency. A successful linter does not prove Kimai compatibility, runtime
authorization safety, or published ZIP correctness.

Retain existing Dependabot PR #18 until replacement coverage and evidence
have been observed. Preserve the existing CI event model and auto-merge
guard; do not import optional upstream CI-gate examples automatically.

## Expected Outcomes and Verification

Observe later scheduled Dependabot runs and compare actual PR count,
dependency coverage, test failures, and review time. Confirm that grouped
major updates are held for review. Verify effective security-update
settings and GitHub-side protections independently.

This change modifies only dependency-maintenance configuration and
governance; it does not change PHP implementation, invoice/mail behavior,
Kimai integration, deployment procedures, or release tooling.

## Related Decisions

ADR-001 governs the released, externally managed shared standards
snapshot. ADR-003 governs the secured manual invoice mail path,
ADR-004 deterministic release packaging, and ADR-005 explicit opt-in
creation-time automation. None are superseded by this decision.
