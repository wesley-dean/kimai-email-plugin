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

namespace KimaiPlugin\InvoiceEmailerBundle\Service;

use App\Configuration\MailConfiguration;
use App\Entity\Invoice;
use App\Entity\User;
use App\Event\EmailEvent;
use App\Invoice\InvoiceService;
use KimaiPlugin\InvoiceEmailerBundle\Exception\InvoiceEmailException;
use KimaiPlugin\InvoiceEmailerBundle\Model\InvoiceEmailPreview;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Owns validation, message construction, and Kimai email-event dispatch.
 *
 * The service resolves recipient, generated invoice file, sender
 * configuration, and subject from current Kimai state.  Preview data is never
 * accepted back as authority for a later send.  The service does not persist
 * post-send audit metadata or change invoice status.
 *
 * File access is delegated to Kimai's `InvoiceService`; no request-controlled
 * filesystem path enters this class.  Dispatch crosses the external
 * communication boundary through Kimai's `EmailEvent`.  A successful return
 * means the event dispatch completed without error, not that a remote mailbox
 * accepted or delivered the message.
 *
 * Routine logs contain invoice/user identifiers only.  Recipient addresses and
 * attachment paths are deliberately excluded from maintained log context.
 *
 * @see \KimaiPlugin\InvoiceEmailerBundle\Controller\InvoiceEmailerController
 */
final class InvoiceEmailService
{
    /**
     * Initialize the send service with Kimai-owned boundary dependencies.
     *
     * The service does not open network connections during construction.
     * Dependencies are retained for later filesystem resolution, message
     * translation, non-sensitive logging, and event dispatch.
     *
     * @param InvoiceService $invoiceService Kimai invoice service that resolves
     *     the generated invoice file from the current invoice object.
     * @param MailConfiguration $mailConfiguration Kimai sender configuration
     *     consulted during each preview/send validation.
     * @param EventDispatcherInterface $eventDispatcher Kimai event dispatcher
     *     used to cross into the configured mail pipeline.
     * @param TranslatorInterface $translator Translator used for localized
     *     subject and fallback customer text.
     * @param LoggerInterface $logger Application logger used for non-sensitive
     *     send-attempt identifiers.
     */
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly MailConfiguration $mailConfiguration,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Resolve and validate the operator-visible details for a pending send.
     *
     * The method reads current customer data, Kimai sender configuration, and
     * generated-invoice file metadata.  No message is dispatched and no
     * persistent state is modified.  The returned object intentionally omits
     * filesystem paths and must not be treated as send-authoritative state.
     *
     * @param Invoice $invoice Current Kimai invoice selected for manual sending;
     *     the object is read but not mutated.
     * @return InvoiceEmailPreview Immutable presentation snapshot for human
     *     confirmation.
     * @throws InvoiceEmailException The invoice is canceled, lacks a usable
     *     customer/recipient/file, contains an invalid recipient, or Kimai lacks
     *     sender configuration.
     */
    public function preview(Invoice $invoice): InvoiceEmailPreview
    {
        [$preview] = $this->prepare($invoice);

        return $preview;
    }

    /**
     * Submit one invoice email through Kimai's EmailEvent mail path.
     *
     * Validation is repeated immediately before message construction so the
     * send does not trust values displayed by an earlier confirmation request.
     * The method reads the current generated invoice file, constructs one
     * `TemplatedEmail`, logs only invoice/user identifiers, and dispatches one
     * `EmailEvent`.  The configured Kimai/Symfony transport may perform
     * network I/O as a consequence of that dispatch.
     *
     * The method does not modify invoice status or persist post-send audit
     * state.  A normal return does not establish recipient delivery.
     *
     * @param Invoice $invoice Current invoice whose authoritative send state is
     *     resolved immediately before dispatch; the object is not mutated.
     * @param User|null $user Authenticated Kimai user initiating the send, or
     *     null when no Kimai user context is available; only the user ID may be
     *     logged.
     * @return void
     * @throws InvoiceEmailException The current invoice/customer/file/sender
     *     state is not valid for sending.
     * @throws \Throwable Kimai's event or configured mail path rejects or fails
     *     the submission; dependency exceptions intentionally propagate to the
     *     controller boundary.
     */
    public function send(Invoice $invoice, ?User $user = null): void
    {
        [$preview, $invoiceFile, $recipient] = $this->prepare($invoice);

        $message = (new TemplatedEmail())
            ->to($recipient)
            ->subject($preview->subject)
            ->textTemplate('@InvoiceEmailer/send.text.twig')
            ->htmlTemplate('@InvoiceEmailer/send.html.twig')
            ->context([
                'invoice' => $invoice,
            ])
            ->attachFromPath(
                $invoiceFile->getPathname(),
                $preview->attachmentName
            );

        $this->logger->debug('Submitting manual invoice email through Kimai', [
            'invoice_id' => $invoice->getId(),
            'user_id' => $user?->getId(),
        ]);

        $this->eventDispatcher->dispatch(new EmailEvent($message));
    }

    /**
     * Resolve the current authoritative state required for preview or send.
     *
     * The method validates cancellation state, customer/recipient state,
     * generated invoice readability, and Kimai sender configuration.  Recipient
     * syntax is normalized through Symfony Mime `Address`; attachment
     * selection is delegated to `InvoiceService`.
     *
     * The returned `SplFileInfo` refers to Kimai-managed invoice storage.
     * Callers must not substitute request-controlled paths for that object.
     *
     * @param Invoice $invoice Current invoice whose customer, number, status,
     *     and generated file are read without mutation.
     * @return array{0: InvoiceEmailPreview, 1: \SplFileInfo, 2: Address}
     *     Presentation snapshot, readable Kimai-managed invoice file, and
     *     validated recipient address derived from the same current state.
     * @throws InvoiceEmailException The invoice is canceled, lacks a customer,
     *     recipient, readable generated file, or sender configuration, or the
     *     recipient address is syntactically invalid.
     */
    private function prepare(Invoice $invoice): array
    {
        if ($invoice->isCanceled()) {
            throw new InvoiceEmailException(InvoiceEmailException::CANCELED);
        }

        $customer = $invoice->getCustomer();
        if ($customer === null) {
            throw new InvoiceEmailException(InvoiceEmailException::MISSING_CUSTOMER);
        }

        $email = trim((string) $customer->getEmail());
        if ($email === '') {
            throw new InvoiceEmailException(InvoiceEmailException::MISSING_RECIPIENT);
        }

        try {
            $recipient = new Address($email);
        } catch (RfcComplianceException $exception) {
            throw new InvoiceEmailException(
                InvoiceEmailException::INVALID_RECIPIENT,
                $exception
            );
        }

        $invoiceFile = $this->invoiceService->getInvoiceFile($invoice);
        if ($invoiceFile === null || !$invoiceFile->isReadable()) {
            throw new InvoiceEmailException(InvoiceEmailException::MISSING_FILE);
        }

        $sender = $this->mailConfiguration->getFromAddress();
        if ($sender === null) {
            throw new InvoiceEmailException(InvoiceEmailException::MISSING_SENDER);
        }

        $invoiceNumber = (string) ($invoice->getInvoiceNumber() ?? '');
        $customerName = trim((string) ($customer->getCompany() ?: $customer->getName()));
        if ($customerName === '') {
            $customerName = $this->translator->trans('invoice.emailer.unknown_customer');
        }

        $subject = $this->translator->trans('invoice.emailer.subject', [
            '%number%' => $invoiceNumber,
        ]);

        $preview = new InvoiceEmailPreview(
            $invoiceNumber,
            $customerName,
            $recipient->getAddress(),
            $sender,
            $subject,
            $invoiceFile->getFilename()
        );

        return [$preview, $invoiceFile, $recipient];
    }
}
