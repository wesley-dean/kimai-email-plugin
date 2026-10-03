<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin.
 *
 * Copyright (c) 2026 Wes Dean.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\InvoiceEmailerBundle\Tests\Unit\Service;

use App\Configuration\MailConfiguration;
use App\Entity\Customer;
use App\Entity\Invoice;
use App\Event\EmailEvent;
use App\Invoice\InvoiceService;
use KimaiPlugin\InvoiceEmailerBundle\Exception\InvoiceEmailException;
use KimaiPlugin\InvoiceEmailerBundle\Service\InvoiceEmailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Verifies the manual-send service contract without booting Kimai's kernel.
 */
final class InvoiceEmailServiceTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $temporaryFiles = [];

    /**
     * Remove temporary invoice files created by individual tests.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->temporaryFiles = [];
        parent::tearDown();
    }

    /**
     * Verify preview resolves only presentation-safe authoritative values.
     *
     * @return void
     */
    public function testPreviewResolvesCurrentInvoiceState(): void
    {
        $invoice = $this->createInvoice();
        $file = $this->createInvoiceFile();

        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService
            ->expects(self::once())
            ->method('getInvoiceFile')
            ->with($invoice)
            ->willReturn(new \SplFileInfo($file));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $service = $this->createService(
            $invoiceService,
            $dispatcher,
            new MailConfiguration('sender@example.com')
        );

        $preview = $service->preview($invoice);

        self::assertSame('INV-0042', $preview->invoiceNumber);
        self::assertSame('Acme Corporation', $preview->customerName);
        self::assertSame('billing@example.com', $preview->recipient);
        self::assertSame('sender@example.com', $preview->sender);
        self::assertSame('Invoice INV-0042', $preview->subject);
        self::assertSame(basename($file), $preview->attachmentName);
    }

    /**
     * Verify one successful send dispatches exactly one Kimai EmailEvent.
     *
     * @return void
     */
    public function testSendDispatchesOneEmailEventWithExpectedMessage(): void
    {
        $invoice = $this->createInvoice();
        $file = $this->createInvoiceFile();

        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService
            ->expects(self::once())
            ->method('getInvoiceFile')
            ->with($invoice)
            ->willReturn(new \SplFileInfo($file));

        $captured = null;
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static function (object $event) use (&$captured): bool {
                    if (!$event instanceof EmailEvent) {
                        return false;
                    }

                    $captured = $event;

                    return true;
                }
            ))
            ->willReturnArgument(0);

        $service = $this->createService(
            $invoiceService,
            $dispatcher,
            new MailConfiguration('sender@example.com')
        );

        $service->send($invoice);

        self::assertInstanceOf(EmailEvent::class, $captured);
        $message = $captured->getEmail();
        self::assertInstanceOf(TemplatedEmail::class, $message);
        self::assertSame('Invoice INV-0042', $message->getSubject());
        self::assertCount(1, $message->getTo());
        self::assertSame('billing@example.com', $message->getTo()[0]->getAddress());
        self::assertCount(0, $message->getFrom());
        self::assertSame('@InvoiceEmailer/send.text.twig', $message->getTextTemplate());
        self::assertSame('@InvoiceEmailer/send.html.twig', $message->getHtmlTemplate());
        self::assertCount(1, $message->getAttachments());
    }


    /**
     * Verify send re-resolves recipient state after an earlier preview.
     *
     * @return void
     */
    public function testSendRevalidatesRecipientAfterPreview(): void
    {
        $invoice = $this->createInvoice();
        $file = $this->createInvoiceFile();

        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService
            ->expects(self::exactly(2))
            ->method('getInvoiceFile')
            ->with($invoice)
            ->willReturn(new \SplFileInfo($file));

        $captured = null;
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(
                static function (object $event) use (&$captured): bool {
                    if (!$event instanceof EmailEvent) {
                        return false;
                    }

                    $captured = $event;

                    return true;
                }
            ))
            ->willReturnArgument(0);

        $service = $this->createService(
            $invoiceService,
            $dispatcher,
            new MailConfiguration('sender@example.com')
        );

        $preview = $service->preview($invoice);
        self::assertSame('billing@example.com', $preview->recipient);

        $customer = $invoice->getCustomer();
        self::assertNotNull($customer);
        $customer->setEmail('current-billing@example.com');

        $service->send($invoice);

        self::assertInstanceOf(EmailEvent::class, $captured);
        self::assertCount(1, $captured->getEmail()->getTo());
        self::assertSame(
            'current-billing@example.com',
            $captured->getEmail()->getTo()[0]->getAddress()
        );
    }

    /**
     * Verify canceled invoices are rejected before file or mail interaction.
     *
     * @return void
     */
    public function testCanceledInvoiceIsRejected(): void
    {
        $invoice = $this->createInvoice();
        $invoice->setIsCanceled();

        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService->expects(self::never())->method('getInvoiceFile');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $service = $this->createService(
            $invoiceService,
            $dispatcher,
            new MailConfiguration('sender@example.com')
        );

        $this->expectException(InvoiceEmailException::class);
        $this->expectExceptionMessage(InvoiceEmailException::CANCELED);

        $service->send($invoice);
    }

    /**
     * Verify invoices without customer email cannot be sent.
     *
     * @return void
     */
    public function testMissingRecipientIsRejected(): void
    {
        $invoice = $this->createInvoice();
        $invoice->getCustomer()?->setEmail(null);

        $service = $this->createService(
            $this->createMock(InvoiceService::class),
            $this->createMock(EventDispatcherInterface::class),
            new MailConfiguration('sender@example.com')
        );

        $this->expectException(InvoiceEmailException::class);
        $this->expectExceptionMessage(InvoiceEmailException::MISSING_RECIPIENT);

        $service->preview($invoice);
    }

    /**
     * Verify syntactically invalid customer email is rejected.
     *
     * @return void
     */
    public function testInvalidRecipientIsRejected(): void
    {
        $invoice = $this->createInvoice();
        $invoice->getCustomer()?->setEmail('not-an-email-address');

        $service = $this->createService(
            $this->createMock(InvoiceService::class),
            $this->createMock(EventDispatcherInterface::class),
            new MailConfiguration('sender@example.com')
        );

        $this->expectException(InvoiceEmailException::class);
        $this->expectExceptionMessage(InvoiceEmailException::INVALID_RECIPIENT);

        $service->preview($invoice);
    }

    /**
     * Verify a missing generated invoice file prevents dispatch.
     *
     * @return void
     */
    public function testMissingInvoiceFileIsRejected(): void
    {
        $invoice = $this->createInvoice();

        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService
            ->expects(self::once())
            ->method('getInvoiceFile')
            ->with($invoice)
            ->willReturn(null);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $service = $this->createService(
            $invoiceService,
            $dispatcher,
            new MailConfiguration('sender@example.com')
        );

        $this->expectException(InvoiceEmailException::class);
        $this->expectExceptionMessage(InvoiceEmailException::MISSING_FILE);

        $service->send($invoice);
    }

    /**
     * Verify missing Kimai sender configuration is detected before dispatch.
     *
     * @return void
     */
    public function testMissingSenderIsRejected(): void
    {
        $invoice = $this->createInvoice();
        $file = $this->createInvoiceFile();

        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService
            ->method('getInvoiceFile')
            ->willReturn(new \SplFileInfo($file));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $service = $this->createService(
            $invoiceService,
            $dispatcher,
            new MailConfiguration('')
        );

        $this->expectException(InvoiceEmailException::class);
        $this->expectExceptionMessage(InvoiceEmailException::MISSING_SENDER);

        $service->send($invoice);
    }

    /**
     * Create a sendable invoice entity for unit tests.
     *
     * @return Invoice Unsaved invoice with customer and message metadata.
     */
    private function createInvoice(): Invoice
    {
        $customer = new Customer('Acme');
        $customer->setCompany('Acme Corporation');
        $customer->setEmail('billing@example.com');

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->setInvoiceNumber('INV-0042');
        $invoice->setFilename('invoice.pdf');
        $invoice->setStatus(Invoice::STATUS_NEW);

        return $invoice;
    }

    /**
     * Create a readable temporary invoice attachment.
     *
     * @return string Absolute path to the temporary file.
     */
    private function createInvoiceFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'kimai-invoice-emailer-');
        self::assertIsString($file);
        file_put_contents($file, '%PDF-1.4 test invoice');

        $this->temporaryFiles[] = $file;

        return $file;
    }

    /**
     * Construct the service with deterministic translator behavior.
     *
     * @param InvoiceService $invoiceService Kimai invoice service or test double.
     * @param EventDispatcherInterface $dispatcher Event dispatcher or test double.
     * @param MailConfiguration $mailConfiguration Mail sender configuration.
     * @return InvoiceEmailService Service under test.
     */
    private function createService(
        InvoiceService $invoiceService,
        EventDispatcherInterface $dispatcher,
        MailConfiguration $mailConfiguration
    ): InvoiceEmailService {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(
                static function (
                    string $id,
                    array $parameters = [],
                    ?string $domain = null,
                    ?string $locale = null
                ): string {
                    if ($id === 'invoice.emailer.subject') {
                        return strtr('Invoice %number%', $parameters);
                    }

                    if ($id === 'invoice.emailer.unknown_customer') {
                        return 'Unknown customer';
                    }

                    return $id;
                }
            );

        return new InvoiceEmailService(
            $invoiceService,
            $mailConfiguration,
            $dispatcher,
            $translator,
            new NullLogger()
        );
    }
}
