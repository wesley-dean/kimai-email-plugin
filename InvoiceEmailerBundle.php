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
 * The bundle is intentionally inert at this stage.  It establishes the
 * namespace and plugin identity required by Kimai without registering invoice
 * actions, routes, permissions, event subscribers, or mail behavior.
 */
class InvoiceEmailerBundle extends Bundle implements PluginInterface
{
}
