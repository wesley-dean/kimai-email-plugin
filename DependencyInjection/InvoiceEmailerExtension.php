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
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads service definitions for the Invoice Emailer plugin.
 *
 * This extension currently loads only the plugin's service-discovery
 * configuration.  Permissions, routes, invoice actions, and mail behavior are
 * introduced by later implementation phases governed by ADR-003.
 */
class InvoiceEmailerExtension extends Extension
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
}
