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
 *
 * Tests replace filesystem resolution, event dispatch, and translation
 * boundaries with deterministic doubles while retaining real Kimai entities
 * and Symfony Mime message objects.  Temporary attachment files are created in
 * the operating-system temporary directory and removed after each test.
 *
 * This suite verifies validation, current-state re-resolution, message
 * construction, and the absence of dispatch on rejected inputs.  It does not
 * exercise Kimai authorization or external mail transport behavior.
 */
final class InvoiceEmailServiceTest extends TestCase
{
    /**
     * Temporary attachment files owned by the current test instance.
     *
     * @var list<string> Absolute paths removed during tear down.
     */
    private array $temporaryFiles = [];

    /**
     * Remove temporary invoice files created by the completed unit test.
     *
     * The method mutates only files recorded in `$temporaryFiles` and then
     * delegates remaining PHPUnit cleanup to the parent class.
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
     * The test confirms preview reads current invoice/file/sender state, emits no event, and exposes only display-safe attachment metadata.
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
     * The captured event is inspected for recipient, subject, maintained templates, attachment count, and deliberate absence of an explicit From address before Kimai policy is applied.
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
     * Customer email is changed after preview; send must use the new current value rather than the stale confirmation snapshot.
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
     * Neither `InvoiceService::getInvoiceFile()` nor event dispatch may be reached after cancellation is detected.
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
     * Missing recipient state must become the maintained user-actionable validation exception before file or mail work begins.
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
     * Symfony Mime address validation must be converted into the plugin's user-safe invalid-recipient exception.
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
     * A null file resolution must fail before an `EmailEvent` is constructed or emitted.
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
     * An empty configured sender is treated as unsendable even though the message intentionally leaves its explicit From header unset for Kimai.
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
     * Create a deterministic sendable invoice entity for unit tests.
     *
     * The returned object is unsaved and owned by the caller.  Its customer,
     * recipient, invoice number, filename, and status are initialized to known
     * values that individual tests may mutate.
     *
     * @return Invoice Unsaved invoice with deterministic customer and message
     *     metadata.
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
     * Create and register a readable temporary invoice attachment.
     *
     * The helper writes a deterministic PDF-like payload to the operating
     * system temporary directory and records the path for tear-down cleanup.
     *
     * @return string Absolute path to the caller-owned temporary attachment.
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
     * Construct the service with deterministic translation and logging.
     *
     * The translator resolves only the maintained subject and unknown-customer
     * keys needed by these tests; all other keys are returned unchanged.  A
     * `NullLogger` prevents tests from writing operational logs.
     *
     * @param InvoiceService $invoiceService Kimai invoice service or test double
     *     controlling generated-file resolution.
     * @param EventDispatcherInterface $dispatcher Event dispatcher or test
     *     double controlling observation of mail-event side effects.
     * @param MailConfiguration $mailConfiguration Sender configuration supplied
     *     to the service under test.
     * @return InvoiceEmailService Isolated service instance with deterministic
     *     translation and no logging side effect.
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
