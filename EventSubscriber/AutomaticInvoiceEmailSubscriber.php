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

namespace KimaiPlugin\InvoiceEmailerBundle\EventSubscriber;

use App\Event\InvoiceCreatedEvent;
use KimaiPlugin\InvoiceEmailerBundle\Service\AutomaticInvoiceEmailService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adapts Kimai's creation-only invoice event to automatic-send policy.
 *
 * Only `InvoiceCreatedEvent` is observed.  Ordinary invoice saves and status
 * updates are intentionally excluded so editing an existing invoice cannot
 * create an implicit resend.  A negative priority places the side effect after
 * ordinary default-priority creation listeners while preserving Symfony's
 * normal event-dispatch semantics.
 *
 * The subscriber itself contains no authorization, message construction, or
 * transport policy; those responsibilities belong to
 * `AutomaticInvoiceEmailService` and `InvoiceEmailService`.
 *
 * @see \KimaiPlugin\InvoiceEmailerBundle\Service\AutomaticInvoiceEmailService
 */
final class AutomaticInvoiceEmailSubscriber implements EventSubscriberInterface
{
    /**
     * Initialize the event adapter with the automatic-send policy service.
     *
     * @param AutomaticInvoiceEmailService $automaticInvoiceEmailService
     *     Policy service that decides whether the new invoice may be sent and
     *     contains all failures.
     */
    public function __construct(
        private readonly AutomaticInvoiceEmailService $automaticInvoiceEmailService
    ) {
    }

    /**
     * Subscribe only to the creation-specific Kimai invoice event.
     *
     * @return array<class-string, array{0: string, 1: int}> Event map using a
     *     negative priority so default-priority creation listeners run first.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            InvoiceCreatedEvent::class => ['onInvoiceCreated', -1024],
        ];
    }

    /**
     * Delegate a newly created invoice to the automatic-send policy.
     *
     * The event is emitted after Kimai has persisted the invoice and generated
     * its invoice file.  The delegated service contains validation,
     * authorization, and transport failures so this listener does not turn
     * mail availability into an invoice-creation availability dependency.
     *
     * @param InvoiceCreatedEvent $event Kimai event carrying the newly created
     *     persisted invoice.
     * @return void
     */
    public function onInvoiceCreated(InvoiceCreatedEvent $event): void
    {
        $this->automaticInvoiceEmailService->sendIfEligible($event->getInvoice());
    }
}
