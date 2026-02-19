<?php

declare(strict_types=1);

namespace superbig\vipps;

use Craft;
use craft\base\Plugin;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use craft\web\twig\variables\CraftVariable;
use superbig\vipps\gateways\Gateway;
use superbig\vipps\models\Settings;
use superbig\vipps\variables\VippsVariable;
use yii\base\Event;

/**
 * Vipps MobilePay payment gateway for Craft Commerce.
 *
 * @method Settings getSettings()
 * @method static Vipps getInstance()
 */
class Vipps extends Plugin
{
    public function init(): void
    {
        parent::init();

        $this->_registerComponents();
        $this->_registerEventListeners();

        Craft::info('Vipps plugin loaded', __METHOD__);
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    private function _registerComponents(): void
    {
        $this->setComponents([
            'api' => services\VippsApi::class,
            'tokenManager' => services\TokenManager::class,
        ]);
    }

    private function _registerEventListeners(): void
    {
        Event::on(
            Gateways::class,
            Gateways::EVENT_REGISTER_GATEWAY_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = Gateway::class;
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function(Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('vipps', VippsVariable::class);
            }
        );
    }
}
