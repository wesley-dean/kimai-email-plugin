<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin.
 *
 * Copyright (c) 2026 Wes Dean.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\InvoiceEmailerBundle\Model;

/**
 * Immutable, presentation-safe description of a pending invoice email.
 *
 * Filesystem paths are deliberately excluded so the confirmation view receives
 * only data that may be displayed to the operator.
 */
final class InvoiceEmailPreview
{
    /**
     * @param string $invoiceNumber Invoice number displayed to the operator.
     * @param string $customerName Customer name displayed to the operator.
     * @param string $recipient Resolved customer email address.
     * @param string $sender Sender address configured in Kimai.
     * @param string $subject Resolved email subject.
     * @param string $attachmentName Attachment filename presented to the user.
     */
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly string $customerName,
        public readonly string $recipient,
        public readonly string $sender,
        public readonly string $subject,
        public readonly string $attachmentName
    ) {
    }
}
