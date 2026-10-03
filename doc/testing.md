# Testing

## Purpose

This document records how the maintained plugin is verified and which evidence
supports compatibility and security claims.

Tests are divided into two layers because the plugin has both application logic
that can be tested in isolation and framework/security behavior that is only
meaningful inside a real Kimai kernel.

## Unit Layer

The standalone plugin Composer environment pins `kimai/kimai` 2.67.0 as a
development dependency and runs the unit suite on PHP 8.2, 8.3, 8.4, and 8.5.

The unit suite currently verifies:

- preview performs no email dispatch;
- current invoice/customer/file/sender values are resolved;
- send re-resolves recipient state instead of trusting an earlier preview;
- one successful send dispatches exactly one `EmailEvent`;
- the message uses the expected recipient, subject, templates, and attachment;
- the plugin deliberately leaves `From` unset for `KimaiMailer`;
- canceled invoices are rejected;
- missing recipient addresses are rejected;
- malformed recipient addresses are rejected;
- missing invoice files are rejected; and
- missing Kimai sender configuration is rejected.

The standalone layer also runs Composer validation and PHPStan.

## Kimai Integration Layer

The integration workflow checks out the exact Kimai 2.67.0 tag and installs the
current plugin checkout under:

```text
var/plugins/InvoiceEmailerBundle/
```

It then executes the plugin against Kimai's own test kernel and database reset
mechanism.

### Kimai 2.67.0 test-container shim

The exact Kimai 2.67.0 tag contains a stale service definition for
`App\Importer\ImporterService` in `config/services_test.yaml`, while that
class is absent from the tagged source tree.  As a result,
`lint:container --env=test` fails in unmodified Kimai 2.67.0 before evaluating
this plugin.

The compatibility workflow removes only that dead test-service definition before
booting the integration test kernel.  Production container compilation is still
validated against the unmodified Kimai application configuration with
`lint:container --env=prod`.

Kimai also deliberately skips dynamic plugin discovery when `APP_ENV=test`.
The harness therefore adds `InvoiceEmailerBundle` to the test checkout's
`config/bundles.php` only for the controller suite.  Production route
discovery is verified separately with `debug:router --env=prod`, where Kimai
uses its normal dynamic plugin-loading path.

These shims are part of the test harness only and do not modify the installed
plugin or production Kimai configuration.

The integration layer verifies:

- Kimai cache/plugin reload succeeds;
- dependency-container linting succeeds;
- plugin YAML parses;
- plugin Twig templates parse;
- plugin XLIFF translations parse;
- both plugin routes load;
- a normal administrator with invoice visibility but without `email_invoice`
  cannot reach confirmation;
- a user granted `email_invoice` but lacking `view_invoice` cannot reach
  confirmation;
- GET confirmation dispatches no email;
- customer-controlled confirmation text remains HTML-escaped;
- the send route rejects GET;
- invalid CSRF POST dispatches no email;
- one valid confirmation POST dispatches exactly one `EmailEvent`;
- the dispatched message uses the current customer recipient and generated
  invoice attachment; and
- canceled invoices are rejected without dispatch.

## Compatibility Matrix

The GitHub Actions workflow runs both verification layers for every PHP version
that Kimai 2.67.0 declares compatible:

| Kimai | PHP 8.2 | PHP 8.3 | PHP 8.4 | PHP 8.5 |
| --- | --- | --- | --- | --- |
| 2.67.0 | CI | CI | CI | CI |

A matrix entry means the workflow executes that combination.  It becomes
verified only when the workflow completes successfully for the commit or
release being evaluated.

## Commands

Standalone unit and static checks:

```bash
composer install
composer quality
```

Inside a Kimai 2.67.0 checkout with this repository copied to
`var/plugins/InvoiceEmailerBundle`:

```bash
bin/console kimai:reload --env=test --no-interaction
bin/console lint:container --env=prod
bin/console lint:yaml var/plugins/InvoiceEmailerBundle/Resources/config --parse-tags
bin/console lint:twig var/plugins/InvoiceEmailerBundle/Resources/views --show-deprecations
bin/console lint:xliff var/plugins/InvoiceEmailerBundle/Resources/translations
vendor/bin/phpunit var/plugins/InvoiceEmailerBundle/tests/Integration
```

The integration test suite uses Kimai's own PHPUnit bootstrap, which resets the
test database through `kimai:reset:test`.

## Evidence Boundaries

Passing unit tests does not establish Kimai runtime compatibility.

Passing container/template/route checks establishes that the plugin loads in the
tested Kimai/PHP combination, but the supported manual-send security claims also
require the controller integration suite to pass.

The configured null mail transport proves dispatch into Kimai's mail pipeline,
not external SMTP delivery or recipient receipt.

A later staging phase should still exercise a packaged release with a controlled
SMTP sink or recipient before production deployment.
