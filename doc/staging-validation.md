# Published Release Staging Validation

## Purpose

This document defines Phase 7 validation for Kimai Invoice Emailer.

The normal compatibility matrix proves source behavior and verifies the
deterministic package before publication.  The staging layer starts from the
other side of the release boundary: it downloads the already-published GitHub
Release assets and verifies that those public bytes can be installed and can
complete the manual invoice-email path through an actual SMTP protocol
connection.

This is evidence for a disposable production-like environment.  It is not a
claim about Internet mail delivery or a specific production deployment.

## Evidence Levels

The project distinguishes:

- **CI-verified**: automated source/package checks completed against an exact
  Kimai/PHP combination;
- **staging-verified**: the published package was installed into a disposable
  production-like environment and completed the controlled SMTP scenario; and
- **production-observed**: the package was used in a real production
  deployment.

A lower level is not silently promoted to a higher one.

## Published Artifact Boundary

The staging workflow does not rebuild the plugin.

It downloads these files from GitHub Releases:

```text
InvoiceEmailerBundle-<version>.zip
InvoiceEmailerBundle-<version>.zip.sha256
```

The workflow verifies:

1. the semantic release tag format;
2. the SHA-256 sidecar;
3. the ZIP integrity;
4. the ZIP digest reported by the GitHub Release asset API; and
5. the required Kimai bundle entry point after extraction.

This binds staging evidence to the public artifact users can download.

## Staging Environment

The maintained staging target is:

| Component | Staging target |
| --- | --- |
| Kimai | 2.67.0 |
| PHP | 8.4 |
| Database | disposable MySQL service |
| Plugin | downloaded published release ZIP |
| SMTP server | Mailpit 1.31.3 on localhost |
| Mailpit binary | published Linux amd64 asset with pinned SHA-256 |

PHP 8.4 is a representative staging runtime.  PHP 8.2 through 8.5 remain
covered by the full compatibility matrix.

Mailpit is downloaded from its upstream v1.31.3 release and verified against
the published Linux amd64 SHA-256:

```text
e98b9a8d9622417a6988b736f7d3246e94bad4fed48ad3c604e60d72569f8cc7
```

The Mailpit HTTP API binds only to localhost, and SMTP binds only to localhost.

## Manual-Send Scenario

The staging workflow reuses the maintained successful controller scenario:

`testConfirmedPostDispatchesExactlyOneEmailEvent`

Kimai 2.67.0 hard-codes `null://null` for the Symfony mailer under
`when@test`, so changing `MAILER_URL` alone is insufficient.  The disposable
staging checkout changes only that test-environment DSN back to
`%env(MAILER_URL)%` and sets the PHPUnit `MAILER_URL` value to
`smtp://127.0.0.1:1025`.

Production mail configuration remains unmodified.

The scenario still performs the real plugin sequence:

1. authenticate as an authorized Kimai user;
2. create a database-backed invoice and generated invoice file;
3. request the side-effect-free confirmation page;
4. submit the CSRF-protected POST form;
5. dispatch the plugin's `EmailEvent`;
6. allow Kimai's `KimaiMailer` to submit the message over SMTP; and
7. redirect after submission.

## SMTP Sink Assertions

After PHPUnit completes, the verifier queries Mailpit's v1 API.

It requires:

- exactly one captured SMTP message;
- exactly one recipient in the reserved `example.com` domain;
- sender `kimai@example.com`;
- a subject beginning with `Invoice `;
- the maintained invoice-email body text;
- exactly one attachment; and
- attachment bytes exactly equal to the deterministic integration-test invoice
  payload.

The final attachment comparison verifies that the generated invoice crossed the
SMTP boundary rather than merely that an email envelope was accepted.

## Trigger Model

The workflow runs in three modes.

### Pull requests

Changes to the staging workflow, verifier, or successful controller scenario
exercise the staging harness against the latest already-published release.

A pull-request run proves the harness can validate those published bytes.  The
run may be recorded as staging evidence only after its exact release target and
successful result are reviewed.

### Release publication

Every newly published release automatically triggers the staging workflow.

This is the normal ongoing staging gate.

### Manual dispatch

A maintainer may explicitly validate a published semantic-version tag.  If the
tag input is blank, the latest published release is used.

## Trust Boundary

The staging workflow has read-only repository permissions.

It can download release assets and source needed for the test harness, but it
cannot create, modify, or delete a release.  The workflow does not execute with
release-publication credentials.

Mailpit is also isolated from external mail delivery.  It accepts the SMTP
message locally and does not relay it to the Internet.

## What Staging Verification Establishes

A successful staging run establishes that the tested published ZIP:

- downloads intact from GitHub Releases;
- matches its published checksum and GitHub asset digest;
- installs into the required Kimai bundle location;
- loads successfully in Kimai 2.67.0;
- exposes the maintained routes;
- completes the authorized manual-send flow;
- is accepted by a real SMTP server; and
- transmits the expected invoice attachment bytes.

## What It Does Not Establish

Staging verification does not establish:

- compatibility with an untested Kimai version;
- compatibility with a production-specific filesystem or reverse proxy;
- successful authentication to a production SMTP provider;
- Internet mail routing;
- spam-filter acceptance;
- mailbox delivery;
- recipient receipt or reading; or
- exactly-once delivery.

Those claims require their own evidence.

## Current Evidence

Release `v0.2.0` is already CI-verified as the first packaged release.
Published-release SMTP staging evidence will be recorded here after the Phase 7
workflow completes successfully.

## Related Documents

- [Compatibility](compatibility.md)
- [Testing](testing.md)
- [Release Packaging](release.md)
- [Security](security.md)
- [ADR-003](adr/ADR-003-secured-manual-invoice-email-workflow.md)
- [ADR-004](adr/ADR-004-deterministic-release-artifacts.md)
