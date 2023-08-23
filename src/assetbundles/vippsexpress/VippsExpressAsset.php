<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\assetbundles\vippsexpress;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class VippsExpressAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================


    public function init(): void
    {
        $this->sourcePath = "@superbig/vipps/assetbundles/vippsexpress/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Vipps.js',
        ];

        $this->css = [
            'css/Vipps.css',
        ];

        parent::init();
    }
}
