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

use Craft;
use craft\commerce\errors\PaymentException;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\web\Controller;

use superbig\vipps\helpers\LogToFile;
use superbig\vipps\Vipps;

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
        $request = Craft::$app->getRequest();
        $commerce = Plugin::getInstance();
        $cartService = $commerce->getCarts();
        $order = $cartService->getCart(true);
        $lineItems = $order->getLineItems();
        $vippsPayments = Vipps::$plugin->getPayments();
        $gateway = $vippsPayments->getGateway();
        $paymentForm = $gateway->getPaymentFormModel();
        $session = Craft::$app->getSession();
        $customError = '';

        $vippsPayments->setIsExpress();

        if ($purchasables = $request->getParam('purchasables')) {
            if ($gateway->newCartOnExpressCheckout && !empty($lineItems)) {
                // Clean out cart
                $cartService->forgetCart();

                $order = $cartService->getCart(true);
            }

            $lineItemService = $commerce->getLineItems();

            foreach ($purchasables as $key => $purchasable) {
                $purchasableId = $request->getRequiredParam("purchasables.{$key}.id");
                $note = $request->getParam("purchasables.{$key}.note", '');
                $options = $request->getParam("purchasables.{$key}.options") ?: [];
                $qty = (int)$request->getParam("purchasables.{$key}.qty", 1);

                // Ignore zero value qty for multi-add forms https://github.com/craftcms/commerce/issues/330#issuecomment-384533139
                if ($qty > 0) {
                    $lineItem = $lineItemService->resolveLineItem($order->id, $purchasableId, $options);

                    // New line items already have a qty of one.
                    if ($lineItem->id) {
                        $lineItem->qty += $qty;
                    } else {
                        $lineItem->qty = $qty;
                    }

                    $lineItem->note = $note;
                    $order->addLineItem($lineItem);
                }
            }
        }
        $order->setGatewayId($gateway->id);
        $order->shippingMethodHandle = null;
        $order->recalculate();

        $originalTotalPrice = $order->getOutstandingBalance();
        $originalTotalQty = $order->getTotalQty();
        $originalTotalAdjustments = \count($order->getAdjustments());

        // Do one final save to confirm the price does not change out from under the customer. Also removes any out of stock items etc.
        // This also confirms the products are available and discounts are current.
        $order->recalculate();

        // Save the orders new values.
        if (Craft::$app->getElements()->saveElement($order)) {
            $totalPriceChanged = $originalTotalPrice !== $order->getOutstandingBalance();
            $totalQtyChanged = $originalTotalQty !== $order->getTotalQty();
            $totalAdjustmentsChanged = $originalTotalAdjustments !== \count($order->getAdjustments());

            // Has the order changed in a significant way?
            if ($totalPriceChanged || $totalQtyChanged || $totalAdjustmentsChanged) {
                if ($totalPriceChanged) {
                    $order->addError('totalPrice', Craft::t('commerce', 'The total price of the order changed.'));
                }

                if ($totalQtyChanged) {
                    $order->addError('totalQty', Craft::t('commerce', 'The total quantity of items within the order changed.'));
                }

                if ($totalAdjustmentsChanged) {
                    $order->addError('totalAdjustments', Craft::t('commerce', 'The total number of order adjustments changed.'));
                }

                $customError = Craft::t('commerce', 'Something changed with the order before payment, please review your order and submit payment again.');

                if ($request->getAcceptsJson()) {
                    return $this->asErrorJson($customError);
                }

                $session->setError($customError);
                LogToFile::error($customError . " @ " . __METHOD__);

                Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));

                return null;
            }
        }

        $redirect = '';
        $transaction = null;

        if (!$paymentForm->hasErrors() && !$order->hasErrors()) {
            try {
                $commerce->getPayments()->processPayment($order, $paymentForm, $redirect, $transaction);
                $success = true;
            } catch (PaymentException $exception) {
                $customError = $exception->getMessage();
                $success = false;
                $method = __METHOD__;
                $error = "{$customError} @ {$method}";

                LogToFile::error($error);
            }
        } else {
            $customError = Craft::t('commerce', 'Invalid payment or order. Please review.');
            $success = false;

            LogToFile::error('Invalid payment or order. Please review.');
        }

        if (!$success) {
            if ($request->getAcceptsJson()) {
                return $this->asJson(['error' => $customError, 'paymentForm' => $paymentForm->getErrors()]);
            }

            $session->setError($customError);
            Craft::$app->getUrlManager()->setRouteParams(compact('paymentForm'));

            return $this->goBack();
        }

        if ($request->getAcceptsJson()) {
            $response = ['success' => true];

            if ($redirect) {
                $response['redirect'] = $redirect;
            }

            if ($transaction) {
                /** @var Transaction $transaction */
                $response['transactionId'] = $transaction->reference;
            }

            return $this->asJson($response);
        }

        return $this->redirect($redirect);
    }
}
