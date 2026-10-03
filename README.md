# Kimai Invoice Emailer

Kimai Invoice Emailer adds a secured manual workflow for emailing an existing
Kimai invoice to the email address stored on its customer.  It also provides an
optional creation-time automatic-send mode for authorized users.

Manual sending remains human-confirmed through a CSRF-protected POST.
Automatic sending is disabled by default, triggers only when a new invoice is
created, and preserves the same invoice/customer authorization boundaries.
Neither mode changes invoice status, retries failed sends automatically, or
claims that mail accepted by the configured transport was delivered to the
recipient.

## Status

The current maintained line targets Kimai 2.67.0.

The maintained package format is CI-verified against Kimai 2.67.0 with PHP 8.2,
8.3, 8.4, and 8.5.  For pushes to `main`, the post-merge release gate builds
the deterministic ZIP, installs that packaged representation into Kimai,
exercises plugin discovery, container compilation, routes, templates,
translations, authorization, CSRF handling, invoice-file lookup, and
email-event dispatch, then publishes only those validated bytes.

The normal compatibility matrix intentionally uses a non-delivering mail
transport.  After publication, the read-only staging workflow validates the
published artifact through a real SMTP connection to a controlled Mailpit sink.
A successful staging run establishes SMTP acceptance by that sink, not Internet
delivery, mailbox receipt, or recipient reading.  Version-specific staging
evidence is retained in [Staging Validation](doc/staging-validation.md).

See [Compatibility](doc/compatibility.md) and [Testing](doc/testing.md) for the
evidence boundary.

## Features

- Adds an "Email invoice" action to eligible invoice rows.
- Supports optional creation-time automatic sending, disabled by default.
- Requires an explicit confirmation GET before each manual send.
- Performs the email side effect only through a CSRF-protected POST.
- Requires the dedicated `email_invoice` permission.
- Also requires normal `view_invoice` authorization for the specific invoice.
- Retains applicable customer/object access checks.
- Uses Kimai's current `InvoiceService` to resolve the generated invoice file.
- Builds a Symfony `TemplatedEmail`.
- Dispatches through Kimai's `EmailEvent` and `KimaiMailer` integration.
- Re-resolves send-authoritative state at POST time instead of trusting values
  shown on the confirmation page.
- Keeps the initial email intentionally sparse and locale-neutral.
- Preserves upstream MIT provenance for concepts derived from the ADK plugin.

## Deliberate Non-Features

The current maintained release does not provide:

- automatic sending on ordinary invoice updates or status changes;
- automatic invoice status changes;
- automatic retry;
- bulk sending;
- arbitrary additional recipients;
- delivery receipts;
- post-send `email_sent_date` metadata; or
- exactly-once delivery semantics.

Those omissions are architectural decisions, not unfinished switches hidden in
configuration.  See
[ADR-003](doc/adr/ADR-003-secured-manual-invoice-email-workflow.md) and
[ADR-005](doc/adr/ADR-005-opt-in-creation-time-automatic-invoice-email.md).

## Requirements

The verified compatibility target is:

| Component | Supported target |
| --- | --- |
| Kimai | 2.67.0 |
| PHP | 8.2, 8.3, 8.4, 8.5 |
| Mail | Kimai's configured Symfony Mailer transport |

The plugin does not create database tables and does not install frontend assets.

Support for other Kimai versions must be established by evidence rather than
inferred from upstream metadata.  See [Compatibility](doc/compatibility.md).

## Installation

Kimai plugins are installed below `var/plugins/`, and Kimai expects this bundle
to live at exactly:

```text
var/plugins/InvoiceEmailerBundle/
```

The directory must contain `InvoiceEmailerBundle.php` at its root.

Download both release assets for the version you intend to install:

```text
InvoiceEmailerBundle-<version>.zip
InvoiceEmailerBundle-<version>.zip.sha256
```

Verify the checksum before extraction.  For example, for `v0.2.0`:

```bash
VERSION=0.2.0
sha256sum -c "InvoiceEmailerBundle-${VERSION}.zip.sha256"
```

For a fresh installation, extract the ZIP directly beneath Kimai's
`var/plugins/` directory:

```bash
unzip -q "InvoiceEmailerBundle-${VERSION}.zip" -d /path/to/kimai/var/plugins
cd /path/to/kimai
bin/console kimai:reload --env=prod
```

The release ZIP already contains the final `InvoiceEmailerBundle/` directory,
so no post-extraction rename is required.

For an upgrade, remove the existing
`var/plugins/InvoiceEmailerBundle/` directory before extracting the new ZIP.
This prevents files removed by a newer release from surviving an in-place
overlay.

Kimai's plugin documentation requires the exact bundle directory name and a
cache rebuild after installation.  This plugin has no database-install command
and no asset-install step.

Official Kimai plugin-management documentation:
https://www.kimai.org/documentation/plugin-management.html

See [Release Packaging](doc/release.md) for the artifact contract and local
build instructions.

### Docker installations

When Kimai runs in a container, the plugin still belongs under Kimai's
`var/plugins/` directory.  The directory is commonly provided through a volume
or bind mount.

Official Kimai Docker Compose documentation:
https://www.kimai.org/documentation/docker-compose.html

After the plugin is visible inside the container, run the production reload
command in that Kimai container.

## Permissions

The plugin introduces the `email_invoice` permission.

It is granted to `ROLE_SUPER_ADMIN` by default.  Kimai administrators may
assign it to other roles through Kimai's normal permission-management
interface.

The custom permission is intentionally insufficient by itself.  A user must
also be authorized to view the specific invoice and must satisfy applicable
customer/object access rules.

If the invoice action is not visible, verify all of the following:

- the invoice is not canceled;
- the current user has `email_invoice`;
- the current user has `view_invoice` for that invoice; and
- the current user may access the invoice customer.

## Configuration

The plugin has no plugin-specific configuration file.

Creation-time automatic sending is controlled by one environment variable:

```text
INVOICE_EMAILER_AUTO_SEND=1
```

The variable is optional and defaults to disabled.  When enabled, only
`InvoiceCreatedEvent` is observed.  The current authenticated Kimai user must
hold `email_invoice`, `view_invoice` for the created invoice, and applicable
customer access.  Automatic failures are logged and leave the created invoice
available for the normal manual-send recovery path.

The plugin otherwise relies on existing Kimai state:

- the customer's email address;
- the generated invoice document;
- Kimai's configured mail sender;
- Kimai's configured Symfony Mailer transport; and
- Kimai's permission and object-access model.

A missing customer email, unreadable generated invoice, canceled invoice, or
missing Kimai sender configuration prevents the send.

## Usage

### Manual send

1. Open Kimai's invoice list.
2. Choose the "Email invoice" action for an eligible invoice.
3. Review the confirmation page.  It shows the invoice number, customer,
   recipient, sender, subject, and attachment filename.
4. Submit the confirmation form.
5. Kimai submits the message through its configured mail system.

The confirmation page is observational.  Merely opening it does not send the
invoice.

The success message means the invoice email was submitted through Kimai's mail
pipeline.  It does not mean the remote server accepted the message, the message
reached the recipient's mailbox, or the recipient read it.

### Automatic send

When `INVOICE_EMAILER_AUTO_SEND=1`, creating a new invoice through an
authenticated authorized Kimai user makes one best-effort submission attempt
after Kimai has persisted the invoice and generated its file.  Ordinary invoice
updates do not trigger automatic resend.  Failures do not roll back invoice
creation and are recovered through the existing manual-send workflow.

## Email Content

The first maintained message intentionally avoids duplicating financial values
or locale-sensitive invoice data in the email body.

The subject contains the invoice number.  The body states that the invoice is
attached.  The attachment is the already-generated Kimai invoice document.

## Security Model

The security-sensitive boundary is the external transmission of an invoice
document.

The maintained workflow uses:

- authentication;
- the dedicated `email_invoice` permission;
- specific-invoice `view_invoice` authorization;
- customer/object authorization;
- a side-effect-free GET confirmation step;
- a per-invoice CSRF-protected POST step for manual sends;
- an opt-in creation-only event boundary for automatic sends;
- current authoritative recipient and file resolution at send time; and
- Kimai's own mail integration rather than a parallel transport configuration.

See [Security Model](doc/security.md) for the security posture and evidence
boundaries.  See [STRIDE Threat Model](doc/thread_model.md) for the detailed
threat register.  See [Security Policy](SECURITY.md) for vulnerability
reporting.

## Testing

The repository has two automated verification layers.

The standalone layer runs Composer validation, PHPStan, and unit tests against
Kimai 2.67.0 on PHP 8.2 through 8.5.

The integration layer builds the deterministic release ZIP, verifies a
byte-identical rebuild, installs that packaged artifact into the exact Kimai
2.67.0 source tree, and exercises the real Kimai kernel, MySQL-backed fixtures,
authorization, CSRF handling, plugin routes, templates, translations, and
email-event dispatch.

Run the standalone checks with:

```bash
composer install
composer quality
```

See [Testing](doc/testing.md) for the integration harness, the Kimai 2.67.0
test-environment shims, and the exact evidence boundary.

## Architecture and Governance

The maintained design is documented in:

- [Architecture](doc/architecture.md)
- [Architecture Decision Records](doc/adr/README.md)
- [Decision Summary](doc/decisions.md)
- [Security Model](doc/security.md)
- [STRIDE Threat Model](doc/thread_model.md)
- [Compatibility](doc/compatibility.md)
- [Testing](doc/testing.md)
- [Release Packaging](doc/release.md)
- [Staging Validation](doc/staging-validation.md)
- [Upstream Provenance](UPSTREAM.md)

Repository work is also governed by the released coding standards committed
under `doc/standards/`.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Upstream Provenance

This project substantially modernizes concepts from the MIT-licensed ADK
Interactive Invoice Emailer plugin.  The selected provenance base is commit:

```text
da0a4c7eb38b0d1d99d89c4c3302776e5ab4d07f
```

The maintained implementation is not a verbatim continuation of the upstream
architecture.  Known unsafe, obsolete, or defective behavior was deliberately
excluded.

See [UPSTREAM.md](UPSTREAM.md) for the provenance record.

## Support

For usage problems or reproducible defects, see [SUPPORT.md](SUPPORT.md).

For security vulnerabilities, use the private reporting path documented in
[SECURITY.md](SECURITY.md).

## Contributing

Contributions are welcome.  Read [CONTRIBUTING.md](CONTRIBUTING.md),
[AGENTS.md](AGENTS.md), the applicable standards under `doc/standards/`, and
the accepted ADRs before changing governed behavior.

## License

Project-owned work is licensed under the MIT License.  Third-party material
retains its original copyright and license obligations.

See [LICENSE](LICENSE) and [UPSTREAM.md](UPSTREAM.md).
