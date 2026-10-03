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
 * Represents a user-actionable invoice-email validation failure.
 *
 * The exception message is a translation key rather than the rejected
 * recipient, attachment path, or other sensitive runtime value.  A previous
 * exception may be retained for diagnostic causality, but callers should expose
 * only the translation key to the operator.
 *
 * These exceptions distinguish expected validation failure from unexpected
 * transport or infrastructure failure at the controller boundary.
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
     * @param string $translationKey Translation key safe for the controller to
     *     translate and expose without embedding rejected sensitive state.
     * @param \Throwable|null $previous Original validation exception retained
     *     for diagnostic causality; it is not part of the user-facing message.
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
     * @return string Translation key in the default messages domain; the value
     *     contains no recipient address, attachment path, or transport detail.
     */
    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }
}
