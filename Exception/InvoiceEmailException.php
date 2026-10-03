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
 * Represents a validation failure that is safe to translate for an operator.
 *
 * The exception carries a translation key rather than sensitive runtime state.
 * Controller code may expose the translated message without disclosing
 * recipient addresses, attachment paths, or transport details.
 */
final class InvoiceEmailException extends \RuntimeException
{
    /** @var string Translation key for a canceled invoice. */
    public const CANCELED = 'invoice.emailer.error.canceled';

    /** @var string Translation key for a syntactically invalid recipient. */
    public const INVALID_RECIPIENT = 'invoice.emailer.error.invalid_recipient';

    /** @var string Translation key for an invoice without a customer. */
    public const MISSING_CUSTOMER = 'invoice.emailer.error.missing_customer';

    /** @var string Translation key for an unavailable generated invoice file. */
    public const MISSING_FILE = 'invoice.emailer.error.missing_file';

    /** @var string Translation key for a customer without an email address. */
    public const MISSING_RECIPIENT = 'invoice.emailer.error.missing_recipient';

    /** @var string Translation key for missing Kimai sender configuration. */
    public const MISSING_SENDER = 'invoice.emailer.error.missing_sender';

    /**
     * Create a user-actionable validation exception.
     *
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
