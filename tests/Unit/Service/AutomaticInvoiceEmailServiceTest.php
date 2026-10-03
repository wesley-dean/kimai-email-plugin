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
use App\Entity\User;
use App\Event\EmailEvent;
use App\Invoice\InvoiceService;
use KimaiPlugin\InvoiceEmailerBundle\Service\AutomaticInvoiceEmailService;
use KimaiPlugin\InvoiceEmailerBundle\Service\InvoiceEmailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Verifies creation-time automatic-send policy without booting Kimai's kernel.
 *
 * Tests isolate authentication, authorization, filesystem lookup, and mail
 * dispatch while retaining real Kimai invoice/customer/user entities.  The
 * suite proves disabled-by-default behavior, permission preservation, failure
 * containment, and successful delegation to the maintained send service.
 */
final class AutomaticInvoiceEmailServiceTest extends TestCase
{
    /**
     * Temporary attachment files owned by the current test instance.
     *
     * @var list<string>
     */
    private array $temporaryFiles = [];

    /**
     * Remove temporary invoice files created by the completed test.
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
     * Verify disabled automation does not inspect identity or send mail.
     *
     * @return void
     */
    public function testDisabledAutomationDoesNothing(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects(self::never())->method('getToken');

        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->expects(self::never())->method('isGranted');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $service = new AutomaticInvoiceEmailService(
            false,
            $authorization,
            $tokenStorage,
            $this->createSendService($dispatcher),
            new NullLogger()
        );

        $service->sendIfEligible($this->createInvoice());
        self::addToAssertionCount(1);
    }

    /**
     * Verify automation requires an authenticated Kimai user context.
     *
     * @return void
     */
    public function testAutomationWithoutAuthenticatedUserDoesNothing(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->expects(self::never())->method('isGranted');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $service = new AutomaticInvoiceEmailService(
            true,
            $authorization,
            $tokenStorage,
            $this->createSendService($dispatcher),
            new NullLogger()
        );

        $service->sendIfEligible($this->createInvoice());
        self::addToAssertionCount(1);
    }

    /**
     * Verify the custom email permission remains required for automation.
     *
     * @return void
     */
    public function testUnderAuthorizedCreatorDoesNotSend(): void
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization
            ->expects(self::once())
            ->method('isGranted')
            ->with('email_invoice')
            ->willReturn(false);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $service = new AutomaticInvoiceEmailService(
            true,
            $authorization,
            $this->createTokenStorage(new User()),
            $this->createSendService($dispatcher),
            new NullLogger()
        );

        $service->sendIfEligible($this->createInvoice());
        self::addToAssertionCount(1);
    }

    /**
     * Verify customer access remains an independent automatic-send restriction.
     *
     * @return void
     */
    public function testCustomerAccessDenialPreventsSend(): void
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization
            ->method('isGranted')
            ->willReturnCallback(
                static function (string $attribute): bool {
                    return $attribute !== 'access';
                }
            );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $service = new AutomaticInvoiceEmailService(
            true,
            $authorization,
            $this->createTokenStorage(new User()),
            $this->createSendService($dispatcher),
            new NullLogger()
        );

        $service->sendIfEligible($this->createInvoice());
        self::addToAssertionCount(1);
    }

    /**
     * Verify an authorized creation submits exactly one email event.
     *
     * @return void
     */
    public function testAuthorizedCreatorSendsExactlyOnce(): void
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn(true);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(EmailEvent::class))
            ->willReturnArgument(0);

        $service = new AutomaticInvoiceEmailService(
            true,
            $authorization,
            $this->createTokenStorage(new User()),
            $this->createSendService($dispatcher, $this->createInvoiceFile()),
            new NullLogger()
        );

        $service->sendIfEligible($this->createInvoice());
    }

    /**
     * Verify validation failure is contained after invoice creation.
     *
     * @return void
     */
    public function testValidationFailureDoesNotEscape(): void
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn(true);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::never())->method('dispatch');

        $invoice = $this->createInvoice();
        $invoice->getCustomer()?->setEmail(null);

        $service = new AutomaticInvoiceEmailService(
            true,
            $authorization,
            $this->createTokenStorage(new User()),
            $this->createSendService($dispatcher),
            new NullLogger()
        );

        $service->sendIfEligible($invoice);
        self::addToAssertionCount(1);
    }

    /**
     * Verify transport failure is contained after invoice creation.
     *
     * @return void
     */
    public function testTransportFailureDoesNotEscape(): void
    {
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn(true);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('transport unavailable'));

        $service = new AutomaticInvoiceEmailService(
            true,
            $authorization,
            $this->createTokenStorage(new User()),
            $this->createSendService($dispatcher, $this->createInvoiceFile()),
            new NullLogger()
        );

        $service->sendIfEligible($this->createInvoice());
        self::addToAssertionCount(1);
    }

    /**
     * Create a deterministic sendable invoice for automatic-send tests.
     *
     * @return Invoice Unsaved invoice with known customer and recipient state.
     */
    private function createInvoice(): Invoice
    {
        $customer = new Customer('Acme');
        $customer->setCompany('Acme Corporation');
        $customer->setEmail('billing@example.com');

        $invoice = new Invoice();
        $invoice->setCustomer($customer);
        $invoice->setInvoiceNumber('AUTO-0042');
        $invoice->setFilename('automatic-invoice.pdf');
        $invoice->setStatus(Invoice::STATUS_NEW);

        return $invoice;
    }

    /**
     * Create and register a readable temporary invoice attachment.
     *
     * @return string Absolute path to the caller-owned temporary attachment.
     */
    private function createInvoiceFile(): string
    {
        $file = tempnam(sys_get_temp_dir(), 'kimai-auto-email-');
        self::assertIsString($file);
        file_put_contents($file, '%PDF-1.4 automatic invoice test');

        $this->temporaryFiles[] = $file;

        return $file;
    }

    /**
     * Build the existing invoice-email service with deterministic dependencies.
     *
     * @param EventDispatcherInterface $dispatcher Mail-event dispatcher used by
     *     the send service.
     * @param string|null $file Optional readable attachment; when omitted,
     *     file resolution returns null.
     * @return InvoiceEmailService Send service used by automatic policy tests.
     */
    private function createSendService(
        EventDispatcherInterface $dispatcher,
        ?string $file = null
    ): InvoiceEmailService {
        $invoiceService = $this->createMock(InvoiceService::class);
        $invoiceService
            ->method('getInvoiceFile')
            ->willReturn($file === null ? null : new \SplFileInfo($file));

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(
                static function (string $id, array $parameters = []): string {
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
            new MailConfiguration('sender@example.com'),
            $dispatcher,
            $translator,
            new NullLogger()
        );
    }

    /**
     * Create token storage containing one authenticated Kimai user.
     *
     * @param User $user User returned by the current Symfony security token.
     * @return TokenStorageInterface Deterministic token-storage test double.
     */
    private function createTokenStorage(User $user): TokenStorageInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        return $storage;
    }
}
