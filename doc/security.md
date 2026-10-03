# Security

## Scope

This document describes the security posture intended for the first maintained
manual invoice-email workflow.

The security-sensitive operation is transmission of an existing invoice
document from Kimai to an external email recipient.  The principal assets are
invoice contents, customer identity and contact information, authorization
state, the stored invoice document, mail configuration, and evidence that a
send operation was attempted.

This is design-stage disclosure.  Controls described as requirements are not
evidence that implementation or runtime validation has already occurred.

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

The Phase 3 source implements these controls, but tests and runtime installation
evidence are still required before they can be considered verified runtime
properties.

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

## STRIDE Analysis

### Spoofing

Threats:

- an under-authorized user attempts to act as a user permitted to send invoices;
- an external site attempts to induce a logged-in user to perform a send;
- an incorrect or maliciously modified customer address directs an invoice to
  the wrong recipient.

Controls:

- Kimai authentication;
- explicit authorization on the specific invoice;
- dedicated `email_invoice` permission;
- CSRF validation;
- confirmation UI showing the resolved recipient; and
- Symfony Mime address validation.

Evidence required:

- controller authorization tests;
- CSRF failure tests; and
- integration tests covering resolved recipients.

Residual risk:

An authorized Kimai user can still send to an incorrect address already stored
in customer data.  The confirmation view reduces accidental disclosure but does
not establish the correctness of customer master data.

### Tampering

Threats:

- modification of invoice identifiers or request values to select another
  invoice;
- attachment-path manipulation;
- modification of customer or invoice state between confirmation and POST.

Controls:

- resolve the invoice through Kimai routing/repository behavior;
- authorize the specific resolved invoice;
- derive the attachment from `InvoiceService`;
- perform send validation again during POST; and
- do not trust hidden confirmation fields as authority for recipient or file
  selection.

Evidence required:

- object-authorization tests;
- altered-ID tests where practical; and
- tests confirming the attachment originates from the resolved invoice.

Residual risk:

Administrators or other separately authorized actors may legitimately modify
invoice/customer state.  The send operation protects against stale confirmation
state by re-reading authoritative values, not by freezing the underlying
records.

### Repudiation

Threats:

- an email side effect occurs without useful evidence of which authenticated
  user initiated it;
- transport submission is mistaken for recipient delivery.

Controls:

- log non-sensitive send-attempt context using invoice and authenticated-user
  identifiers;
- do not persist a misleading `email_sent_date` field in the initial release;
- distinguish mail-system submission from recipient delivery; and
- keep recipient addresses and attachment paths out of routine logs.

Evidence required:

- tests for logging-adjacent observable behavior where practical; and
- review of maintained logging statements.

Residual risk:

The initial release intentionally provides limited non-repudiation evidence.
Stronger evidence would require additional logging, provider message IDs,
delivery receipts, or another durable audit design and is outside the first
release.

### Information Disclosure

Threats:

- sending an invoice to an unauthorized or incorrect recipient;
- an under-authorized Kimai user using the plugin as an alternate invoice
  download path;
- recipient addresses or invoice content appearing unnecessarily in logs;
- unescaped customer-controlled template content.

Controls:

- require normal invoice visibility in addition to `email_invoice`;
- require applicable object/customer authorization;
- show recipient before POST;
- avoid plaintext recipient logging without documented need;
- use Twig autoescaping; and
- attach only the invoice selected through Kimai's invoice service.

Evidence required:

- authorization tests with restricted users;
- template escaping tests; and
- review of maintained logging statements.

Residual risk:

Once a message is handed to the configured mail system, downstream forwarding,
storage, mailbox security, and external mail-provider handling are outside the
plugin's control.

### Denial of Service

Threats:

- repeated manual sends consume mail-service capacity;
- malformed or missing invoice files cause repeated failures;
- a transport outage causes slow or failed requests.

Controls:

- manual, authorized operation only;
- no automatic retry in the first release;
- validate required invoice/recipient state before dispatch; and
- use the configured mail transport's operational limits and timeouts.

Evidence required:

- failure-path tests; and
- staging tests with a controlled unavailable/failing transport where practical.

Residual risk:

The first release does not add plugin-specific rate limiting.  An authorized
user could intentionally or accidentally perform repeated sends.  If this
becomes operationally material, rate limiting or queued delivery should be
considered in a later design.

### Elevation of Privilege

Threats:

- possession of `email_invoice` is treated as sufficient to access any invoice;
- customer access is treated as a substitute for invoice permission;
- a send endpoint becomes an alternate route around Kimai's normal invoice
  authorization.

Controls:

- require `email_invoice` and `view_invoice` for the specific invoice;
- retain applicable object/customer authorization; and
- do not expose attachment bytes separately through the plugin.

Evidence required:

- tests covering users with only one of the required permissions;
- tests covering object-level access restrictions; and
- review against Kimai's native invoice authorization behavior.

Residual risk:

The plugin necessarily trusts Kimai's authentication and authorization
implementation.  A vulnerability or policy error in Kimai's underlying access
model can affect the plugin.

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

## Evidence Plan

Security claims will be supported by tests and review in later phases,
including:

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
controls.  This document may describe a control as verified only after the
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
- [ADR-003](adr/ADR-003-secured-manual-invoice-email-workflow.md)
- [Upstream Provenance](../UPSTREAM.md)
