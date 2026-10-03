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
 * Carries the immutable presentation snapshot shown before a manual send.
 *
 * Filesystem paths and other send-authoritative state are deliberately
 * excluded.  The public readonly values are safe for confirmation rendering
 * but are informational only; callers must not persist or return them as
 * authority for a later send.  The service re-resolves current invoice,
 * customer, file, and mail state during POST.
 *
 * The object owns no external resource and has no cleanup lifecycle.
 */
final class InvoiceEmailPreview
{
    /**
     * Initialize one presentation-only confirmation snapshot.
     *
     * All values are copied as immutable display state.  In particular,
     * `recipient`, `sender`, and `attachmentName` must not be trusted by a
     * later send operation without re-resolution from current Kimai state.
     *
     * @param string $invoiceNumber Invoice number displayed to the operator.
     * @param string $customerName Customer name displayed to the operator.
     * @param string $recipient Resolved customer email address shown for human
     *     review.
     * @param string $sender Sender address configured in Kimai and shown for
     *     human review.
     * @param string $subject Resolved localized email subject.
     * @param string $attachmentName Basename of the generated invoice file;
     *     no filesystem path is exposed.
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
