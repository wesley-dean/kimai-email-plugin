# Threat Model (STRIDE)

## Purpose

This document is the maintained threat model for Kimai Invoice Emailer.  It
applies the STRIDE model to the secured manual invoice-email workflow, release
artifact, staging-validation path, and the trust boundaries that connect the
plugin to Kimai and to an external mail transport.

The model is intended to answer four questions for each material threat:

1. what asset or boundary is at risk;
2. what concrete attack or failure is plausible;
3. what control currently reduces that risk; and
4. what residual risk remains after the control.

This document describes the maintained manual-send architecture only.
Automatic sending, retries, queues, bulk sending, arbitrary additional
recipients, authoritative delivery tracking, and invoice-status coupling remain
outside the accepted architecture unless a later ADR introduces them.

## Governing Decisions

The threat model is constrained by:

- [ADR-003](adr/ADR-003-secured-manual-invoice-email-workflow.md), which defines
  the secured human-confirmed send workflow; and
- [ADR-004](adr/ADR-004-deterministic-release-artifacts.md), which defines the
  supported release artifact and publication boundary.

Repository-wide security requirements are summarized in
[security.md](security.md).

## Scope

The security-sensitive operation is transmission of an already-generated Kimai
invoice to the email address stored on the invoice customer.

The modeled path is:

```text
Browser
  -> Kimai authentication and authorization
  -> InvoiceEmailerController
  -> InvoiceEmailService
  -> InvoiceService::getInvoiceFile()
  -> TemplatedEmail
  -> EmailEvent
  -> Kimai EmailSubscriber
  -> KimaiMailer
  -> configured Symfony mail transport
  -> recipient mail system
```

The release and staging path is also in scope because artifact substitution or
privilege confusion could alter the code that performs the send:

```text
Repository
  -> read-only validation
  -> deterministic ZIP
  -> isolated publisher
  -> GitHub Release assets
  -> checksum/digest verification
  -> Kimai plugin installation
  -> controlled staging SMTP sink
```

## Assets

### Invoice contents

Generated invoice files may contain customer identity, commercial information,
billing details, addresses, descriptions of work, prices, taxes, and other
confidential business information.

### Customer contact information

The stored customer email address is both sensitive contact information and a
routing decision.  A syntactically valid address may still identify the wrong
recipient.

### Authorization state

Kimai authentication, the custom `email_invoice` permission,
specific-invoice `view_invoice` authorization, and applicable customer access
collectively determine whether a user may initiate the email side effect.

### Generated invoice file

The stored invoice document is the attachment that crosses the external mail
boundary.  Selecting the wrong file is an information-disclosure event even if
the intended recipient is otherwise correct.

### Mail configuration

Kimai's sender identity and configured Symfony transport determine where the
message is submitted.  The plugin deliberately does not own a second mail
configuration.

### Operational evidence

Logs, CI evidence, release digests, staging results, and Git history support
diagnosis and review.  They are useful evidence, but they are not equivalent to
recipient delivery proof.

### Release artifacts

The deterministic ZIP and its SHA-256 sidecar are the supported installation
artifacts.  Their integrity matters because the installed plugin crosses
authorization, filesystem, and network boundaries.

## Actors

### Authorized Kimai user

An authenticated human with `email_invoice`, permission to view the specific
invoice, and applicable access to its customer.

### Under-authorized Kimai user

An authenticated user missing at least one required permission or object-level
grant.

### External web actor

A party able to cause navigation, links, or form submissions in a user's
browser without possessing the user's authenticated authorization state or a
valid per-invoice CSRF token.

### Kimai administrator

A privileged operator able to change customer data, permissions, mail
configuration, or invoice state.  Administrative capability is legitimate but
can create routing mistakes or policy changes that the plugin cannot
independently distinguish from intended administration.

### Mail transport and downstream mail systems

Systems outside the plugin that accept, queue, route, filter, forward, store,
or reject email.

### Repository maintainer and release automation

Actors and workflows that can alter source, validate artifacts, or publish a
release.

## Trust Boundaries

### Browser to controller

Untrusted request state crosses into the plugin.  Route identifiers and the CSRF
token originate from the request.  Recipient, sender, subject, and attachment
path do not become authoritative merely because they appeared on the
confirmation page.

### Controller to application service

The controller passes a Kimai-resolved `Invoice`.  The service must resolve
current customer, recipient, file, and sender state again at send time.

### Kimai authorization subsystem

The plugin relies on Kimai to establish authenticated identity and to evaluate
`view_invoice` and customer access.  The custom permission is additive, not a
replacement for those checks.

### Kimai storage to attachment

The plugin crosses from invoice metadata to a confidentiality-sensitive
filesystem object.  The path is resolved through `InvoiceService`, not from
request-controlled path input.

### Plugin to mail transport

The invoice leaves the Kimai application boundary.  Transport acceptance is an
externally visible side effect and does not prove mailbox delivery.

### Repository validation to release publication

Read-only jobs validate source and build release bytes.  The write-capable
publisher receives only validated release artifacts and does not check out or
execute repository code.

### Published release to staging

The staging workflow downloads already-published assets, verifies the sidecar
and GitHub asset digest, installs the ZIP, and submits a deterministic invoice
to a localhost-only Mailpit SMTP sink.

## Security Assumptions

The current model assumes:

- Kimai 2.67.0 authentication and authorization behave as documented and tested;
- the Kimai process can trust its configured invoice-data directory;
- the host operating system and filesystem permissions protecting Kimai are not
  already compromised;
- repository branch and workflow protections are administered separately from
  this plugin;
- GitHub's release asset digest accurately represents the bytes GitHub stores;
- the configured production mail transport is administered by the deployment
  owner; and
- a syntactically valid customer email address is not automatically a verified
  identity.

A failure of one of these assumptions may require controls outside the plugin.

## STRIDE Analysis

### Spoofing

#### S-1: Impersonation of an authorized sender

**Scenario:** an unauthenticated or under-authorized user attempts to initiate an
invoice send as though they possessed the required Kimai privileges.

**Controls:**

- class-level `IS_AUTHENTICATED_FULLY`;
- dedicated `email_invoice` permission;
- specific-invoice `view_invoice` authorization; and
- applicable customer access authorization.

**Evidence:**

- controller integration tests for missing `email_invoice`;
- controller integration tests for missing invoice visibility; and
- Kimai-kernel authorization behavior.

**Residual risk:** the plugin trusts Kimai's underlying identity and permission
implementation.  Compromised credentials or a defect in Kimai authorization
can affect the plugin.

#### S-2: Cross-site request forgery impersonates user intent

**Scenario:** an external site causes a logged-in browser to submit a send
operation.

**Controls:**

- GET confirmation has no email side effect;
- the send route accepts POST only; and
- POST validates a per-invoice CSRF token.

**Evidence:**

- GET side-effect integration test;
- send-route GET rejection test; and
- invalid-CSRF integration test.

**Residual risk:** malicious browser extensions, endpoint compromise, or theft
of an authenticated session and valid CSRF token are outside the plugin's
direct control.

#### S-3: Incorrect customer address impersonates the intended recipient

**Scenario:** the customer record contains a valid but incorrect or
attacker-controlled email address.

**Controls:**

- confirmation page displays the resolved recipient;
- recipient is re-resolved from current customer state at POST; and
- Symfony Mime validates address syntax.

**Evidence:**

- recipient revalidation unit test; and
- integration assertion against the current customer address.

**Residual risk:** the plugin cannot prove that a valid stored address belongs
to the intended human.  Master-data correctness remains an administrative
responsibility.

#### S-4: Misconfigured sender or transport presents misleading identity

**Scenario:** deployment mail configuration uses an unintended sender or
transport.

**Controls:**

- sender configuration remains owned by Kimai;
- missing sender configuration blocks the send; and
- the plugin does not introduce a second SMTP configuration.

**Evidence:**

- missing-sender unit test; and
- staging validation through Kimai's production mailer path.

**Residual risk:** a deployment administrator can intentionally or accidentally
configure a misleading sender or transport.

### Tampering

#### T-1: Route identifier is changed to select another invoice

**Scenario:** a user changes the invoice identifier in the confirmation or send
URL.

**Controls:**

- Kimai resolves the invoice entity from the route;
- authorization is evaluated against that specific resolved invoice; and
- customer access is evaluated against the resolved invoice customer.

**Evidence:**

- specific-invoice authorization integration tests.

**Residual risk:** the plugin depends on Kimai route/entity resolution and
authorization correctness.

#### T-2: Confirmation state becomes stale before POST

**Scenario:** customer email, invoice state, or generated file changes after the
operator reviews the confirmation page.

**Controls:**

- confirmation data is explicitly informational;
- POST repeats authorization; and
- `InvoiceEmailService` re-resolves recipient, invoice state, generated file,
  and sender configuration immediately before dispatch.

**Evidence:**

- unit test proving send re-resolves recipient state after preview; and
- service-level validation tests.

**Residual risk:** an authorized change immediately before or during send may
still alter the outcome.  The workflow intentionally does not lock customer or
invoice records across human think time.

#### T-3: Attachment path is manipulated

**Scenario:** request data or application state attempts to point the plugin at
an arbitrary filesystem path.

**Controls:**

- no request-controlled attachment path is accepted; and
- the file is obtained through `InvoiceService::getInvoiceFile()`.

**Evidence:**

- source review;
- missing-file unit tests; and
- packaged integration/staging attachment checks.

**Residual risk:** compromise of Kimai's invoice storage logic or host
filesystem can undermine this boundary.

#### T-4: Release artifact is modified between source validation and installation

**Scenario:** the distributed ZIP differs from the source state that passed
tests or is modified after publication.

**Controls:**

- deterministic package build;
- byte-for-byte rebuild comparison;
- release SHA-256 sidecar;
- GitHub asset digest verification; and
- integration tests against the package representation.

**Evidence:**

- Phase 6 compatibility workflow;
- published release digest; and
- staging download verification.

**Residual risk:** compromise of both the distribution platform and the
independently compared integrity metadata is outside this repository's direct
control.

#### T-5: Release publication executes altered repository code with write privilege

**Scenario:** untrusted or mutable source executes in a job holding
`contents: write` and changes what is released.

**Controls:**

- source validation and release-candidate construction run read-only;
- write capability exists only in the final publisher;
- the publisher does not check out repository source;
- the publisher re-verifies transferred files before release creation.

**Evidence:**

- workflow source;
- CodeQL validation of the corrected privilege boundary; and
- successful Phase 6 release runs.

**Residual risk:** compromise of pinned third-party actions or GitHub's runtime
remains a platform-level concern.

### Repudiation

#### R-1: User disputes initiating a send

**Scenario:** an invoice is submitted and the initiating user later disputes the
action.

**Controls:**

- send attempts log invoice ID and authenticated user ID;
- the UI requires explicit human confirmation; and
- automatic sending is absent.

**Evidence:**

- source review of the maintained debug/error logging context.

**Residual risk:** the initial implementation intentionally does not create a
durable, authoritative audit record or cryptographic non-repudiation evidence.

#### R-2: Transport submission is presented as recipient delivery

**Scenario:** a successful application return is interpreted as proof that the
recipient received or read the invoice.

**Controls:**

- user-facing and maintained documentation describe submission rather than
  confirmed delivery;
- no `email_sent_date` is stored as delivery proof; and
- staging terminology is limited to SMTP acceptance by the controlled sink.

**Evidence:**

- ADR-003;
- architecture and security documentation; and
- staging evidence boundaries.

**Residual risk:** downstream systems can accept and later drop, quarantine, or
redirect mail.

#### R-3: Release provenance cannot be established

**Scenario:** an installed ZIP cannot be tied to a reviewed repository state.

**Controls:**

- semantic tag;
- release target commit;
- deterministic ZIP;
- SHA-256 sidecar; and
- GitHub asset digest.

**Evidence:**

- release API metadata and Phase 6 documentation.

**Residual risk:** users who install generic source archives or locally modified
packages step outside the supported provenance contract.

### Information Disclosure

#### I-1: Under-authorized user uses email as an alternate invoice access path

**Scenario:** a user with only `email_invoice` attempts to transmit an invoice
they cannot normally view.

**Controls:**

- `email_invoice` is insufficient by itself;
- specific-invoice `view_invoice` is also required; and
- applicable customer access remains required.

**Evidence:**

- integration test for email permission without invoice permission.

**Residual risk:** the plugin inherits any over-broad grants configured in
Kimai.

#### I-2: Invoice is sent to the wrong stored customer address

**Scenario:** confidential invoice content leaves Kimai for an unintended
mailbox.

**Controls:**

- recipient is shown to the operator;
- current recipient is re-resolved at POST; and
- malformed addresses are rejected.

**Evidence:**

- invalid/missing recipient unit tests;
- confirmation flow; and
- current-recipient integration assertion.

**Residual risk:** human confirmation cannot prove ownership of the mailbox.

#### I-3: Sensitive values leak into logs

**Scenario:** recipient address, attachment path, or invoice content is written
to routine application logs.

**Controls:**

- maintained send logging uses invoice ID and user ID only; and
- controller error logging records the exception plus non-sensitive identifiers,
  not recipient or file path fields.

**Evidence:**

- source review of logging statements.

**Residual risk:** dependency exceptions may contain implementation details.
Deployment operators must protect application logs according to their normal
data-handling policy.

#### I-4: Customer-controlled content becomes active markup

**Scenario:** customer data rendered in the confirmation page injects HTML or
script content.

**Controls:**

- maintained Twig templates rely on normal autoescaping; and
- no raw rendering is introduced for customer-controlled confirmation values.

**Evidence:**

- integration test with customer-controlled HTML-like content.

**Residual risk:** future template changes using raw output or unsafe filters
would require threat-model review.

#### I-5: Staging validation uses real customer data

**Scenario:** production-like validation leaks real invoice or recipient data to
a test SMTP system.

**Controls:**

- staging uses deterministic `example.com` addresses;
- the staging invoice is synthetic and non-persisted for the prod-kernel SMTP
  step;
- Mailpit binds to localhost only; and
- Mailpit does not relay to the Internet.

**Evidence:**

- staging command source and Mailpit verifier.

**Residual risk:** runner compromise remains outside the plugin's control.

### Denial of Service

#### D-1: Authorized user repeatedly sends the same invoice

**Scenario:** repeated manual sends consume application or mail capacity.

**Controls:**

- sending remains authenticated and authorized;
- every send requires explicit confirmation; and
- there is no automatic retry loop.

**Evidence:**

- ADR-003 and manual workflow implementation.

**Residual risk:** the plugin does not implement per-user, per-invoice, or
global rate limiting.  A legitimately authorized user can still perform
repeated sends.

#### D-2: Mail transport is slow or unavailable

**Scenario:** synchronous submission delays or fails the HTTP request.

**Controls:**

- failures are caught at the controller boundary and represented as a user-safe
  error;
- no automatic retry amplifies an outage; and
- deployment transport timeouts remain owned by Symfony/Kimai configuration.

**Evidence:**

- service and controller failure contracts.

**Residual risk:** the current synchronous architecture can still consume a web
worker while a transport blocks.

#### D-3: Generated invoice file is missing or unreadable

**Scenario:** repeated attempts fail before dispatch or filesystem problems
consume operator time.

**Controls:**

- file existence/readability is validated before dispatch; and
- the request fails without claiming successful submission.

**Evidence:**

- missing-file unit test.

**Residual risk:** the plugin does not repair invoice generation or filesystem
permissions.

#### D-4: External CI dependency prevents release or staging validation

**Scenario:** GitHub, Kimai dependency installation, or Mailpit download is
temporarily unavailable.

**Controls:**

- release publication is gated by successful validation;
- staging is read-only and can be rerun; and
- third-party actions and Mailpit are pinned to immutable versions/digests.

**Evidence:**

- workflow definitions.

**Residual risk:** availability of external build and hosting systems is not
controlled by this repository.

### Elevation of Privilege

#### E-1: Custom email permission bypasses normal invoice authorization

**Scenario:** `email_invoice` is mistakenly treated as authority to email any
invoice.

**Controls:**

- `email_invoice`, `view_invoice`, and customer access are all required; and
- controller authorization remains authoritative even if the UI action is
  hidden.

**Evidence:**

- permission-isolation integration tests; and
- controller source review.

**Residual risk:** administrators can intentionally grant broad Kimai roles.

#### E-2: UI action visibility is treated as the security boundary

**Scenario:** a caller bypasses the invoice-list action and invokes routes
directly.

**Controls:**

- both controller endpoints enforce authentication and authorization; and
- POST independently validates CSRF.

**Evidence:**

- controller attributes and authorization helper.

**Residual risk:** future routes must preserve the same server-side checks.

#### E-3: Customer access substitutes for invoice visibility

**Scenario:** access to a customer is used to infer permission to transmit all
associated invoices.

**Controls:**

- customer access is an additional restriction, never a replacement for
  `view_invoice`.

**Evidence:**

- ADR-003 and controller authorization order.

**Residual risk:** any weakness in Kimai's own object authorization affects the
plugin.

#### E-4: Release or staging workflow gains unnecessary mutation capability

**Scenario:** validation code can alter repository contents or release state.

**Controls:**

- compatibility and staging workflows default to `contents: read`;
- only the isolated publisher receives `contents: write`;
- staging cannot create, edit, or delete releases; and
- post-publication staging resolves the release tied to the validated
  compatibility commit.

**Evidence:**

- workflow permission declarations; and
- CodeQL workflow analysis.

**Residual risk:** repository administrators retain out-of-band capability to
change workflow permissions.

## Cross-Cutting Controls

### Human-protective confirmation

The confirmation page makes the consequential action visible before the POST.
It reduces accidental disclosure but is not treated as authoritative state.

### Current-state revalidation

The send service resolves current customer, recipient, file, and sender state
after the human confirmation interval.

### Least privilege in automation

Validation and staging run read-only.  Write privilege is isolated to release
publication and is not combined with repository code execution.

### Evidence bound to state

Compatibility evidence is associated with the tested source tree or published
artifact.  Staging evidence records the exact release tag, digest, Kimai
version, PHP version, and SMTP sink.

## Evidence Map

| Security property | Primary evidence |
| --- | --- |
| GET has no send side effect | controller integration test |
| POST only for send | route integration test |
| CSRF required | invalid-CSRF integration test |
| `email_invoice` required | authorization integration test |
| `view_invoice` required | authorization integration test |
| current recipient re-resolved | service unit test |
| canceled invoice rejected | unit and integration tests |
| malformed/missing recipient rejected | unit tests |
| missing invoice file rejected | unit test |
| customer text escaped | integration test |
| packaged ZIP integrity | deterministic build and checksum |
| release privilege isolation | workflow review and CodeQL |
| real SMTP acceptance by controlled sink | published-release staging |
| attachment bytes cross SMTP boundary | Mailpit attachment comparison |

## Residual Risk Summary

The most material accepted residual risks are:

- a valid stored customer address can still identify the wrong recipient;
- an authorized user can repeatedly perform manual sends;
- synchronous transport failure can delay a request;
- Kimai authentication, authorization, and invoice storage remain trusted
  dependencies;
- transport acceptance is not Internet delivery or mailbox receipt;
- no authoritative non-repudiation record is stored; and
- compromise of the application host, repository administration, or external
  platform can bypass controls owned by this plugin.

These risks are accepted for the current human-initiated scope.  They should not
be silently inherited by a future automatic-send design.

## Review Triggers

Re-review this threat model when any of the following occurs:

- automatic sending is introduced;
- retry, queue, or outbox behavior is introduced;
- bulk sending is added;
- additional recipients are added;
- invoice status is changed as part of sending;
- delivery receipts or authoritative audit state are introduced;
- request-controlled paths, templates, or message bodies are accepted;
- customer or invoice authorization semantics change;
- Kimai changes the relevant invoice-storage or mail APIs;
- release-publication privileges change;
- staging begins using external SMTP rather than a localhost sink;
- a security incident exposes an unmodeled threat; or
- supported Kimai versions materially alter the trust boundaries.

## Related Documents

- [Security](security.md)
- [Architecture](architecture.md)
- [Testing](testing.md)
- [Compatibility](compatibility.md)
- [Published Release Staging Validation](staging-validation.md)
- [ADR-003](adr/ADR-003-secured-manual-invoice-email-workflow.md)
- [ADR-004](adr/ADR-004-deterministic-release-artifacts.md)
