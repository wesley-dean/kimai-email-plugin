# Testing

## Purpose

This document records how Kimai Invoice Emailer is verified and which evidence
supports its compatibility and security claims.

Verification is split into source, distribution-artifact, and integration
layers.  Application logic can be tested in isolation, while packaging and
framework/security behavior require the public ZIP and a real Kimai kernel.

## Evidence Bound to Release v0.1.1

The final Phase 4 compatibility workflow completed successfully against commit:

```text
a20f7b7ac6df78261ef373bac9ba6791afa8ca28
```

The squash-merged release commit for `v0.1.1` is:

```text
b72bd5f8cecc25c6ad55ec5af3bb0cd8b770f55a
```

Both commits point to the same Git tree:

```text
20cf37fc4b95ff7708e2eaaa0b935b387c0912b8
```

This matters because test evidence is valid only for the state it actually
verifies.  The Phase 4 evidence is therefore directly applicable to the source
tree released as `v0.1.1`.

## Unit Layer

The standalone plugin Composer environment pins `kimai/kimai` 2.67.0 as a
development dependency and runs on PHP 8.2, 8.3, 8.4, and 8.5.

The unit suite verifies:

- preview performs no email dispatch;
- current invoice/customer/file/sender values are resolved;
- send re-resolves recipient state instead of trusting an earlier preview;
- one successful send dispatches exactly one `EmailEvent`;
- recipient, subject, templates, and attachment are correct;
- the plugin leaves `From` unset so `KimaiMailer` owns fallback sender policy;
- canceled invoices are rejected;
- missing recipient addresses are rejected;
- malformed recipient addresses are rejected;
- missing invoice files are rejected; and
- missing Kimai sender configuration is rejected.

The standalone layer also runs:

- `composer validate --strict --no-check-version`;
- PHPStan; and
- PHPUnit.

## Distribution Artifact Layer

The compatibility workflow builds the public release representation before
starting Kimai integration tests.

For each PHP matrix cell it:

1. reads the package version from `composer.json`;
2. builds `InvoiceEmailerBundle-<version>.zip`;
3. builds the same archive a second time;
4. compares both ZIPs byte-for-byte;
5. compares both SHA-256 sidecars byte-for-byte;
6. extracts the first ZIP directly beneath Kimai's `var/plugins/`; and
7. confirms that runtime/legal files are present while tests, documentation
   governance, CI configuration, and release scripts are absent.

The builder itself also verifies archive integrity, the required
`InvoiceEmailerBundle/` top-level directory, required runtime files, prohibited
development paths, and the SHA-256 checksum.

See [release.md](release.md) and
[ADR-004](adr/ADR-004-deterministic-release-artifacts.md).

## Kimai Integration Layer

The integration workflow checks out the exact Kimai 2.67.0 tag and installs the
generated release ZIP under:

```text
var/plugins/InvoiceEmailerBundle/
```

The integration test source remains in the repository checkout outside the
installed plugin and executes against the packaged runtime code.

The workflow then exercises the plugin against Kimai's own application, test
kernel, MySQL-backed fixtures, security system, CSRF manager, route loader, and
mail event path.

The integration layer verifies:

- Kimai reload succeeds with the plugin present;
- production dependency-container compilation succeeds;
- plugin YAML parses;
- plugin Twig templates parse;
- plugin XLIFF translations parse;
- both production plugin routes load;
- a normal administrator with invoice visibility but without `email_invoice`
  cannot reach confirmation;
- a user granted `email_invoice` but lacking `view_invoice` cannot reach
  confirmation;
- GET confirmation dispatches no email;
- customer-controlled confirmation text remains HTML-escaped;
- the send route rejects GET;
- invalid CSRF POST dispatches no email;
- one valid confirmation POST dispatches exactly one `EmailEvent`;
- the dispatched message uses the current customer recipient;
- the generated invoice is attached; and
- canceled invoices are rejected without dispatch.

## Kimai 2.67.0 Test-Harness Shims

The exact Kimai 2.67.0 tag contains a stale service definition for
`App\Importer\ImporterService` in `config/services_test.yaml`, while that
class is absent from the tagged source tree.

The integration workflow removes only that dead test-service definition before
booting Kimai's test kernel.

Kimai also deliberately skips dynamic plugin discovery when `APP_ENV=test`.
The harness therefore adds `InvoiceEmailerBundle` to the test checkout's
`config/bundles.php` only for the controller suite.

Production container and route checks do not depend on those test-only
substitutions.

## Compatibility Matrix

The final Phase 4 matrix completed successfully:

| Kimai | PHP 8.2 | PHP 8.3 | PHP 8.4 | PHP 8.5 |
| --- | --- | --- | --- | --- |
| 2.67.0 unit/static | Verified | Verified | Verified | Verified |
| 2.67.0 integration | Verified | Verified | Verified | Verified |

## Local Commands

Install development dependencies and run the standalone quality suite:

```bash
composer install
composer quality
```

Build and verify the release ZIP:

```bash
scripts/build-release.bash 0.2.0 dist
sha256sum -c dist/InvoiceEmailerBundle-0.2.0.zip.sha256
```

Inside a Kimai 2.67.0 checkout with the generated ZIP extracted beneath
`var/plugins/`, the production-facing checks include:

```bash
bin/console kimai:reload --env=prod --no-interaction
bin/console lint:container --env=prod
bin/console lint:yaml var/plugins/InvoiceEmailerBundle/Resources/config --parse-tags
bin/console lint:twig var/plugins/InvoiceEmailerBundle/Resources/views --show-deprecations
bin/console lint:xliff var/plugins/InvoiceEmailerBundle/Resources/translations
bin/console debug:router invoice_emailer_confirm --env=prod
bin/console debug:router invoice_emailer_send --env=prod
```

The controller integration suite additionally needs Kimai's test-kernel shims
described above and a disposable MySQL database.  The GitHub Actions workflow is
the maintained executable definition of that harness.

## Published Release SMTP Staging Layer

Phase 7 adds a separate read-only workflow for published releases.

The staging workflow:

1. resolves a published semantic-version tag;
2. downloads the ZIP and SHA-256 sidecar from GitHub Releases;
3. verifies the sidecar and GitHub's published asset digest;
4. installs the downloaded ZIP into a clean Kimai 2.67.0 checkout;
5. verifies production plugin loading and routes;
6. downloads Mailpit v1.31.3 and verifies its published Linux amd64 SHA-256;
7. changes only Kimai's disposable PHPUnit mail transport from `null://` to
   local SMTP;
8. executes the successful manual confirmation-to-POST controller path; and
9. queries Mailpit's API to verify one captured message and the exact invoice
   attachment bytes.

The pull-request trigger exercises this harness against the latest already
published release.  Published-release events validate the newly released
artifact automatically.

See [staging-validation.md](staging-validation.md).

## Mail Boundary

The normal compatibility matrix uses a non-delivering mail transport.

The Phase 7 staging layer crosses an actual SMTP protocol boundary to a local,
controlled Mailpit sink.  This establishes SMTP acceptance by that sink for the
tested packaged release.  It still does not establish Internet delivery,
deliverability through filtering systems, recipient mailbox receipt, or
recipient reading.

## Evidence Boundaries

Passing the unit suite alone does not establish Kimai runtime compatibility.

Passing source-level integration alone also does not establish that the public
distribution transformation preserved behavior.  Beginning with Phase 6, the
compatibility workflow installs the generated ZIP before exercising Kimai.

Passing production container/template/route checks against that installed
artifact establishes plugin loading for the tested combination, while the
controller integration suite establishes the tested authorization, CSRF,
rendering, and dispatch behavior.

Neither layer establishes:

- external SMTP delivery;
- deliverability through spam or policy filters;
- recipient mailbox receipt;
- production-specific filesystem permissions; or
- compatibility with untested Kimai versions.

See [compatibility.md](compatibility.md) for the support claim and
[security.md](security.md) for the threat model.
