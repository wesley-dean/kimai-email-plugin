# Compatibility

## Status

This document records the compatibility target and available evidence for the
maintained plugin.

It is not a runtime compatibility certification.

## Initial Target

The first maintained implementation targets:

| Component | Initial target | Evidence status |
| --- | --- | --- |
| Kimai | 2.67.0 | Source/API review complete; runtime plugin test pending |
| PHP | 8.2 through 8.5 | Kimai 2.67.0 release declares support; plugin tests pending |
| Symfony | Kimai-managed version | Do not independently widen beyond Kimai's supported stack |
| Mail transport | Kimai/Symfony Mailer configuration | End-to-end test pending |

Kimai 2.67.0 was released on September 13, 2026.  Its release notes state
compatibility with PHP 8.2 through 8.5.

Source:
https://github.com/kimai/kimai/releases/tag/2.67.0

## Verified Kimai 2.67.0 Integration Points

The following observations have been verified against the Kimai 2.67.0 tag.

### InvoiceService

`App\Invoice\InvoiceService::getInvoiceFile(Invoice): ?SplFileInfo` exists and
returns the stored generated invoice file when it is readable.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/Invoice/InvoiceService.php

### ServiceInvoice

`App\Invoice\ServiceInvoice` still exists only as a compatibility subclass and
is explicitly deprecated since Kimai 2.56 in favor of `InvoiceService`.

New maintained plugin code must use `InvoiceService`.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/Invoice/ServiceInvoice.php

### EmailEvent and Kimai mail handling

`App\Event\EmailEvent` accepts a Symfony `Email`.
Kimai's `EmailSubscriber` handles that event and delegates to `KimaiMailer`.
`KimaiMailer` delegates to the configured Symfony mailer and supplies Kimai's
fallback From address when the message has no explicit sender.

Sources:

- https://github.com/kimai/kimai/blob/2.67.0/src/Event/EmailEvent.php
- https://github.com/kimai/kimai/blob/2.67.0/src/EventSubscriber/EmailSubscriber.php
- https://github.com/kimai/kimai/blob/2.67.0/src/Mail/KimaiMailer.php

### Invoice action extension point

Kimai 2.67.0 retains `AbstractActionsSubscriber`, which subscribes to named
`actions.*` events and supports action URLs through Kimai's action framework.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/EventSubscriber/Actions/AbstractActionsSubscriber.php

### Invoice authorization and CSRF precedent

Kimai's invoice controller requires `view_invoice` for invoice access and
specific-invoice download.  Its state-changing invoice status route uses POST
and validates a CSRF token before changing status.

The plugin should align with those boundaries rather than create a weaker
alternate path.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/Controller/InvoiceController.php

### Future invoice-update event

`InvoiceUpdatePostEvent` exists in Kimai 2.67.0 and is documented as firing
after an invoice is saved for both new and updated invoices.

It is not used by the first maintained release.  Its presence is recorded only
because it may be evaluated in a future automatic-send architecture.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/Event/InvoiceUpdatePostEvent.php

## Upstream Compatibility Claims Are Not Inherited

The ADK upstream Composer metadata declares:

- PHP `>=8.1`;
- `kimai/kimai2: ^2.0`; and
- Kimai minimum `21700`.

Those declarations are provenance data, not evidence for this maintained
plugin.  The reviewed upstream source contains deprecated and unsupported
integration assumptions, so this project must not claim broad Kimai 2.x
compatibility merely because upstream metadata did.

The initial maintained compatibility floor is therefore Kimai 2.67.0 until
tests establish otherwise.

## Intended Verification Matrix

The first release should exercise the plugin against Kimai 2.67.0 with each PHP
version that the Kimai release supports and that can be reproduced reliably in
CI or a controlled test environment:

| Kimai | PHP 8.2 | PHP 8.3 | PHP 8.4 | PHP 8.5 |
| --- | --- | --- | --- | --- |
| 2.67.0 | planned | planned | planned | planned |

A matrix cell becomes "verified" only after the plugin is installed into a
matching Kimai environment and the relevant checks pass.

## Required Compatibility Checks

At minimum, a target combination should demonstrate:

- plugin discovery and container compilation;
- `bin/console kimai:reload --env=prod`;
- route loading;
- permission registration;
- Twig template rendering;
- translation loading;
- controller authorization;
- CSRF validation;
- invoice-file resolution;
- `EmailEvent` dispatch;
- controlled mail transport behavior;
- static analysis;
- relevant unit/integration tests; and
- one end-to-end synthetic invoice send to a controlled recipient or SMTP sink.

## Compatibility Claim Policy

Documentation and package metadata must distinguish:

- **source-reviewed**: required APIs are present in inspected source;
- **CI-verified**: automated tests have run successfully against that exact
  version combination;
- **staging-verified**: the packaged plugin has been exercised in a disposable
  installation matching the target environment; and
- **production-observed**: the plugin has been used in a production environment,
  without implying that observation proves universal compatibility.

Do not convert one category into another by inference.

## Production Deployment Gate

Before claiming compatibility with a specific deployment, record:

- exact Kimai version;
- exact PHP version;
- container image tag and resolved digest when containerized;
- plugin release/version;
- mail transport type sufficient for reproducing behavior; and
- results of the staging validation.

## Extending the Compatibility Range

Support for an older or newer Kimai version requires evidence.

When adding a version:

1. inspect API differences relevant to this plugin;
2. add or update the test matrix;
3. run the plugin installation and functional checks;
4. document any conditional compatibility behavior; and
5. update package metadata only after the support claim is established.

## Related Documents

- [Architecture](architecture.md)
- [Security](security.md)
- [Upstream Provenance](../UPSTREAM.md)
- [ADR-003](adr/ADR-003-secured-manual-invoice-email-workflow.md)
