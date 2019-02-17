<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\assetbundles\vippsutilityutility;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class VippsUtilityUtilityAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@superbig/vipps/assetbundles/vippsutilityutility/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/VippsUtility.js',
        ];

        $this->css = [
            'css/VippsUtility.css',
        ];

        parent::init();
    }
}
