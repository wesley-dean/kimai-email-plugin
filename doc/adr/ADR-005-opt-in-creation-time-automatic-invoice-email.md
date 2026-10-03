# ADR-005: Add Opt-In Creation-Time Automatic Invoice Email

Date: 2026-10-03

## Status

Accepted

## Context

ADR-003 deliberately limited the first maintained release to a human-confirmed
manual-send workflow.  It explicitly deferred automatic sending because the
historical upstream implementation coupled automation to obsolete event APIs,
automatic status changes, duplicate-prevention state, and failure ordering that
were not safe to import unchanged.

The maintained manual workflow is now CI-verified and staging-verified through
Kimai's production mail path.  Automatic sending may therefore be considered as
a distinct feature rather than as unfinished work from the initial import.

Kimai 2.67.0 exposes two relevant invoice events:

- `InvoiceCreatedEvent`, emitted after a newly generated invoice has been
  saved and after its generated file exists; and
- `InvoiceUpdatePostEvent`, emitted after both newly created and subsequently
  updated invoices are saved.

Using the broader update event would make ordinary edits and status changes
potential resend triggers.  That would require durable idempotency state and
explicit update semantics before the behavior could be considered predictable.

Mail submission and invoice persistence remain non-atomic.  A newly created
invoice can exist even when the downstream mail path fails.

## Decision Drivers

- Keep automatic behavior opt-in and human-governed.
- Avoid implicit resend on ordinary invoice edits or status changes.
- Preserve the authorization model already established for manual sending.
- Reuse the maintained `InvoiceEmailService` rather than duplicate message
  construction or transport logic.
- Avoid automatic invoice-status transitions.
- Avoid automatic retry until durable idempotency semantics exist.
- Prevent mail transport availability from becoming an invoice-creation
  availability dependency.
- Preserve manual sending as the recovery path after failed automation.
- Keep routine logs free of recipient addresses and attachment paths.

## Decision

The plugin SHALL support optional automatic emailing for newly created invoices.

Automatic sending SHALL be disabled by default and enabled only when:

`INVOICE_EMAILER_AUTO_SEND=1`

The automatic path SHALL subscribe only to `InvoiceCreatedEvent`.

It SHALL NOT subscribe to `InvoiceUpdatePostEvent` or any generic status-update
event in this release.

The automatic path SHALL require a current authenticated Kimai `User` context.
When no authenticated Kimai user is available, the automatic send SHALL be
skipped.

The initiating user SHALL satisfy the same authorization boundaries as the
manual workflow:

- `email_invoice`;
- specific-invoice `view_invoice`; and
- applicable customer `access`.

The automatic path SHALL reuse `InvoiceEmailService::send()`, including its
current-state validation of cancellation status, customer recipient, generated
invoice file, sender configuration, and Kimai mail dispatch.

Automatic sending SHALL NOT:

- change invoice status;
- persist `email_sent_date` or equivalent audit state;
- retry automatically;
- send on ordinary invoice updates;
- introduce additional recipients;
- claim exactly-once delivery; or
- claim recipient delivery from transport acceptance.

Automatic-send validation, authorization, and transport failures SHALL be
contained and logged rather than rethrown through the creation event.  The
invoice has already been persisted before `InvoiceCreatedEvent` is emitted;
therefore a mail failure SHALL NOT make invoice creation appear to have failed.

Manual send SHALL remain the recovery path after an automatic-send failure.

Routine automatic-send logs SHALL contain only operational identifiers and
non-sensitive reason identifiers.  Recipient addresses and attachment paths
SHALL NOT be added to routine log context.

## Alternatives Considered

### Use InvoiceUpdatePostEvent

This would allow sends on creation and later updates, but the event does not
distinguish the business meaning of each save.  Ordinary edits could therefore
cause duplicate sends.

Rejected for the first automatic-send release.

### Persist a sent flag and suppress duplicates

This could support broader update-triggered automation, but it introduces a
mail-versus-database consistency boundary.  Mail may be accepted before the
flag write fails, or the flag may be committed before a later transport failure.

Deferred until a durable outbox/idempotency design is adopted.

### Retry automatically after transport failure

Automatic retry without durable idempotency state can produce duplicate email.
It can also amplify a downstream outage.

Rejected.

### Change invoice status after automatic send

The historical upstream plugin coupled sending to invoice workflow state.
That creates an additional consequential side effect and failure ordering
problem unrelated to the mail operation.

Rejected.

### Allow automation without an authenticated user

This would permit background or system-initiated creation paths to send without
the authorization context required by ADR-003.

Rejected for this release.  A future system-principal design would require a
separate architectural decision.

## Consequences

### Positive

- Automatic sending is available without weakening the manual path.
- Existing authorization and send validation are reused.
- Invoice edits cannot silently resend mail.
- A mail outage does not roll back or obscure successful invoice creation.
- No new persistence schema or migration is required.
- Failed automation can be recovered explicitly through manual send.

### Negative

- Background invoice creation without a Kimai user does not auto-send.
- There is no automatic retry.
- There is no durable record proving whether an automatic attempt occurred.
- An operator must use logs and manual resend when an automatic attempt fails.
- Exactly-once semantics remain intentionally unclaimed.

## Compatibility and Migration

The initial automatic-send implementation remains scoped to the existing
Kimai 2.67.0 compatibility target.

Existing installations remain behaviorally unchanged because automatic sending
is disabled by default.

Operators who enable the feature must ensure the user creating invoices holds
the required `email_invoice`, `view_invoice`, and customer-access
authorization.

No database migration is required.

## Expected Outcome

When automatic sending is disabled, invoice creation behaves exactly as before.

When automatic sending is enabled and an authorized authenticated user creates
a sendable invoice, exactly one automatic submission attempt is made through
the existing Kimai mail path.

If the attempt cannot proceed or fails, the created invoice remains intact and
available for the maintained manual-send workflow.

## Related Decisions

- [ADR-003: Establish a Secured Manual Invoice Email Workflow](ADR-003-secured-manual-invoice-email-workflow.md)
- [ADR-004: Define Deterministic Kimai Release Artifacts](ADR-004-deterministic-release-artifacts.md)

## Related Documentation

- [Architecture](../architecture.md)
- [Security](../security.md)
- [STRIDE Threat Model](../thread_model.md)
- [Testing](../testing.md)
- [Compatibility](../compatibility.md)
