<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin.
 *
 * Copyright (c) 2026 Wes Dean.
 * Copyright (c) 2024 ADK Interactive, LLC.
 *
 * The maintained implementation substantially modernizes automatic-send
 * concepts from the ADK Interactive Invoice Emailer plugin.  See UPSTREAM.md
 * for provenance and licensing details.
 */

namespace KimaiPlugin\InvoiceEmailerBundle\Service;

use App\Entity\Invoice;
use App\Entity\User;
use KimaiPlugin\InvoiceEmailerBundle\Exception\InvoiceEmailException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Applies the opt-in policy for creation-time automatic invoice email.
 *
 * The service is disabled by default.  When enabled, it requires a current
 * authenticated Kimai user and repeats the same custom, invoice, and customer
 * authorization checks used by the manual workflow before delegating to
 * `InvoiceEmailService`.
 *
 * Automatic sending is deliberately best effort.  Validation, authorization,
 * and transport failures are logged without being rethrown so a mail outage or
 * configuration defect does not make an already-persisted invoice appear to
 * have failed creation.  No retry, invoice-status mutation, or post-send audit
 * state is introduced.
 *
 * Routine log context contains invoice/user identifiers and non-sensitive
 * reason identifiers only.  Recipient addresses and attachment paths are not
 * logged by this class.
 *
 * @see \KimaiPlugin\InvoiceEmailerBundle\EventSubscriber\AutomaticInvoiceEmailSubscriber
 * @see ../../doc/adr/ADR-005-opt-in-creation-time-automatic-invoice-email.md
 */
final class AutomaticInvoiceEmailService
{
    /**
     * Initialize the automatic-send policy and boundary dependencies.
     *
     * Construction performs no authorization, filesystem, or network work.
     * The boolean configuration is resolved by Symfony from the maintained
     * environment-variable contract.
     *
     * @param bool $enabled Whether creation-time automatic sending is enabled.
     * @param AuthorizationCheckerInterface $authorizationChecker Kimai
     *     authorization service used for the custom, invoice, and customer
     *     checks.
     * @param TokenStorageInterface $tokenStorage Current security token source;
     *     automatic sending requires an authenticated Kimai `User`.
     * @param InvoiceEmailService $invoiceEmailService Existing validated send
     *     service that resolves current invoice state and crosses the mail
     *     boundary.
     * @param LoggerInterface $logger Application logger for non-sensitive skip,
     *     validation, and failure diagnostics.
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly InvoiceEmailService $invoiceEmailService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Automatically submit a newly created invoice when policy permits.
     *
     * The method returns without side effects when automatic sending is
     * disabled, when no authenticated Kimai user is available, or when the
     * initiating user lacks any required authorization.  When eligible, the
     * existing invoice-email service revalidates recipient, file, sender, and
     * cancellation state immediately before dispatch.
     *
     * All failures are contained at this boundary.  The invoice has already
     * been persisted before Kimai emits `InvoiceCreatedEvent`; rethrowing a
     * mail failure would therefore misrepresent the state of invoice creation.
     * Manual send remains the recovery path after a failed automatic attempt.
     *
     * @param Invoice $invoice Newly persisted Kimai invoice emitted by
     *     `InvoiceCreatedEvent`; the object is read but not mutated.
     * @return void
     */
    public function sendIfEligible(Invoice $invoice): void
    {
        if (!$this->enabled) {
            return;
        }

        $user = null;

        try {
            $token = $this->tokenStorage->getToken();
            $tokenUser = $token?->getUser();

            if (!$tokenUser instanceof User) {
                $this->logger->debug(
                    'Automatic invoice email skipped without authenticated Kimai user',
                    ['invoice_id' => $invoice->getId()]
                );

                return;
            }

            $user = $tokenUser;
            $customer = $invoice->getCustomer();

            if (
                !$this->authorizationChecker->isGranted('email_invoice')
                || !$this->authorizationChecker->isGranted('view_invoice', $invoice)
                || (
                    $customer !== null
                    && !$this->authorizationChecker->isGranted('access', $customer)
                )
            ) {
                $this->logger->debug(
                    'Automatic invoice email skipped by authorization policy',
                    [
                        'invoice_id' => $invoice->getId(),
                        'user_id' => $user->getId(),
                    ]
                );

                return;
            }

            $this->invoiceEmailService->send($invoice, $user);

            $this->logger->debug('Automatic invoice email submitted through Kimai', [
                'invoice_id' => $invoice->getId(),
                'user_id' => $user->getId(),
            ]);
        } catch (InvoiceEmailException $exception) {
            $this->logger->warning('Automatic invoice email validation failed', [
                'invoice_id' => $invoice->getId(),
                'user_id' => $user->getId(),
                'reason' => $exception->getTranslationKey(),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Automatic invoice email submission failed', [
                'invoice_id' => $invoice->getId(),
                'user_id' => $user?->getId(),
                'exception_class' => $exception::class,
            ]);
        }
    }
}
