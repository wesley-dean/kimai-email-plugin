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
 * Dispatch crosses the external mail boundary through Kimai's `EmailEvent`.
 * Successful dispatch therefore means submission to Kimai's configured mail
 * path, not recipient delivery.
 */
final class InvoiceEmailService
{
    /**
     * @param InvoiceService $invoiceService Kimai invoice service used for file resolution.
     * @param MailConfiguration $mailConfiguration Kimai mail sender configuration.
     * @param EventDispatcherInterface $eventDispatcher Kimai event dispatcher.
     * @param TranslatorInterface $translator Translator used for message content.
     * @param LoggerInterface $logger Application logger.
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
     * No message is dispatched and no persistent state is modified.  The
     * returned object intentionally omits filesystem paths and must not be
     * treated as send-authoritative state.
     *
     * @param Invoice $invoice Invoice selected for manual sending.
     * @return InvoiceEmailPreview Presentation-safe send details.
     * @throws InvoiceEmailException The invoice cannot currently be sent.
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
     *
     * @param Invoice $invoice Invoice selected for manual sending.
     * @param User|null $user Authenticated Kimai user initiating the send.
     * @return void
     * @throws InvoiceEmailException The invoice cannot currently be sent.
     * Dispatch logs only invoice and initiating-user identifiers; recipient
     * addresses and attachment paths are not added to the plugin log context.
     *
     * @throws \Throwable Kimai's configured mail path rejects or fails the send.
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
     * Resolve current authoritative send state.
     *
     * This method validates cancellation state, customer and recipient,
     * generated invoice readability, and Kimai sender configuration before
     * returning the data needed to construct a message.
     *
     * @param Invoice $invoice Invoice selected for manual sending.
     * @return array{0: InvoiceEmailPreview, 1: \SplFileInfo, 2: Address}
     * @throws InvoiceEmailException The invoice cannot currently be sent.
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
