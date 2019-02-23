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

    public function getOrderDetails(Order $order)
    {
        return Vipps::$plugin->getPayments()->getOrderDetails($order);
    }

    /**
     * @param null  $purchasable
     * @param array $config
     *
     * @return \Twig_Markup
     */
    public function getExpressButton($purchasable = null, array $config = []): \Twig_Markup
    {
        $html = Vipps::$plugin->express->getButton($purchasable, $config = []);

        return Template::raw($html);
    }

    public function getExpressUrl($purchasable = null, array $config = []): string
    {
        return Vipps::$plugin->express->getCheckoutUrl($purchasable, $config);
    }

    /**
     * @param null  $purchasable
     * @param array $config
     *
     * @return \Twig_Markup
     */
    public function getExpressFormButton($purchasable = null, array $config = []): \Twig_Markup
    {
        $html = Vipps::$plugin->express->getFormButton($purchasable, $config);

        return Template::raw($html);
    }
}
