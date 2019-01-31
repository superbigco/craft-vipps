<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps;

use superbig\vipps\services\Payments as PaymentsService;
use superbig\vipps\services\Express as ExpressService;
use superbig\vipps\models\Settings;
use superbig\vipps\utilities\VippsUtility as VippsUtilityUtility;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\services\Utilities;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;

use yii\base\Event;

/**
 * Class Vipps
 *
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 *
 * @property  PaymentsService $payments
 * @property  ExpressService  $express
 */
class Vipps extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var Vipps
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->setComponents([
            'payments' => PaymentsService::class,
            'express'  => ExpressService::class,
        ]);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    // @todo handle + callbackPrefix https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api.md#express-checkout-payments
                    // callbackPrefix: https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api.md#initiate-payment
                    'vipps/callbacks/v2/consents/{userId}'                  => '',
                    'vipps/callbacks/v2/payments/{orderId}'                 => '',
                    'vipps/callbacks/v2/payments/{orderId}/shippingDetails' => '',
                ]);
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['cpActionTrigger1'] = 'vipps/default/do-something';
            }
        );

        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = VippsUtilityUtility::class;
            }
        );

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Craft::info(
            Craft::t(
                'vipps',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'vipps/settings',
            [
                'settings' => $this->getSettings(),
            ]
        );
    }
}
