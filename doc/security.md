# Security

## Scope

This document describes the security posture intended for the first maintained
manual invoice-email workflow.

The security-sensitive operation is transmission of an existing invoice
document from Kimai to an external email recipient.  The principal assets are
invoice contents, customer identity and contact information, authorization
state, the stored invoice document, mail configuration, and evidence that a
send operation was attempted.

The source controls described here are implemented, and Phase 4 added automated
unit and real-Kimai integration evidence for the principal authorization, CSRF,
rendering, validation, and dispatch boundaries.

The evidence remains bounded.  The normal compatibility matrix uses a
non-delivering transport, while Phase 7 verified release `v0.2.0` through a
real SMTP connection to a controlled Mailpit sink.  That establishes SMTP
acceptance by the controlled sink only; Internet delivery, mailbox receipt, and
recipient reading remain unvalidated.

## Security Claims

The implementation is required to provide these properties:

1. A GET request cannot cause an invoice email to be sent.
2. Email transmission can be initiated only by an authenticated, authorized
   user through a CSRF-protected POST operation.
3. The user must possess the dedicated `email_invoice` permission and normal
   authorization to view the specific invoice.
4. Applicable customer/object access checks remain additional restrictions and
   are not substitutes for invoice authorization.
5. The attachment path is obtained from Kimai's `InvoiceService`, not derived
   from request-controlled filesystem input.
6. Canceled invoices cannot be sent.
7. Customer-controlled content must not bypass Twig escaping in maintained
   templates.
8. Recipient addresses should not be written to logs unless an operational need
   is documented and accepted.
9. A successful mail API call is not represented as proof of recipient
   delivery.
10. The Phase 3 implementation does not persist post-send audit metadata.

Phase 4 verified these controls at the automated-test boundary described in
[testing.md](testing.md).  Claims about external delivery remain outside that
evidence.

## Actors and Identities

### Authorized Kimai user

A logged-in human who may have permission to view invoices and, separately, the
`email_invoice` permission.

### Unauthorized or under-authorized Kimai user

An authenticated user who lacks one or more required permissions or
object-level grants.

### External web actor

A party capable of causing or encouraging browser navigation or form
submission, but without a valid authenticated authorization context and valid
CSRF token.

### Kimai application

The trusted host application providing invoice authorization, invoice storage,
customer data, event dispatch, and mail configuration.

### Configured mail transport

An external or local transport used by Symfony Mailer.  Its acceptance of a
message is not equivalent to delivery to the intended recipient.

### Invoice recipient

The customer email address stored in Kimai for the invoice's customer.

## Material Trust Boundaries

### Browser to controller

The browser supplies the route, invoice identifier, CSRF token, and the
authenticated user context associated with the request.

Required controls:

- authenticated Kimai session;
- dedicated `email_invoice` authorization;
- `view_invoice` authorization for the specific invoice;
- applicable customer/object access checks; and
- CSRF validation on the POST send operation.

### Controller to application service

The controller passes a resolved Kimai `Invoice` to the send service.

Required controls:

- repeat all state-dependent validation required for the send;
- do not trust confirmation-page state as authoritative at POST time; and
- reject canceled invoices or invoices that have become unsendable.

### Kimai storage to attachment

The invoice document is a confidentiality-sensitive file.

Required control:

- resolve it through `InvoiceService::getInvoiceFile()` and do not accept a
  request-supplied path.

### Plugin to mail transport

The plugin crosses from Kimai-controlled state to an external communication
system.

Required controls:

- use Kimai's `EmailEvent` and `KimaiMailer` path;
- use validated Symfony Mime address handling;
- avoid unnecessary sensitive data in logs; and
- report transport acceptance accurately without claiming delivery.

### Mail transport to recipient

This boundary is outside the plugin's direct control.

Transport configuration, remote server behavior, spam filtering, forwarding,
mailbox compromise, and recipient-side processing can all affect confidentiality
and delivery after Kimai hands off the message.

## STRIDE Threat Model

The detailed STRIDE analysis is maintained in
[thread_model.md](thread_model.md).  It covers spoofing, tampering,
repudiation, information disclosure, denial of service, and elevation of
privilege across the runtime send path, deterministic release pipeline, and
published-release staging boundary.

This document remains the concise security posture and evidence summary.

## Cross-System Consistency

The Phase 3 manual-send implementation performs no post-send database write.
That removes the original email-versus-audit persistence ordering problem from
the initial workflow.

The mail boundary remains non-atomic from the user's perspective: a successful
submission to Kimai's configured mail system still does not prove recipient
delivery.

Compensating controls are:

- human confirmation;
- no automatic retries;
- no automatic send trigger;
- explicit resend behavior; and
- visible user-safe failure handling.

A future requirement for durable audit state, retries, or stronger consistency
is a review trigger for a queue/outbox architecture.

## Sensitive Logging

Default maintained code should avoid recipient email addresses, invoice
contents, and attachment paths in routine logs.

Useful operational identifiers such as invoice IDs and authenticated user IDs
may be logged when they materially aid diagnosis, subject to the deployment's
normal log-protection policy.

Exceptions that require more sensitive logging must be explicit and documented.

## Automated Evidence

Phase 4 provides tests and review evidence for:

- GET produces no send side effect;
- POST rejects missing or invalid CSRF;
- `email_invoice` is required;
- specific-invoice `view_invoice` is required;
- applicable customer/object access is required;
- canceled invoices are rejected;
- exactly one `EmailEvent` is dispatched for one successful POST;
- malformed or missing recipient state is rejected;
- missing invoice files are rejected;
- customer-controlled text remains escaped;
- mail failure does not create a false success state; and
- no post-send audit metadata is persisted by the Phase 3 implementation.

Phase 4 adds automated unit and Kimai-kernel integration tests for these
controls.  Phase 7 adds published-artifact SMTP staging evidence for
`v0.2.0`, including exact attachment-byte verification at the controlled
Mailpit sink.  This document may describe a control as verified only after the
corresponding workflow jobs pass for the evaluated commit or release.

See [testing.md](testing.md) for the executable evidence map.

## Review Triggers

Revisit this security model when any of the following occurs:

- automatic sending is proposed;
- retries or queues are introduced;
- an outbox is introduced;
- additional recipients are added;
- bulk sending is added;
- status changes are coupled to sending;
- audit state becomes authoritative for idempotency;
- the plugin accepts request-controlled attachment paths or templates;
- authentication or permission design changes;
- Kimai materially changes invoice authorization, invoice storage, or mail
  dispatch APIs; or
- a security incident or relevant vulnerability changes the threat model.

## Related Documents

- [Architecture](architecture.md)
- [Compatibility](compatibility.md)
- [STRIDE Threat Model](thread_model.md)
- [ADR-003](adr/ADR-003-secured-manual-invoice-email-workflow.md)
- [Upstream Provenance](../UPSTREAM.md)
