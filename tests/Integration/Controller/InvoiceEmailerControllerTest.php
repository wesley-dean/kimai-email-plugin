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
 */
#[Group('integration')]
final class InvoiceEmailerControllerTest extends AbstractControllerBaseTestCase
{
    /**
     * @var string[]
     */
    private array $invoiceFiles = [];

    /**
     * Remove invoice files created by integration tests.
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
     * Verify the custom email permission is required in addition to invoice access.
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
     * Verify GET confirmation does not dispatch an email.
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
     * Verify the send route cannot be invoked with GET.
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
     * @return void
     */
    public function testConfirmedPostDispatchesExactlyOneEmailEvent(): void
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
            'invoice-recipient@example.com',
            $message->getTo()[0]->getAddress()
        );
        self::assertCount(1, $message->getAttachments());
        self::assertCount(0, $message->getFrom());
    }

    /**
     * Verify canceled invoices cannot reach the confirmation form.
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
     * @param string $status Kimai invoice status assigned to the fixture.
     * @return Invoice Persisted invoice suitable for controller testing.
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

        $customer->setEmail('invoice-recipient@example.com');
        $this->getEntityManager()->flush();

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
     * @param array<int, EmailEvent> $events Mutable event capture buffer.
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
