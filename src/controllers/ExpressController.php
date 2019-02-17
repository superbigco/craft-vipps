<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\controllers;

use craft\commerce\Plugin;
use superbig\vipps\models\PaymentRequestModel;
use superbig\vipps\Vipps;

use Craft;
use craft\web\Controller;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class ExpressController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['checkout'];

    // Public Methods
    // =========================================================================

    /**
     * Initiate Express payment
     *
     * @return mixed
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionCheckout()
    {
        $request     = Craft::$app->getRequest();
        $settings    = Vipps::$plugin->getSettings();
        $cartService = Plugin::getInstance()->getCarts();
        $cart        = $cartService->getCart(true);
        $lineItems   = $cart->getLineItems();

        if ($purchasables = $request->getParam('purchasables')) {
            if ($settings->newCartOnExpressCheckout && !empty($lineItems)) {
                // Clean out cart
                $cartService->forgetCart();

                $cart = $cartService->getCart(true);
            }

            foreach ($purchasables as $key => $purchasable) {
                $purchasableId = $request->getRequiredParam("purchasables.{$key}.id");
                $note          = $request->getParam("purchasables.{$key}.note", '');
                $options       = $request->getParam("purchasables.{$key}.options") ?: [];
                $qty           = (int)$request->getParam("purchasables.{$key}.qty", 1);

                // Ignore zero value qty for multi-add forms https://github.com/craftcms/commerce/issues/330#issuecomment-384533139
                if ($qty > 0) {
                    $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($cart->id, $purchasableId, $options);

                    // New line items already have a qty of one.
                    if ($lineItem->id) {
                        $lineItem->qty += $qty;
                    }
                    else {
                        $lineItem->qty = $qty;
                    }

                    $lineItem->note = $note;
                    $cart->addLineItem($lineItem);

                    Craft::$app->getElements()->saveElement($cart);
                }
            }
        }

        $paymentRequest = new PaymentRequestModel([
            'order' => $cart,
        ]);
        $response       = Vipps::$plugin->payments->initiatePayment($paymentRequest);

        if ($paymentRequest->hasErrors()) {

        }

        return $this->redirect($response['url']);
    }
}
