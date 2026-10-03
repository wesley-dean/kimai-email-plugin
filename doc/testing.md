# Testing

## Purpose

This document records how Kimai Invoice Emailer is verified and which evidence
supports its compatibility and security claims.

Tests are split into two layers because the plugin contains application logic
that can be tested in isolation and framework/security behavior that is only
meaningful inside a real Kimai kernel.

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

## Kimai Integration Layer

The integration workflow checks out the exact Kimai 2.67.0 tag and installs the
current plugin checkout under:

```text
var/plugins/InvoiceEmailerBundle/
```

It then exercises the plugin against Kimai's own application, test kernel,
MySQL-backed fixtures, security system, CSRF manager, route loader, and mail
event path.

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

Inside a Kimai 2.67.0 checkout with the repository copied to
`var/plugins/InvoiceEmailerBundle/`, the production-facing checks include:

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

## Mail Boundary

The integration workflow uses a non-delivering mail transport.

This proves message construction and dispatch through Kimai's mail path.  It
does not prove external SMTP acceptance, mailbox delivery, or recipient receipt.

A later production-equivalent validation phase should exercise the packaged
release with a controlled SMTP sink or controlled recipient.

## Evidence Boundaries

Passing the unit suite alone does not establish Kimai runtime compatibility.

Passing production container/template/route checks establishes plugin loading
for the tested combination, while the controller integration suite establishes
the tested authorization, CSRF, rendering, and dispatch behavior.

Neither layer establishes:

- external SMTP delivery;
- deliverability through spam or policy filters;
- recipient mailbox receipt;
- production-specific filesystem permissions; or
- compatibility with untested Kimai versions.

See [compatibility.md](compatibility.md) for the support claim and
[security.md](security.md) for the threat model.
