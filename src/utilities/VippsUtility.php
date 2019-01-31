<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\utilities;

use superbig\vipps\Vipps;
use superbig\vipps\assetbundles\vippsutilityutility\VippsUtilityUtilityAsset;

use Craft;
use craft\base\Utility;

/**
 * Vipps Utility
 *
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class VippsUtility extends Utility
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('vipps', 'VippsUtility');
    }

    /**
     * @inheritdoc
     */
    public static function id(): string
    {
        return 'vipps-vipps-utility';
    }

    /**
     * @inheritdoc
     */
    public static function iconPath()
    {
        return Craft::getAlias("@superbig/vipps/assetbundles/vippsutilityutility/dist/img/VippsUtility-icon.svg");
    }

    /**
     * @inheritdoc
     */
    public static function badgeCount(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public static function contentHtml(): string
    {
        Craft::$app->getView()->registerAssetBundle(VippsUtilityUtilityAsset::class);

        $someVar = 'Have a nice day!';
        return Craft::$app->getView()->renderTemplate(
            'vipps/_components/utilities/VippsUtility_content',
            [
                'someVar' => $someVar
            ]
        );
    }
}
