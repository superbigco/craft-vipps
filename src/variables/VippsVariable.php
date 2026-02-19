<?php

declare(strict_types=1);

namespace superbig\vipps\variables;

/**
 * Twig variable for the Vipps plugin.
 *
 * Usage: {{ craft.vipps.pluginName }}
 */
class VippsVariable
{
    public function getPluginName(): string
    {
        return 'Vipps MobilePay';
    }
}
