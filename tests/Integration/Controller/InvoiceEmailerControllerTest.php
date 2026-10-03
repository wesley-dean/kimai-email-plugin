<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin.
 *
 * Copyright (c) 2026 Wes Dean.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\InvoiceEmailerBundle\Tests\Integration\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use App\Event\EmailEvent;
use App\Tests\Controller\AbstractControllerBaseTestCase;
use App\Tests\DataFixtures\InvoiceFixtures;
use App\Utils\FileHelper;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the manual-send HTTP security contract inside Kimai's real kernel.
 *
 * The suite uses Kimai authentication, authorization, routing, CSRF handling,
 * Doctrine-backed fixtures, Twig rendering, and the real event dispatcher.
 * Individual tests may create database state and generated-invoice files in the
 * disposable test environment.  Cleanup removes only files recorded by this
 * test instance.
 *
 * The suite is evidence for ADR-003 and the STRIDE controls around
 * authorization, CSRF, stale confirmation state, escaping, and mail-event
 * dispatch.  It does not prove external SMTP delivery; that boundary is
 * covered by the staging harness.
 */
#[Group('integration')]
final class InvoiceEmailerControllerTest extends AbstractControllerBaseTestCase
{
    /**
     * Generated invoice files created by this test instance for later cleanup.
     *
     * @var list<string> Absolute paths beneath Kimai's disposable invoice-data
     *     directory.
     */
    private array $invoiceFiles = [];

    /**
     * Remove generated invoice files created by the completed test.
     *
     * The method mutates the disposable filesystem by deleting only paths
     * recorded in `$invoiceFiles`, then delegates remaining fixture cleanup to
     * Kimai's parent test case.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->invoiceFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->invoiceFiles = [];
        parent::tearDown();
    }

    /**
     * Verify the custom email permission is required in addition to invoice
     * access.
     *
     * An authenticated administrator with ordinary invoice access must receive
     * HTTP 403 when the dedicated `email_invoice` capability is absent.
     *
     * @return void
     */
    public function testAdminWithoutEmailPermissionCannotConfirm(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $invoice = $this->createSendableInvoice();

        $this->request(
            $client,
            '/invoice/emailer/confirm/' . $invoice->getId()
        );

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $client->getResponse()->getStatusCode()
        );
    }


    /**
     * Verify email permission alone does not substitute for invoice visibility.
     *
     * A user granted only the custom email permission must still receive HTTP
     * 403 because specific-invoice visibility remains independently required.
     *
     * @return void
     */
    public function testEmailPermissionWithoutInvoicePermissionCannotConfirm(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_USER);

        $this->grantPermissions(
            User::ROLE_USER,
            'TEST_EMAIL_INVOICE_ONLY',
            ['email_invoice']
        );

        $client->loginUser(
            $this->getUserByRole(User::ROLE_USER),
            'secured_area'
        );
        $invoice = $this->createSendableInvoice();

        $this->request(
            $client,
            '/invoice/emailer/confirm/' . $invoice->getId()
        );

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $client->getResponse()->getStatusCode()
        );
    }

    /**
     * Verify GET confirmation does not dispatch an email.
     *
     * The observational confirmation request must render one POST form and
     * leave the captured `EmailEvent` buffer empty.
     *
     * @return void
     */
    public function testConfirmationGetHasNoEmailSideEffect(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $invoice = $this->createSendableInvoice();

        $events = [];
        $this->captureEmailEvents($events);

        $crawler = $this->request(
            $client,
            '/invoice/emailer/confirm/' . $invoice->getId()
        );

        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertCount(0, $events);

        $form = $crawler->filter(
            'form[action*="/invoice/emailer/send/"]'
        );
        self::assertCount(1, $form);
        self::assertSame('post', strtolower((string) $form->attr('method')));
        self::assertCount(1, $form->filter('input[name="_token"]'));
    }


    /**
     * Verify customer-controlled confirmation text remains HTML-escaped.
     *
     * The test persists HTML-like customer data and verifies Twig renders it
     * as text rather than executable markup.
     *
     * @return void
     */
    public function testConfirmationEscapesCustomerControlledText(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $invoice = $this->createSendableInvoice();

        $customer = $invoice->getCustomer();
        self::assertNotNull($customer);
        $customer->setCompany('<strong>Untrusted customer</strong>');
        $this->getEntityManager()->flush();

        $this->request(
            $client,
            '/invoice/emailer/confirm/' . $invoice->getId()
        );

        self::assertTrue($client->getResponse()->isSuccessful());
        $content = (string) $client->getResponse()->getContent();

        self::assertStringNotContainsString(
            '<strong>Untrusted customer</strong>',
            $content
        );
        self::assertStringContainsString(
            '&lt;strong&gt;Untrusted customer&lt;/strong&gt;',
            $content
        );
    }

    /**
     * Verify the send route cannot be invoked with GET.
     *
     * Calling the side-effecting route with GET must fail at the HTTP routing
     * boundary before mail dispatch can occur.
     *
     * @return void
     */
    public function testSendRouteRejectsGet(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $invoice = $this->createSendableInvoice();

        $this->request(
            $client,
            '/invoice/emailer/send/' . $invoice->getId()
        );

        self::assertSame(
            Response::HTTP_METHOD_NOT_ALLOWED,
            $client->getResponse()->getStatusCode()
        );
    }

    /**
     * Verify POST rejects an invalid CSRF token without dispatching email.
     *
     * The request reaches the authenticated route but must fail with HTTP 403
     * before an `EmailEvent` is emitted.
     *
     * @return void
     */
    public function testSendRejectsInvalidCsrfToken(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $invoice = $this->createSendableInvoice();

        $events = [];
        $this->captureEmailEvents($events);

        $this->request(
            $client,
            '/invoice/emailer/send/' . $invoice->getId(),
            'POST',
            ['_token' => 'not-a-valid-token']
        );

        self::assertSame(
            Response::HTTP_FORBIDDEN,
            $client->getResponse()->getStatusCode()
        );
        self::assertCount(0, $events);
    }

    /**
     * Verify one valid confirmation POST dispatches exactly one invoice email.
     *
     * The test follows the real confirmation form, submits its generated CSRF
     * token, and inspects the single `EmailEvent` produced by Kimai's
     * dispatcher.
     *
     * @return void
     */
    public function testConfirmedPostDispatchesExactlyOneEmailEvent(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $client->disableReboot();
        $invoice = $this->createSendableInvoice();

        $events = [];
        $this->captureEmailEvents($events);

        $crawler = $this->request(
            $client,
            '/invoice/emailer/confirm/' . $invoice->getId()
        );
        self::assertTrue($client->getResponse()->isSuccessful());
        self::assertCount(0, $events);

        $customer = $invoice->getCustomer();
        self::assertNotNull($customer);
        $expectedRecipient = $customer->getEmail();
        self::assertNotNull($expectedRecipient);
        self::assertNotSame('', trim($expectedRecipient));

        $form = $crawler
            ->filter('form[action*="/invoice/emailer/send/"]')
            ->form();
        $client->submit($form);

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertCount(1, $events);

        $message = $events[0]->getEmail();
        self::assertSame(
            'Invoice ' . $invoice->getInvoiceNumber(),
            $message->getSubject()
        );
        self::assertCount(1, $message->getTo());
        self::assertSame(
            $expectedRecipient,
            $message->getTo()[0]->getAddress()
        );
        self::assertCount(1, $message->getAttachments());
        self::assertCount(1, $message->getFrom());
        self::assertSame(
            'kimai@example.com',
            $message->getFrom()[0]->getAddress()
        );
    }

    /**
     * Verify canceled invoices cannot reach the confirmation form.
     *
     * A canceled invoice must redirect before rendering a send form or
     * emitting an `EmailEvent`.
     *
     * @return void
     */
    public function testCanceledInvoiceIsRejectedWithoutEmailDispatch(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);
        $invoice = $this->createSendableInvoice(Invoice::STATUS_CANCELED);

        $events = [];
        $this->captureEmailEvents($events);

        $this->request(
            $client,
            '/invoice/emailer/confirm/' . $invoice->getId()
        );

        self::assertTrue($client->getResponse()->isRedirect());
        self::assertCount(0, $events);
    }

    /**
     * Create one database-backed invoice and its generated invoice file.
     *
     * The helper imports Kimai's invoice fixture, writes a deterministic
     * PDF-like payload into Kimai's normal invoice-data directory, and records
     * the absolute path for tear-down cleanup.  The returned invoice is
     * persisted and may be mutated further by an individual test.
     *
     * @param string $status Kimai invoice status assigned to the imported
     *     fixture.
     * @return Invoice Persisted invoice whose generated file exists and is
     *     readable by `InvoiceService`.
     */
    private function createSendableInvoice(
        string $status = Invoice::STATUS_NEW
    ): Invoice {
        $fixture = new InvoiceFixtures();
        $fixture->setAmount(1);
        $fixture->setStatus([$status]);

        $invoice = $this->importFixture($fixture)[0];
        $customer = $invoice->getCustomer();
        self::assertNotNull($customer);

        /** @var FileHelper $fileHelper */
        $fileHelper = self::getContainer()->get(FileHelper::class);
        $path = $fileHelper->getDataDirectory('invoices')
            . $invoice->getInvoiceFilename();

        file_put_contents($path, '%PDF-1.4 integration test invoice');
        $this->invoiceFiles[] = $path;

        return $invoice;
    }

    /**
     * Capture EmailEvent instances emitted by the real Kimai dispatcher.
     *
     * The helper mutates both the caller-supplied buffer and the test
     * dispatcher's listener set.  A high listener priority captures the event
     * before ordinary downstream test behavior can obscure the plugin's
     * emission count.
     *
     * @param array<int, EmailEvent> $events Mutable event capture buffer that
     *     receives each dispatched `EmailEvent` by reference.
     * @return void
     */
    private function captureEmailEvents(array &$events): void
    {
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(
            EmailEvent::class,
            static function (EmailEvent $event) use (&$events): void {
                $events[] = $event;
            },
            4096
        );
    }
}
