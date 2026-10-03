<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin.
 *
 * Copyright (c) 2026 Wes Dean.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\InvoiceEmailerBundle\Exception;

/**
 * Represents a user-actionable invoice email validation failure.
 */
final class InvoiceEmailException extends \RuntimeException
{
    public const CANCELED = 'invoice.emailer.error.canceled';
    public const INVALID_RECIPIENT = 'invoice.emailer.error.invalid_recipient';
    public const MISSING_CUSTOMER = 'invoice.emailer.error.missing_customer';
    public const MISSING_FILE = 'invoice.emailer.error.missing_file';
    public const MISSING_RECIPIENT = 'invoice.emailer.error.missing_recipient';
    public const MISSING_SENDER = 'invoice.emailer.error.missing_sender';

    /**
     * @param string $translationKey Translation key safe to expose to the user.
     * @param \Throwable|null $previous Original exception, when available.
     */
    public function __construct(
        private readonly string $translationKey,
        ?\Throwable $previous = null
    ) {
        parent::__construct($translationKey, 0, $previous);
    }

    /**
     * Return the user-safe translation key for this failure.
     *
     * @return string Translation key in the default messages domain.
     */
    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }
}
