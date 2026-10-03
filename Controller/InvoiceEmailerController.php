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

namespace KimaiPlugin\InvoiceEmailerBundle\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use KimaiPlugin\InvoiceEmailerBundle\Exception\InvoiceEmailException;
use KimaiPlugin\InvoiceEmailerBundle\Service\InvoiceEmailService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Owns the HTTP boundary for the human-confirmed invoice email workflow.
 *
 * Confirmation is a side-effect-free GET.  Sending is a separate POST that
 * repeats authorization, validates a per-invoice CSRF token, and then invokes
 * the application service.  Recipient, sender, subject, and attachment path
 * are never accepted from request data as send-authoritative state.
 *
 * The class relies on Kimai authentication and object authorization, writes
 * user-facing flash messages, and may write non-sensitive diagnostic context
 * to the application log.  It does not read attachment paths from the request
 * or persist post-send invoice state.
 *
 * These boundaries implement ADR-003 and the STRIDE controls documented in the
 * maintained threat model.
 *
 * @see \KimaiPlugin\InvoiceEmailerBundle\Service\InvoiceEmailService
 * @see ../../doc/thread_model.md
 */
#[Route(path: '/invoice/emailer')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class InvoiceEmailerController extends AbstractController
{
    /**
     * Namespace for CSRF token identifiers bound to a specific invoice ID.
     *
     * @var string
     */
    private const CSRF_PREFIX = 'invoice_emailer.send.';

    /**
     * Initialize the controller with application-boundary dependencies.
     *
     * The injected service owns send validation and mail dispatch.  Translation
     * is used only for user-facing messages, while logging records operational
     * identifiers without adding recipient addresses or attachment paths.
     *
     * @param InvoiceEmailService $invoiceEmailService Manual-send application
     *     service that validates current state and dispatches through Kimai.
     * @param TranslatorInterface $translator Translator for user-facing result
     *     and validation messages.
     * @param LoggerInterface $logger Application logger used for non-sensitive
     *     failure diagnostics.
     */
    public function __construct(
        private readonly InvoiceEmailService $invoiceEmailService,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Render a side-effect-free confirmation page for one invoice.
     *
     * The method reuses the same authorization boundary as the send endpoint
     * and asks the service for a presentation-only snapshot.  Validation
     * failures become translated flash messages followed by a redirect.  No
     * email is dispatched and no invoice state is modified.
     *
     * @param Invoice $invoice Invoice resolved by Kimai from the route ID and
     *     treated as the object against which authorization is evaluated.
     * @param Request $request Current request, used only for locale-preserving
     *     redirect behavior after a validation failure.
     * @return Response Confirmation page or localized invoice-list redirect.
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     *     The authenticated user lacks a required invoice, customer, or custom
     *     email permission.
     */
    #[Route(
        path: '/confirm/{id}',
        name: 'invoice_emailer_confirm',
        requirements: ['id' => '\\d+'],
        methods: ['GET']
    )]
    public function confirm(Invoice $invoice, Request $request): Response
    {
        $this->authorizeInvoice($invoice);

        try {
            $preview = $this->invoiceEmailService->preview($invoice);
        } catch (InvoiceEmailException $exception) {
            $this->addFlash(
                'error',
                $this->translator->trans($exception->getTranslationKey())
            );

            return $this->redirectToInvoiceList($request);
        }

        return $this->render('@InvoiceEmailer/confirm.html.twig', [
            'invoice' => $invoice,
            'preview' => $preview,
            'csrf_id' => $this->csrfId($invoice),
        ]);
    }

    /**
     * Submit one confirmed invoice email through Kimai.
     *
     * Authorization and send-authoritative state are evaluated at POST time;
     * the earlier confirmation page is not treated as authority.  A valid
     * per-invoice CSRF token is required before the service is invoked.
     *
     * A successful service call creates the external mail side effect and adds
     * a success flash message.  User-actionable validation failures become
     * translated error flashes.  Unexpected transport or infrastructure
     * failures are logged with invoice/user identifiers and reduced to a
     * generic user-facing error.
     *
     * @param Invoice $invoice Invoice resolved by Kimai from the route ID and
     *     re-authorized immediately before sending.
     * @param Request $request Current HTTP request supplying the CSRF token and
     *     active locale for the final redirect.
     * @return RedirectResponse Localized invoice-list redirect after the send
     *     attempt.
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     *     Authorization fails or the per-invoice CSRF token is invalid.
     */
    #[Route(
        path: '/send/{id}',
        name: 'invoice_emailer_send',
        requirements: ['id' => '\\d+'],
        methods: ['POST']
    )]
    public function send(Invoice $invoice, Request $request): RedirectResponse
    {
        $this->authorizeInvoice($invoice);

        $token = $request->request->get('_token');
        $token = \is_string($token) ? $token : null;

        if (!$this->isCsrfTokenValid($this->csrfId($invoice), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        $kimaiUser = $user instanceof User ? $user : null;

        try {
            $this->invoiceEmailService->send($invoice, $kimaiUser);
            $this->addFlash(
                'success',
                $this->translator->trans('invoice.emailer.submitted')
            );
        } catch (InvoiceEmailException $exception) {
            $this->addFlash(
                'error',
                $this->translator->trans($exception->getTranslationKey())
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Manual invoice email submission failed', [
                'exception' => $exception,
                'invoice_id' => $invoice->getId(),
                'user_id' => $kimaiUser?->getId(),
            ]);
            $this->addFlash(
                'error',
                $this->translator->trans('invoice.emailer.error.generic')
            );
        }

        return $this->redirectToInvoiceList($request);
    }

    /**
     * Enforce all authorization boundaries required by ADR-003.
     *
     * The caller must possess `email_invoice`, normal `view_invoice`
     * authorization for this exact invoice, and applicable access to its
     * customer.  Customer access is an additional restriction and never
     * substitutes for invoice visibility.
     *
     * @param Invoice $invoice Invoice whose current authorization scope is
     *     evaluated.
     * @return void
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException
     *     Any required authorization decision is denied.
     */
    private function authorizeInvoice(Invoice $invoice): void
    {
        $this->denyAccessUnlessGranted('email_invoice');
        $this->denyAccessUnlessGranted('view_invoice', $invoice);

        $customer = $invoice->getCustomer();
        if ($customer !== null) {
            $this->denyAccessUnlessGranted('access', $customer);
        }
    }

    /**
     * Build the CSRF namespace for a specific invoice send operation.
     *
     * The identifier binds the confirmation form token to the resolved invoice
     * ID so that a token created for one invoice is not intentionally reused as
     * the token identifier for another invoice.
     *
     * @param Invoice $invoice Persisted invoice whose ID scopes the token.
     * @return string Stable token identifier for this invoice send operation.
     */
    private function csrfId(Invoice $invoice): string
    {
        return self::CSRF_PREFIX . (string) $invoice->getId();
    }

    /**
     * Redirect back to the invoice listing while preserving request locale.
     *
     * This helper creates an HTTP redirect only; it does not mutate invoice or
     * customer state.
     *
     * @param Request $request Current request supplying the active locale.
     * @return RedirectResponse Localized invoice-list redirect response.
     */
    private function redirectToInvoiceList(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('admin_invoice_list', [
            '_locale' => $request->getLocale(),
        ]);
    }
}
