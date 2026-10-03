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
 *
 * Container mutation occurs only during Symfony compilation.  Runtime
 * authorization remains additive: the custom permission does not replace
 * specific-invoice visibility or customer access checks required by ADR-003.
 *
 * @see \KimaiPlugin\InvoiceEmailerBundle\Controller\InvoiceEmailerController
 */
class InvoiceEmailerExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Load the plugin service definitions into Kimai's dependency container.
     *
     * Loading `services.yaml` mutates the supplied container by registering
     * the controller, application service, and action subscriber through
     * Symfony autowiring and autoconfiguration.  The configuration fragments
     * are accepted to satisfy Symfony's extension contract and are not treated
     * as an additional plugin-owned configuration surface.
     *
     * @param array<mixed> $configs Configuration fragments supplied by Symfony;
     *     the current plugin does not interpret values from this array.
     * @param ContainerBuilder $container Mutable dependency container being
     *     compiled for Kimai.
     * @return void
     * @throws \Exception The maintained service-definition file cannot be
     *     located, parsed, or loaded by Symfony.
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
     * The method mutates Kimai's pending configuration by adding
     * `email_invoice` to `ROLE_SUPER_ADMIN` defaults.  It does not grant
     * invoice or customer access and therefore cannot weaken the object-level
     * authorization required by the runtime controller.
     *
     * @param ContainerBuilder $container Mutable dependency container whose
     *     `kimai.permissions` configuration is being prepended.
     * @return void
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
