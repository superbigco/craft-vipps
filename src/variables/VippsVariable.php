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

use craft\helpers\Template;

use superbig\vipps\Vipps;
use Twig\Markup;

/**
 * Vipps Utility
 *
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class VippsVariable
{
    /**
     * @param null  $purchasable
     *
     */
    public function getExpressButton($purchasable = null, array $config = []): Markup
    {
        $html = Vipps::$plugin->getExpress()->getButton($purchasable, $config);

        return Template::raw($html);
    }

    public function getExpressUrl($purchasable = null, array $config = []): string
    {
        return Vipps::$plugin->getExpress()->getCheckoutUrl($purchasable, $config);
    }

    /**
     * @param null  $purchasable
     *
     */
    public function getExpressFormButton($purchasable = null, array $config = []): Markup
    {
        $html = Vipps::$plugin->getExpress()->getFormButton($purchasable, $config);

        return Template::raw($html);
    }
}
