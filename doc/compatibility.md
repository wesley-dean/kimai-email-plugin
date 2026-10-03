# Compatibility

## Status

Kimai Invoice Emailer has a narrow, evidence-based compatibility claim.

The maintained package format is **CI-verified** against the exact Kimai 2.67.0
source tag on PHP 8.2, 8.3, 8.4, and 8.5.  Published releases are created from
those validated bytes by the isolated release pipeline described below.

The `v0.2.0` release tag points to commit
`5a79f1281145481991109d185df66ce7760f2e9c`.  Main-branch compatibility run
`37088809507` completed successfully across the full unit/static and packaged
integration matrix before the isolated publisher created the release.

The published ZIP asset is:

```text
InvoiceEmailerBundle-0.2.0.zip
sha256:ed558a1a2809b22239bd7fbfd75c6e733daebce4a60461c57e8bea30a945178a
```

The final green compatibility run executed against PR-head commit
`a20f7b7ac6df78261ef373bac9ba6791afa8ca28`.  That commit and the
squash-merged `v0.1.1` commit
`b72bd5f8cecc25c6ad55ec5af3bb0cd8b770f55a` have the same Git tree:

```text
20cf37fc4b95ff7708e2eaaa0b935b387c0912b8
```

The evidence is therefore bound to the exact source tree released as
`v0.1.1`.

This is not yet a production-delivery certification.  The integration matrix
uses a non-delivering mail transport and does not establish external SMTP
delivery or recipient receipt.

## Phase 6 Distribution Validation

Beginning with the Phase 6 packaging change, the compatibility workflow builds
the release-equivalent `InvoiceEmailerBundle-<version>.zip`, verifies a
byte-identical rebuild and SHA-256 sidecar, extracts that artifact directly
beneath Kimai's `var/plugins/`, and executes the existing integration suite
against the packaged runtime code.

For pushes to `main`, release publication is a final stage of the same
compatibility workflow.  A read-only release-candidate job depends directly on
the successful PHP 8.2 through 8.5 unit and integration matrices and uploads
only validated release bytes.  A separate write-capable publish job does not
check out or execute repository source; it re-verifies those bytes before
creating the semantic-version tag and GitHub release.

Release `v0.2.0` completed the first packaged-release gate successfully.
Release `v0.2.1` was subsequently produced by the same validated release
pipeline and contains both the deterministic ZIP and SHA-256 sidecar.

See [release.md](release.md) and
[ADR-004](adr/ADR-004-deterministic-release-artifacts.md).

## Verified Target

| Component | Verified target | Evidence |
| --- | --- | --- |
| Kimai | 2.67.0 | CI-verified |
| PHP | 8.2 | CI-verified |
| PHP | 8.3 | CI-verified |
| PHP | 8.4 | CI-verified |
| PHP | 8.5 | CI-verified |
| Symfony | Kimai-managed 2.67.0 dependency set | CI-verified through Kimai |
| Mail dispatch | Kimai `EmailEvent` / `KimaiMailer` path | CI-verified |
| SMTP transport to controlled sink | v0.2.0 staging-verified | Phase 7 |
| External Internet delivery | Not claimed | Outside current validation |

Kimai 2.67.0 was released on September 13, 2026 and declares PHP 8.2 through
8.5 compatibility.

Source:
https://github.com/kimai/kimai/releases/tag/2.67.0

## Verified Runtime Checks

For every PHP version in the matrix, the Phase 4 workflow:

- installs standalone development dependencies;
- validates Composer metadata;
- runs PHPStan;
- runs the unit suite;
- checks out the exact Kimai 2.67.0 tag;
- builds the deterministic release ZIP twice and compares the outputs;
- verifies the release checksum;
- installs the packaged plugin under
  `var/plugins/InvoiceEmailerBundle/`;
- reloads Kimai with the plugin present;
- validates the production dependency container;
- lints plugin YAML;
- lints plugin Twig templates;
- lints plugin XLIFF translations;
- verifies both plugin routes under the production environment; and
- runs the controller integration suite with Kimai's real test kernel and MySQL
  fixtures.

The controller suite verifies authorization boundaries, CSRF rejection,
side-effect-free GET behavior, Twig escaping, one-event dispatch for a valid
POST, current recipient use, attachment use, and canceled-invoice rejection.

See [testing.md](testing.md) for the executable evidence map.

## Kimai 2.67.0 Test-Harness Conditions

Two properties of the exact Kimai 2.67.0 tag affect integration testing.

First, `config/services_test.yaml` contains a stale service definition for
`App\Importer\ImporterService`, while that class is absent from the tag.  The
integration harness removes only that dead test-only service definition before
booting the test kernel.

Second, Kimai deliberately skips dynamic plugin discovery in the `test`
environment.  The harness therefore registers `InvoiceEmailerBundle` in the
test checkout's `config/bundles.php` only for the controller suite.

Production plugin discovery, production container compilation, and production
route discovery are tested without those test-environment substitutions.

These shims are test-harness adaptations, not plugin runtime requirements.

## Verified Kimai 2.67.0 Integration Points

### InvoiceService

`App\Invoice\InvoiceService::getInvoiceFile(Invoice): ?SplFileInfo` exists
and resolves the generated invoice file when it is readable.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/Invoice/InvoiceService.php

### ServiceInvoice

`App\Invoice\ServiceInvoice` remains only as a compatibility subclass and is
deprecated since Kimai 2.56 in favor of `InvoiceService`.

The maintained plugin uses `InvoiceService`.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/Invoice/ServiceInvoice.php

### EmailEvent and Kimai mail handling

`App\Event\EmailEvent` accepts a Symfony `Email`.  Kimai's
`EmailSubscriber` handles that event and delegates to `KimaiMailer`.
`KimaiMailer` delegates to the configured Symfony mailer and supplies Kimai's
fallback From address when the message has no explicit sender.

Sources:

- https://github.com/kimai/kimai/blob/2.67.0/src/Event/EmailEvent.php
- https://github.com/kimai/kimai/blob/2.67.0/src/EventSubscriber/EmailSubscriber.php
- https://github.com/kimai/kimai/blob/2.67.0/src/Mail/KimaiMailer.php

### Invoice action extension point

Kimai 2.67.0 retains `AbstractActionsSubscriber`, which supports the invoice
page-action integration used by this plugin.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/EventSubscriber/Actions/AbstractActionsSubscriber.php

### Invoice authorization and CSRF precedent

Kimai's invoice controller requires `view_invoice` for invoice access and
specific-invoice download.  Its state-changing invoice status operation uses
POST and validates a CSRF token.

The plugin retains those boundaries rather than introducing a weaker alternate
path.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/Controller/InvoiceController.php

### Future invoice-update event

`InvoiceUpdatePostEvent` exists in Kimai 2.67.0 and fires after invoice
persistence for new and updated invoices.

It is not used by the current maintained release.  Its existence is recorded
only as a possible input to a future automatic-send design.

Source:
https://github.com/kimai/kimai/blob/2.67.0/src/Event/InvoiceUpdatePostEvent.php

## Upstream Compatibility Claims Are Not Inherited

The ADK upstream Composer metadata declared:

- PHP `>=8.1`;
- `kimai/kimai2: ^2.0`; and
- Kimai minimum `21700`.

Those declarations are provenance data, not evidence for this maintained
plugin.  The reviewed upstream source contains deprecated and unsupported
integration assumptions.

The maintained compatibility claim therefore remains Kimai 2.67.0 until tests
establish additional versions.

## Evidence Vocabulary

Documentation and package metadata distinguish:

- **source-reviewed**: required APIs are present in inspected source;
- **CI-verified**: automated tests completed successfully against that exact
  version combination;
- **staging-verified**: a packaged plugin was exercised in a disposable
  production-like installation; and
- **production-observed**: the plugin was used in production, without implying
  that observation proves universal compatibility.

One category must not be promoted to another by inference.

## Production Deployment Gate

Before claiming compatibility with a particular production deployment, record:

- exact Kimai version;
- exact PHP version;
- container image tag and resolved digest when containerized;
- plugin release/version;
- relevant mail transport type; and
- staging-validation results.

Phase 7 adds a published-release staging workflow that downloads the actual
GitHub Release assets, verifies their digests, installs the ZIP into Kimai
2.67.0 on PHP 8.4, and exercises the maintained controller contract plus the
packaged send service through a real SMTP connection to a controlled Mailpit
sink.

Release `v0.2.0` passed this staging gate in workflow run `37089873036`.
The run validated the published ZIP digest
`sha256:ed558a1a2809b22239bd7fbfd75c6e733daebce4a60461c57e8bea30a945178a`
and verified the captured invoice attachment bytes.

See [staging-validation.md](staging-validation.md).

## Extending the Compatibility Range

Supporting an older or newer Kimai version requires evidence.

When adding a version:

1. inspect API differences relevant to this plugin;
2. add or update the test matrix;
3. run installation and functional checks;
4. document conditional compatibility behavior; and
5. update package metadata only after the support claim is established.

## Related Documents

- [Architecture](architecture.md)
- [Security](security.md)
- [Testing](testing.md)
- [Release Packaging](release.md)
- [Staging Validation](staging-validation.md)
- [STRIDE Threat Model](thread_model.md)
- [Upstream Provenance](../UPSTREAM.md)
- [ADR-003](adr/ADR-003-secured-manual-invoice-email-workflow.md)
- [ADR-004](adr/ADR-004-deterministic-release-artifacts.md)
