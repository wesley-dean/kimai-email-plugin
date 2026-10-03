<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin.
 *
 * Copyright (c) 2026 Wes Dean.
 * Copyright (c) 2024 ADK Interactive, LLC.
 *
 * The maintained implementation is a substantial modernization of concepts
 * from the ADK Interactive Invoice Emailer plugin.  See UPSTREAM.md for
 * provenance and licensing details.
 */

namespace KimaiPlugin\InvoiceEmailerBundle\EventSubscriber;

use App\Entity\Invoice;
use App\Event\PageActionsEvent;
use App\EventSubscriber\Actions\AbstractActionsSubscriber;

/**
 * Adds the manual-send action only for invoices the current user may email.
 *
 * Visibility is intentionally constrained by the same authorization layers
 * enforced again by the controller: `email_invoice`, invoice visibility, and
 * customer access.  Hiding the action is a usability aid, not the security
 * boundary; direct route access remains protected by the controller.
 *
 * The subscriber mutates only the current `PageActionsEvent`; it does not
 * send mail, modify invoice/customer state, or expose attachment bytes.
 *
 * @see \KimaiPlugin\InvoiceEmailerBundle\Controller\InvoiceEmailerController
 */
final class InvoiceActionsSubscriber extends AbstractActionsSubscriber
{
    /**
     * Return the Kimai page-action name handled by this subscriber.
     *
     * @return string The stable `invoice` action name used by Kimai to invoke
     *     this subscriber for invoice rows.
     */
    public static function getActionName(): string
    {
        return 'invoice';
    }

    /**
     * Add a confirmation link when the current invoice can be emailed.
     *
     * The method reads the invoice from the event payload and evaluates the
     * current user's custom, invoice, and customer permissions before mutating
     * the event.  Canceled invoices, invoices without a customer, unresolved
     * invoice payloads, and invoices outside the current authorization scope
     * receive no plugin action.
     *
     * When another action already exists, the method also adds a divider before
     * appending the email confirmation action.
     *
     * @param PageActionsEvent $event Mutable invoice-row action event supplied
     *     by Kimai; the method may append a divider and one action.
     * @return void
     */
    public function onActions(PageActionsEvent $event): void
    {
        $payload = $event->getPayload();
        $invoice = $payload['invoice'] ?? null;

        if (!$invoice instanceof Invoice || $invoice->getId() === null) {
            return;
        }

        if ($invoice->isCanceled()) {
            return;
        }

        $customer = $invoice->getCustomer();
        if ($customer === null) {
            return;
        }

        if (
            !$this->isGranted('email_invoice')
            || !$this->isGranted('view_invoice', $invoice)
            || !$this->isGranted('access', $customer)
        ) {
            return;
        }

        if ($event->countActions() > 0) {
            $event->addDivider();
        }

        $event->addAction('email_invoice', [
            'url' => $this->path('invoice_emailer_confirm', [
                'id' => $invoice->getId(),
            ]),
            'title' => 'invoice.emailer_send',
            'icon' => 'mail',
        ]);
    }
}
