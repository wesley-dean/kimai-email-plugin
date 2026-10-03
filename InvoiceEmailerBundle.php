<?php

/*
 * This file is part of the Kimai Invoice Emailer plugin.
 *
 * Copyright (c) 2026 Wes Dean.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\InvoiceEmailerBundle;

use App\Plugin\PluginInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Provides the Kimai bundle entry point for the Invoice Emailer plugin.
 *
 * Kimai discovers this bundle from `var/plugins/InvoiceEmailerBundle/` and
 * loads its dependency-injection extension, routes, services, translations,
 * and templates through Kimai's plugin mechanism.  The bundle owns no mutable
 * runtime state and introduces no lifecycle or cleanup requirements beyond
 * Kimai's normal bundle lifecycle.
 *
 * The supported installation directory is part of the ADR-004 distribution
 * contract; generic GitHub source archives are not the supported plugin
 * package.
 *
 * @see \KimaiPlugin\InvoiceEmailerBundle\DependencyInjection\InvoiceEmailerExtension
 */
class InvoiceEmailerBundle extends Bundle implements PluginInterface
{
}
