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
 * the application service.  The controller never accepts recipient, sender,
 * subject, or attachment path as send-authoritative form input.
 *
 * These boundaries implement ADR-003.
 *
 * @see \KimaiPlugin\InvoiceEmailerBundle\Service\InvoiceEmailService
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
     * @param InvoiceEmailService $invoiceEmailService Manual-send application service.
     * @param TranslatorInterface $translator Translator for user-facing results.
     * @param LoggerInterface $logger Application logger.
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
     * This method does not dispatch email or modify invoice state.
     *
     * @param Invoice $invoice Invoice resolved by Kimai from the route ID.
     * @param Request $request Current HTTP request.
     * @return Response Confirmation page or redirect on validation failure.
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
     * the earlier confirmation page is not treated as authority.
     *
     * @param Invoice $invoice Invoice resolved by Kimai from the route ID.
     * @param Request $request Current HTTP request containing the CSRF token.
     * @return RedirectResponse Redirect to the invoice listing.
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
     * customer.
     *
     * @param Invoice $invoice Invoice being accessed.
     * @return void
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
     * Build a per-invoice CSRF token identifier.
     *
     * @param Invoice $invoice Invoice being sent.
     * @return string CSRF token identifier.
     */
    private function csrfId(Invoice $invoice): string
    {
        return self::CSRF_PREFIX . (string) $invoice->getId();
    }

    /**
     * Redirect back to the localized invoice listing.
     *
     * @param Request $request Current request supplying the active locale.
     * @return RedirectResponse Localized invoice-list redirect.
     */
    private function redirectToInvoiceList(Request $request): RedirectResponse
    {
        return $this->redirectToRoute('admin_invoice_list', [
            '_locale' => $request->getLocale(),
        ]);
    }
}
