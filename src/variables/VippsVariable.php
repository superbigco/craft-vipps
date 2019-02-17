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
use craft\helpers\Template;
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

    public function getExpressButton($purchasable, $config = [])
    {
        $html = Vipps::$plugin->express->getButton($purchasable, $config = []);

        return Template::raw($html);
    }

    public function getExpressFormButton()
    {
        return Vipps::$plugin->express->getFormButton();
    }
}
