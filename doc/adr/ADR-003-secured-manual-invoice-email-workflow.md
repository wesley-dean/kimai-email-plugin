# ADR-003: Establish a Secured Manual Invoice Email Workflow

Date: 2026-10-02

## Status

Accepted

## Context

This repository is intended to modernize and maintain invoice-email
functionality derived from the MIT-licensed ADK Interactive Kimai Invoice
Emailer plugin.

The upstream plugin contains a useful manual-send core, but its maintained
architecture cannot be adopted unchanged.  The reviewed implementation sends
email through a state-changing GET route, does not require normal
specific-invoice visibility in addition to its custom send permission, depends
on deprecated `ServiceInvoice`, and includes automatic-send behavior coupled
to unsupported or defective event logic.

The first maintained release needs a smaller and more defensible contract before
source is imported.

Kimai 2.67.0 is the initial implementation target.  Source review confirms the
current presence of `InvoiceService::getInvoiceFile()`, `EmailEvent`,
`EmailSubscriber`, `KimaiMailer`, and the action-subscriber mechanism.
Kimai's own invoice controller uses specific-invoice `view_invoice`
authorization and CSRF-protected POST for state-changing status operations.

The email transport and Kimai persistence layers do not provide one atomic
transaction.  A transport may accept a message before a later audit-state
database write fails.  The architecture must expose that limitation instead of
claiming exactly-once delivery.

## Decision Drivers

- Prevent navigation or prefetch of a GET request from sending an invoice.
- Preserve Kimai's normal invoice authorization boundary.
- Require an explicit, reviewable human action before an external email side
  effect.
- Use current Kimai APIs rather than deprecated compatibility classes.
- Reuse Kimai's stored invoice document rather than accepting arbitrary paths.
- Preserve Kimai's mail configuration and dispatch path.
- Keep the first release small enough to test thoroughly.
- Avoid coupling the first release to automatic-send, retry, or idempotency
  problems.
- Make mail-versus-database consistency limitations explicit.
- Maintain clear upstream provenance while allowing substantial modernization.

## Decision

The first maintained release SHALL implement manual invoice email only.

The workflow SHALL use a side-effect-free GET confirmation endpoint followed by
a CSRF-protected POST send endpoint.

The POST operation SHALL require:

- an authenticated Kimai user;
- the dedicated `email_invoice` permission;
- `view_invoice` authorization for the specific invoice; and
- applicable customer/object access authorization.

The send service SHALL re-evaluate current invoice/customer/file state during
POST rather than treating confirmation-page state as authoritative.

The invoice attachment SHALL be obtained through
`InvoiceService::getInvoiceFile()`.

Maintained code SHALL use `InvoiceService`, not deprecated
`ServiceInvoice`.

The message SHALL be a Symfony `TemplatedEmail` dispatched through Kimai's
`EmailEvent`, allowing Kimai's `EmailSubscriber`, `KimaiMailer`, and
configured Symfony transport to own mail delivery mechanics.

Canceled invoices SHALL NOT be sent.

The first maintained release SHALL NOT:

- send automatically on invoice creation or update;
- subscribe to an automatic invoice-send event;
- change invoice status after a send;
- support post-send `PAID` behavior;
- retry automatically;
- add an arbitrary second recipient;
- claim exactly-once delivery; or
- claim recipient delivery merely because the configured transport accepted the
  message.

Any send timestamp or equivalent invoice metadata in the first release is
informational audit state.  It SHALL NOT be treated as delivery proof or as an
idempotency guarantee.

The confirmation view SHOULD show the resolved invoice, customer, recipient,
subject, sender identity when available, and attachment filename so that the
human operator can verify the consequential action before POST.

The first maintained message body SHOULD remain sparse and locale-neutral until
locale-aware rendering of duplicated financial/date information is explicitly
designed and tested.

## Alternatives Considered

### Import the ADK plugin unchanged and modernize incrementally

This would preserve the most source similarity, but it would temporarily adopt
known unsafe and defective behavior as the maintained baseline.  It was
rejected because the repository already has enough evidence to identify the
side-effecting GET, authorization gap, deprecated dependency, and automatic-send
problems before import.

### Preserve automatic sending but repair the event hook immediately

Kimai 2.67.0 provides `InvoiceUpdatePostEvent`, which is a plausible future
hook.  Using it now would expand the first change into idempotency, recursion,
creation-versus-update semantics, retry, and cross-system consistency questions.

Automation is therefore deferred to a separate ADR and feature.

### Retain an optional New-to-Pending transition after manual sending

A status transition could be useful, but it introduces a second consequential
state change and additional failure ordering.  The first release omits all
automatic status transitions so that sending and invoice workflow remain
separate concerns.

### Send directly through Symfony Mailer

Direct use could reduce one layer of indirection, but it would bypass Kimai's
established `EmailEvent` and `KimaiMailer` integration and could duplicate
sender/transport policy.

The plugin will use the Kimai event path.

### Construct or accept the attachment path directly

This would increase filesystem attack surface and duplicate Kimai storage
knowledge.  The plugin will use `InvoiceService::getInvoiceFile()`.

### Introduce a durable queue or outbox in the first release

An outbox could provide stronger retry and consistency semantics, but it would
add persistence, migration, worker, and idempotency complexity before the
manual behavior is proven.

The first release accepts documented non-atomic mail/database behavior with
manual operation and no automatic retries.

## Consequences

### Positive

- GET remains free of the email side effect.
- CSRF and authorization boundaries are explicit.
- Sending cannot become an alternate invoice-download path for users who lack
  normal invoice access.
- Current Kimai APIs are used from the first maintained implementation.
- Mail transport policy remains owned by Kimai.
- Automatic-send and status-change complexity is separated from the first
  production milestone.
- Tests can target a narrow, explicit behavioral contract.
- Provenance remains visible without requiring obsolete architecture to remain.

### Negative

- Users who want automatic sending will not receive it in the first release.
- Manual resend remains possible and requires operator judgment.
- Transport acceptance and audit persistence can still become inconsistent.
- A single send timestamp provides limited audit evidence.
- Compatibility begins narrowly at Kimai 2.67.0 until broader support is
  demonstrated.

## Compatibility and Migration

The initial compatibility target is Kimai 2.67.0 and PHP versions supported by
that Kimai release, currently PHP 8.2 through 8.5.

This is a target, not a certification.  Source presence of required APIs does
not replace plugin installation, integration, and end-to-end tests.

No migration from an earlier maintained release is required because this
repository has not yet shipped the modernized implementation.

Future support for additional Kimai versions must be based on executed
compatibility evidence.

## Expected Outcome

The first implementation PR should be reviewable against a stable contract:

- a human sees a confirmation;
- GET has no send side effect;
- one authorized, CSRF-valid POST requests one email send;
- the attachment is the existing Kimai invoice;
- Kimai's own mail pipeline performs transport dispatch;
- no invoice status changes automatically; and
- failures and audit state are described without overstating delivery
  guarantees.

## Related Decisions

- [ADR-001: Adopt Released Coding Standards](ADR-001-adopt-released-coding-standards.md)
- [ADR-002: Adopt MIT License for Project-Owned Work](ADR-002-adopt-mit-license.md)

## Related Documentation

- [Architecture](../architecture.md)
- [Security](../security.md)
- [Compatibility](../compatibility.md)
- [Upstream Provenance](../../UPSTREAM.md)
