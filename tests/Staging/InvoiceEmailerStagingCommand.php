<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin staging harness.
 *
 * Copyright (c) 2026 Wes Dean.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Utils\FileHelper;
use KimaiPlugin\InvoiceEmailerBundle\Service\InvoiceEmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exercises the packaged Invoice Emailer service through Kimai's prod kernel.
 *
 * The staging workflow copies this command into a disposable Kimai 2.67.0
 * checkout after installing the published plugin ZIP.  The command creates an
 * in-memory invoice/customer pair and a deterministic invoice file, delegates
 * the send to the plugin's real `InvoiceEmailService`, and removes the file
 * afterward.
 *
 * The command deliberately avoids production customer data, database writes,
 * and external recipients.  Its filesystem side effect is limited to one
 * deterministic file in the disposable invoice-data directory.  Its network
 * side effect is one message submitted through Kimai's production mailer to a
 * localhost-only Mailpit SMTP sink.
 *
 * This class is staging harness code, not part of the distributed plugin ZIP.
 *
 * @see \KimaiPlugin\InvoiceEmailerBundle\Service\InvoiceEmailService
 */
#[AsCommand(
    name: 'app:invoice-emailer:staging-send',
    description: 'Send one deterministic invoice email to the staging SMTP sink'
)]
final class InvoiceEmailerStagingCommand extends Command
{
    /**
     * Deterministic recipient reserved for staging validation.
     *
     * @var string
     */
    private const RECIPIENT = 'staging@example.com';

    /**
     * Deterministic invoice number used by SMTP assertions.
     *
     * @var string
     */
    private const INVOICE_NUMBER = 'STAGING-0001';

    /**
     * Deterministic generated-invoice filename used by the staging command.
     *
     * @var string
     */
    private const INVOICE_FILENAME = 'invoice-emailer-staging.pdf';

    /**
     * Deterministic PDF-like payload verified after SMTP transmission.
     *
     * @var string
     */
    private const INVOICE_CONTENT = '%PDF-1.4 staging invoice';

    /**
     * Initialize the staging command with Kimai and packaged-plugin boundaries.
     *
     * Construction performs no filesystem or network I/O.  The dependencies
     * are retained until `execute()` creates the deterministic attachment and
     * crosses the configured mail boundary.
     *
     * @param InvoiceEmailService $invoiceEmailService Packaged plugin service
     *     that validates, constructs, and dispatches the invoice email.
     * @param FileHelper $fileHelper Kimai filesystem helper used to locate the
     *     disposable generated-invoice data directory.
     */
    public function __construct(
        private readonly InvoiceEmailService $invoiceEmailService,
        private readonly FileHelper $fileHelper
    ) {
        parent::__construct();
    }

    /**
     * Submit one deterministic invoice email through Kimai's configured mailer.
     *
     * The invoice is not persisted.  A deterministic generated-invoice file is
     * written to Kimai's normal invoice data directory so the packaged plugin
     * resolves it through `InvoiceService` exactly as it would a stored
     * invoice.  The file is removed in a finally block whether sending succeeds
     * or fails.  Successful execution causes one SMTP submission through the
     * configured Kimai mailer.
     *
     * @param InputInterface $input Symfony console input; no command arguments
     *     or options are consumed.
     * @param OutputInterface $output Symfony console output used only for a
     *     non-sensitive success message.
     * @return int Command::SUCCESS after Kimai accepts the message for
     *     transport.
     * @throws \Throwable The packaged plugin or configured Kimai mail path
     *     rejects or fails the staging send.
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $customer = new Customer('Invoice Emailer Staging');
        $customer->setCompany('Invoice Emailer Staging');
        $customer->setEmail(self::RECIPIENT);

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->setInvoiceNumber(self::INVOICE_NUMBER);
        $invoice->setFilename(self::INVOICE_FILENAME);
        $invoice->setStatus(Invoice::STATUS_NEW);

        $path = $this->fileHelper->getDataDirectory('invoices')
            . self::INVOICE_FILENAME;

        file_put_contents($path, self::INVOICE_CONTENT);

        try {
            $this->invoiceEmailService->send($invoice);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $output->writeln('Staging invoice email submitted.');

        return Command::SUCCESS;
    }
}
