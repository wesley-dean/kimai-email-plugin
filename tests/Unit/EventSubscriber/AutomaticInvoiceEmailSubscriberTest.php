<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin.
 *
 * Copyright (c) 2026 Wes Dean.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\InvoiceEmailerBundle\Tests\Unit\EventSubscriber;

use App\Event\InvoiceCreatedEvent;
use KimaiPlugin\InvoiceEmailerBundle\EventSubscriber\AutomaticInvoiceEmailSubscriber;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the automatic event adapter subscribes only to invoice creation.
 */
final class AutomaticInvoiceEmailSubscriberTest extends TestCase
{
    /**
     * Verify edits and generic invoice saves cannot trigger automatic resend.
     *
     * @return void
     */
    public function testSubscribesOnlyToInvoiceCreatedEvent(): void
    {
        self::assertSame(
            [
                InvoiceCreatedEvent::class => ['onInvoiceCreated', -1024],
            ],
            AutomaticInvoiceEmailSubscriber::getSubscribedEvents()
        );
    }
}
