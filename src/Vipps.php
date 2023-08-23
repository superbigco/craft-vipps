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

use Craft;
use craft\base\Plugin;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\services\Gateways;
use craft\commerce\services\OrderAdjustments;
use craft\commerce\services\OrderHistories;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterComponentTypesEvent;

use craft\events\RegisterUrlRulesEvent;

use craft\services\Plugins;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use superbig\vipps\behaviors\TransactionBehavior;
use superbig\vipps\gateways\Gateway;

use superbig\vipps\variables\VippsVariable;
use yii\base\Event;

/**
 * Class Vipps
 *
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 *
 */
class Vipps extends Plugin
{
    use Services;

    public bool $hasCpSettings = false;
    public static Vipps $plugin;

    public static bool $commerceInstalled = false;
    public string $schemaVersion = '1.0.0';


    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->initComponents();

        self::$commerceInstalled = class_exists(CommercePlugin::class);

        // Install event listeners
        $this->installEventListeners();

        Craft::info(
            Craft::t(
                'vipps',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    protected function installEventListeners(): void
    {
        // Install our event listeners only if our table schema exists
        if ($this->migrationsAndSchemaReady()) {
            $this->installGlobalEventListeners();
        }
    }

    public function installGlobalEventListeners(): void
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

        Event::on(Transaction::class, Transaction::EVENT_DEFINE_BEHAVIORS, function(DefineBehaviorsEvent $event) {
            $event->behaviors[] = TransactionBehavior::class;
        });

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

    protected function installSiteEventListeners(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    'vipps/callbacks/v2/consents/<userId>' => 'vipps/callback/consent-removal',
                    'vipps/callbacks/v2/payments/<orderId>' => 'vipps/callback/complete',
                    'vipps/callbacks/v2/return/<orderId>' => 'vipps/callback/return',
                    'vipps/callbacks/v2/payments/<orderId>/shippingDetails' => 'vipps/callback/shipping-details',
                    'vipps/express/checkout' => 'vipps/express/checkout',
                ]);
            }
        );
    }

    protected function installCpEventListeners()
    {
    }
}
