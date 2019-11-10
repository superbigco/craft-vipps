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

use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\services\Gateways;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\OrderHistories;
use craft\web\twig\variables\CraftVariable;
use superbig\vipps\gateways\Gateway;
use superbig\vipps\helpers\StringHelper;
use superbig\vipps\services\Api;
use superbig\vipps\services\Payments as PaymentsService;
use superbig\vipps\services\Express as ExpressService;
use superbig\vipps\services\Api as ApiService;
use superbig\vipps\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\services\Utilities;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;

use superbig\vipps\variables\VippsVariable;
use yii\base\Event;

/**
 * Class Vipps
 *
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 *
 *
 * @method Settings getSettings()
 */
class Vipps extends Plugin
{
    use Services;

    // Static Properties
    // =========================================================================

    /**
     * @var Vipps
     */
    public static $plugin;

    /**
     * @var bool
     */
    public static $commerceInstalled = false;

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
            'api'      => Api::class,
        ]);

        self::$commerceInstalled = class_exists(CommercePlugin::class);

        // Install event listeners
        $this->installEventListeners();

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

    protected function installEventListeners()
    {

        $this->installGlobalEventListeners();

    }

    public function installGlobalEventListeners()
    {
        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Gateway::class;
            }
        );

        Event::on(
            OrderHistories::class,
            OrderHistories::EVENT_ORDER_STATUS_CHANGE,
            [self::$plugin->getPayments(), 'onStatusChange']
        );

        Event::on(
            OrderAdjustments::class,
            OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS,
            [self::$plugin->getExpress(), 'onRegisterOrderAdjusters']
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('vipps', VippsVariable::class);
            }
        );

        // Handler: Plugins::EVENT_AFTER_LOAD_PLUGINS
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_LOAD_PLUGINS,
            function() {
                // Install these only after all other plugins have loaded
                $request = Craft::$app->getRequest();

                if ($request->getIsSiteRequest() && !$request->getIsConsoleRequest()) {
                    $this->installSiteEventListeners();
                }

                if ($request->getIsCpRequest() && !$request->getIsConsoleRequest()) {
                    $this->installCpEventListeners();
                }
            }
        );
    }

    protected function installSiteEventListeners()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    'vipps/callbacks/v2/consents/<userId>'                  => 'vipps/callback/consent-removal',
                    'vipps/callbacks/v2/payments/<orderId>'                 => 'vipps/callback/complete',
                    'vipps/callbacks/v2/payments/<orderId>/shippingDetails' => 'vipps/callback/shipping-details',
                    'vipps/express/checkout'                                => 'vipps/express/checkout',
                ]);
            }
        );
    }

    protected function installCpEventListeners()
    {
    }
}
