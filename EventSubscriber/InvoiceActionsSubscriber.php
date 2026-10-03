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
 * boundary; the controller remains authoritative.
 */
final class InvoiceActionsSubscriber extends AbstractActionsSubscriber
{
    /**
     * Return the Kimai page-action name handled by this subscriber.
     *
     * @return string Kimai invoice action name.
     */
    public static function getActionName(): string
    {
        return 'invoice';
    }

    /**
     * Add a confirmation link when the current invoice can be emailed.
     *
     * Canceled invoices, invoices without a customer, and invoices outside the
     * current user's authorization scope receive no plugin action.
     *
     * @param PageActionsEvent $event Current invoice-row action event.
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
