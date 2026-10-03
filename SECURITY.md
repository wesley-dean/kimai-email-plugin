# Security Policy

## Scope

Kimai Invoice Emailer transmits generated invoice documents through Kimai's
configured mail system.  Authorization, recipient selection, CSRF handling,
attachment resolution, and externally visible email side effects are therefore
security-sensitive behavior.

The maintained threat model and control evidence are documented in
[doc/security.md](doc/security.md).

## Supported Versions

The maintained compatibility target is the current release line documented in
[doc/compatibility.md](doc/compatibility.md).

Security fixes are applied to the maintained line.  Older tags are historical
artifacts and should not be assumed to receive backports unless a release note
explicitly says otherwise.

## Reporting a Vulnerability

Please report suspected vulnerabilities privately by email:

security_vulnerability_disclosure@wesleydean.com

Include, when practical:

- the affected plugin version or commit;
- the Kimai and PHP versions involved;
- the security boundary or behavior affected;
- reproducible steps or a minimal proof of concept;
- expected versus observed behavior; and
- any known conditions required for exploitation.

Do not include real customer invoices, credentials, mail-server passwords,
session tokens, or other sensitive production data in the report.

Good-faith attempts will be made to acknowledge and address reports
responsibly.  No fixed remediation deadline is promised because severity,
reproducibility, upstream dependencies, and release constraints vary by issue.

## Public Disclosure

Please avoid opening a public issue for an uncoordinated vulnerability
disclosure while a reasonable private investigation is in progress.

After a vulnerability is understood and a fix or mitigation is available, a
public issue, advisory, changelog entry, or release note may be used when doing
so is appropriate and does not create unnecessary risk.

## Security Guarantees and Non-Guarantees

The current design requires:

- explicit authorization;
- a side-effect-free confirmation GET;
- a CSRF-protected POST for sending;
- current-state validation at send time;
- attachment lookup through Kimai's `InvoiceService`; and
- mail dispatch through Kimai's own mail integration.

A successful application-level send does not prove recipient delivery.  The
plugin does not provide delivery receipts or exactly-once delivery semantics.

See [doc/security.md](doc/security.md) for the full model and evidence.
