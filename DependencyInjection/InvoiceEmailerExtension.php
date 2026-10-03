<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin.
 *
 * Copyright (c) 2026 Wes Dean.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\InvoiceEmailerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Integrates plugin services and default authorization policy into Kimai.
 *
 * The extension loads the plugin service definitions and prepends the
 * `email_invoice` permission to Kimai's permission configuration.  The
 * default grant is intentionally limited to `ROLE_SUPER_ADMIN`; deployments
 * may assign the permission to additional roles through Kimai's normal
 * permission management.
 */
class InvoiceEmailerExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Load the plugin service definitions into Kimai's dependency container.
     *
     * Loading this file registers the controller, application service, and
     * invoice-action subscriber through Symfony autowiring and
     * autoconfiguration.
     *
     * @param array<mixed> $configs Configuration fragments supplied by Symfony.
     * @param ContainerBuilder $container Dependency container being compiled.
     * @return void
     * @throws \Exception The service-definition file cannot be loaded.
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.yaml');
    }

    /**
     * Register the plugin permission before Kimai processes application config.
     *
     * The permission is granted only to `ROLE_SUPER_ADMIN` by default.  This
     * custom permission does not replace Kimai's per-invoice or customer
     * authorization checks; those remain enforced by the controller and action
     * subscriber under ADR-003.
     *
     * @param ContainerBuilder $container Dependency container being compiled.
     * @return void
     *
     * @see \KimaiPlugin\InvoiceEmailerBundle\Controller\InvoiceEmailerController
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'roles' => [
                    'ROLE_SUPER_ADMIN' => [
                        'email_invoice',
                    ],
                ],
            ],
        ]);
    }
}
