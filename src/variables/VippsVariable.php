<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\variables;

use craft\commerce\elements\Order;
use superbig\bring\Bring;

use Craft;
use superbig\vipps\Vipps;

/**
 * Vipps Utility
 *
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class VippsVariable
{
    // Public Methods
    // =========================================================================

    public function getExpressButton()
    {
        return Vipps::$plugin->express->getButton();
    }
}
