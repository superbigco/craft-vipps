<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\services;

use craft\commerce\base\Purchasable;
use craft\commerce\Plugin;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use superbig\vipps\models\PaymentRequestModel;
use superbig\vipps\Vipps;

use Craft;
use craft\base\Component;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Express extends Component
{
    // Public Methods
    // =========================================================================

    public function initiatePayment($purchasables)
    {
        $settings            = Vipps::$plugin->getSettings();
        $cart                = Plugin::getInstance()->getCarts()->getCart(true);
        $orderId             = $cart->getShortNumber() ?? $cart->id;
        $orderTotalMinorUnit = $cart->getTotalPrice() * 100;
        $paymentRequest      = new PaymentRequestModel([
            'orderId' => $orderId,
            'amount'  => $orderTotalMinorUnit,
        ]);
    }

    public function getButton($purchasable = null, $config = []): string
    {
        $view    = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        if (\is_numeric($purchasable)) {
            $purchasable = Plugin::getInstance()->getVariants()->getVariantById($purchasable);
        }
        elseif ($purchasable instanceof Purchasable) {
            $purchasable = $purchasable;
        }

        $data  = array_filter([
            'id'      => $purchasable->id,
            'qty'     => $config['quantity'] ?? 1,
            'note'    => $config['note'] ?? null,
            'options' => $config['options'] ?? [],
        ]);
        $class = $config['class'] ?? null;
        $url   = UrlHelper::siteUrl('vipps/express/checkout', [
            'purchasables' => [$data],
        ]);
        $html  = $view->renderTemplate('vipps/_components/express/button', [
            'url'         => $url,
            'class'       => $class,
            'title'       => '',
            'purchasable' => $purchasable,
        ]);

        $view->setTemplateMode($oldMode);

        return $html;
    }

    public function getFormButton(): string
    {
        $view    = Craft::$app->getView();
        $oldMode = $view->getTemplateMode();
        $view->setTemplateMode($view::TEMPLATE_MODE_CP);

        $html = $view->renderTemplate('vipps/_components/express/form-button', [
            'nonceUrl' => UrlHelper::siteUrl(''),
            'title'    => '',
        ]);

        $view->setTemplateMode($oldMode);

        return Template::raw($html);
    }
}
