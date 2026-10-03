# Upstream Provenance

## Purpose

This document records the provenance of third-party source material that may be
incorporated into this repository.  It is part of the repository's licensing
and maintenance record and should be updated when imported upstream material or
its provenance changes.

## Primary Upstream

The legal and historical upstream for the invoice-email functionality is:

- Repository: https://github.com/adkinteractive/kimai-invoice-emailer-plugin
- Upstream organization: ADK Interactive, LLC
- Selected base commit:
  `da0a4c7eb38b0d1d99d89c4c3302776e5ab4d07f`
- Upstream license: MIT
- Upstream copyright notice:
  `Copyright (c) 2024 ADK Interactive, LLC`

The selected commit is the head of the ADK repository as reviewed for this
modernization effort.  The upstream MIT license requires preservation of its
copyright and permission notice in copies or substantial portions of the
software.

The repository-root `LICENSE` file governs new project-owned work.  It does
not erase or replace copyright and license notices associated with imported
third-party material.

## Selected Provenance Strategy

The ADK repository is the provenance base even when an implementation is
rewritten, reorganized, or modernized substantially.  Future commits that copy
or substantially derive from ADK source must preserve the upstream MIT notice
in an appropriate durable form.

The modernization is not intended to preserve broken or obsolete behavior for
the sake of source similarity.  Provenance and maintainability are separate
concerns: the project will preserve where code came from while changing how the
maintained implementation works when current Kimai APIs, security requirements,
or repository governance require it.

## Derivative Reference Repository

The following derivative repository has also been reviewed:

- Repository: https://github.com/martepato/InvoiceEmailerBundle
- Reviewed head:
  `a46603514b05540a1b091d7a339eef132ab6c8b7`

That repository shares the ADK base history and adds later changes, including
German translation work and an additional-recipient feature.  It is a useful
reference, but it is not the provenance base for this project.

Derivative changes must be evaluated individually before adoption.  In
particular, the additional-recipient feature is outside the first maintained
release and must not be imported merely because it exists in the derivative.

## Initial Import Scope

The first maintained implementation may derive the following concepts and
integration patterns from ADK:

- Kimai plugin/bundle structure;
- the invoice-list action integration;
- lookup of the invoice customer's email address;
- use of Kimai's stored invoice file;
- construction of a Symfony `TemplatedEmail`;
- attachment of the existing invoice document;
- dispatch through Kimai's `EmailEvent`;
- the custom `email_invoice` permission concept;
- email templates and translation concepts.

The Phase 3 implementation deliberately does not persist invoice email audit
metadata.  The original `email_sent_date` concept remains provenance context,
but it is not imported into the maintained manual-send path.

## Phase 3 Derived Implementation Record

The following maintained files substantially modernize concepts reviewed in the
ADK base and therefore retain explicit ADK provenance in their source headers:

- `Controller/InvoiceEmailerController.php`;
- `Service/InvoiceEmailService.php`; and
- `EventSubscriber/InvoiceActionsSubscriber.php`.

The implementation is rewritten around current Kimai APIs and ADR-003 rather
than copied verbatim.  The root license and this document preserve the upstream
MIT notice and selected base commit.

The first maintained implementation will deliberately not import or preserve:

- automatic sending triggered by invoice events;
- the nonexistent `InvoiceStatusUpdateEvent` dependency;
- automatic post-send invoice status changes;
- the post-send `PAID` behavior;
- the upstream automatic-send duplicate-prevention design;
- the derivative additional-recipient behavior; or
- compatibility claims based only on upstream Composer metadata.

## Compatibility Baseline

The first implementation target is Kimai 2.67.0.  Runtime compatibility is not
yet certified.  Compatibility claims must be based on executed tests against
specific Kimai and PHP versions rather than inherited upstream version
constraints.

See [doc/compatibility.md](doc/compatibility.md) for the maintained compatibility
record.

## Related Governance

- [ADR-002: Adopt MIT License for Project-Owned Work](doc/adr/ADR-002-adopt-mit-license.md)
- [ADR-003: Establish a Secured Manual Invoice Email Workflow](doc/adr/ADR-003-secured-manual-invoice-email-workflow.md)
