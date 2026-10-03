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
 * Loads service definitions and default permissions for the plugin.
 */
class InvoiceEmailerExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Load the plugin service definitions into Kimai's dependency container.
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
     * Register the plugin permission with Kimai before application configuration.
     *
     * The permission is granted only to ROLE_SUPER_ADMIN by default.  Kimai
     * administrators can subsequently assign it to other roles through Kimai's
     * normal permission-management interface.
     *
     * @param ContainerBuilder $container Dependency container being compiled.
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
