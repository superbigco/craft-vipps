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

use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\models\LineItem;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use superbig\vipps\gateways\Gateway;
use superbig\vipps\helpers\Currency;
use superbig\vipps\helpers\LogToFile;
use superbig\vipps\models\PaymentModel;
use superbig\vipps\models\PaymentRequestModel;
use superbig\vipps\records\PaymentRecord;
use superbig\vipps\responses\CaptureResponse;
use superbig\vipps\responses\PaymentResponse;
use superbig\vipps\Vipps;

use Craft;
use craft\base\Component;
use yii\base\Exception;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Payments extends Component
{
    private $_express;

    /** @var Gateway */
    private $_gateway;

    public function init()
    {
    }

    /**
     * @param string|null $shortId
     *
     * @return Order|null
     */
    public function getOrderByShortId(string $shortId = null)
    {
        $orderId = (new Query())
            ->from(PaymentRecord::tableName())
            ->select('orderId')
            ->where('shortId = :shortId', [':shortId' => $shortId])
            ->scalar();

        if (!$orderId) {
            return null;
        }

        return Plugin::getInstance()->getOrders()->getOrderById($orderId);
    }

    public function getTransactionByShortId(string $shortId = null)
    {
        $reference = (new Query())
            ->from(PaymentRecord::tableName())
            ->select('transactionReference')
            ->where('shortId = :shortId', [':shortId' => $shortId])
            ->scalar();

        if (!$reference) {
            return null;
        }

        return Plugin::getInstance()->getTransactions()->getTransactionByReferenceAndStatus($reference, TransactionRecord::STATUS_REDIRECT);
    }

    public function savePayment(PaymentModel $payment): bool
    {
        $query = (new Query())
            ->createCommand()
            ->upsert(PaymentRecord::tableName(), [
                'orderId'              => $payment->orderId,
                'shortId'              => $payment->shortId,
                'transactionReference' => $payment->transactionReference,
            ])
            ->execute();

        return $query;
    }

    public function initiatePayment(PaymentRequestModel $paymentRequest)
    {
        // ref https://vippsas.github.io/vipps-ecom-api/#/Vipps_eCom_API/initiatePaymentV3UsingPOST
        $payload = $paymentRequest->getPayload();

        $response = Vipps::$plugin->api->post('/ecomm/v2/payments/', $payload);

        return $response;
    }

    public function intiatePaymentFromGateway(Transaction $transaction): PaymentResponse
    {
        $order          = $transaction->getOrder();
        $paymentRequest = new PaymentRequestModel([
            'order'       => $order,
            'transaction' => $transaction,
            'type'        => PaymentRequestModel::TYPE_REGULAR,
        ]);

        $this->savePayment($paymentRequest->getPaymentRecord());

        if ($this->getIsExpress()) {
            $paymentRequest->setType(PaymentRequestModel::TYPE_EXPRESS);
        }

        $request  = $this->initiatePayment($paymentRequest);
        $url      = $request['url'] ?? null;
        $response = new PaymentResponse($request);

        if ($url) {
            $response->setRedirectUrl($url);
        }

        return $response;

    }

    /**
     * @param Transaction $transaction
     *
     * @return CaptureResponse
     * @throws Exception
     */
    public function captureFromGateway(Transaction $transaction): CaptureResponse
    {
        $order                = $transaction->getOrder();
        $authorizedTransation = $this->getSuccessfulTransactionForOrder($order);
        $parentTransaction    = $authorizedTransation->getParent();
        $gateway              = $this->getGateway();
        //$amount            = (int)$transaction->amount * 100;
        $amount   = 0;
        $response = Vipps::$plugin->api->post("/ecomm/v2/payments/{$parentTransaction->reference}/capture", [
            'merchantInfo' => [
                'merchantSerialNumber' => Craft::parseEnv($gateway->merchantSerialNumber),
            ],
            'transaction'  => [
                'amount'          => $amount,
                // TODO: Set from status message?
                'transactionText' => $order->getEmail(),
            ],
        ]);

        return new CaptureResponse($response);
    }

    /**
     * @param Transaction $transaction
     *
     * @return CaptureResponse
     * @throws Exception
     */
    public function refundFromGateway(Transaction $transaction): CaptureResponse
    {
        $order                = $transaction->getOrder();
        $authorizedTransation = $this->getSuccessfulTransactionForOrder($order);
        $parentTransaction    = $authorizedTransation->getParent();
        $gateway              = $this->getGateway();
        $amount               = Currency::roundAndConvertToMinorUnit($transaction->amount);
        $transactionText      = !empty($transaction->note) ? $transaction->note : $order->getEmail();
        //dd($parentTransaction->reference, $amount, $transactionText);
        $response = Vipps::$plugin->api->post("/ecomm/v2/payments/{$parentTransaction->reference}/refund", [
            'merchantInfo' => [
                'merchantSerialNumber' => Craft::parseEnv($gateway->merchantSerialNumber),
            ],
            'transaction'  => [
                'amount'          => $amount,
                // TODO: Set from status message?
                'transactionText' => $transactionText,
            ],
        ]);


        return new CaptureResponse($response);
    }

    public function onStatusChange(OrderStatusEvent $e)
    {
        try {
            $order   = $e->order;
            $gateway = $this->getGateway();
            $enabled = $gateway && $gateway->captureOnStatusChange && $this->isVippsGateway($order);

            if ($enabled && $gateway->captureStatusUid === $e->orderHistory->getNewStatus()->uid) {
                $transaction = $this->getSuccessfulTransactionForOrder($order);

                if ($transaction && $transaction->canCapture()) {
                    // capture transaction and display result
                    $child = Plugin::getInstance()->getPayments()->captureTransaction($transaction);

                    $message = $child->message ? ' (' . $child->message . ')' : '';

                    if ($child->status === TransactionRecord::STATUS_SUCCESS) {
                        $child->order->updateOrderPaidInformation();
                        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Transaction captured successfully: {message}', [
                            'message' => $message,
                        ]));
                    }
                    else {
                        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t capture transaction: {message}', [
                            'message' => $message,
                        ]));
                    }
                }
                else {
                    Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t capture transaction.', ['id' => $transaction->id]));
                }
            }
        } catch (\Exception $e) {
            LogToFile::error('Not able to change status: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            Craft::$app->getErrorHandler()->logException($e);
        }
    }

    public function createResponseFromCallback($payload = [])
    {
        // https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api.md#callback
    }

    public function getTransactionText(Order $order): string
    {
        $text = Craft::parseEnv($this->getGateway()->transactionText);

        return Craft::$app->getView()->renderObjectTemplate($text, $order, [
            'lineItemsText' => $this->getLineItemsAsText($order),
        ]);
    }

    public function getLineItemsAsText(Order $order): string
    {
        // @todo Crop?
        $lineItems = $order->getLineItems();
        $lines     = array_map(function(LineItem $item) {
            $variant = $item->getPurchasable();

            /** @var Variant $variant */

            return "{$item->qty}x {$variant->title}";
        }, $lineItems);

        return implode("\n", $lines);
    }

    public function getFallbackActionUrl(string $transactionId)
    {
        return UrlHelper::siteUrl('vipps/callbacks/v2/return/' . $transactionId);
    }

    public function getFallbackUrl(Order $order): string
    {
        $gateway    = $this->getGateway();
        $url        = Craft::parseEnv($gateway->fallbackUrl);
        $parsedUrl  = Craft::$app->getView()->renderObjectTemplate($url, $order);
        $defaultUrl = UrlHelper::siteUrl('/');

        return !empty($parsedUrl) ? $parsedUrl : $defaultUrl;
    }

    public function getFallbackErrorUrl(Order $order): string
    {
        $gateway    = $this->getGateway();
        $url        = Craft::parseEnv($gateway->errorFallbackUrl);
        $parsedUrl  = Craft::$app->getView()->renderObjectTemplate($url, $order);
        $defaultUrl = $this->getFallbackUrl($order);

        return !empty($parsedUrl) ? $parsedUrl : $defaultUrl;
    }

    public function getGateway(): Gateway
    {
        if (!$this->_gateway) {
            $gateways = Plugin::getInstance()->getGateways()->getAllCustomerEnabledGateways();
            $this->_gateway = ArrayHelper::firstWhere($gateways, function($gateway) {
                return $gateway instanceof Gateway;
            });

            if (!$this->_gateway) {
                throw new Exception('The Vipps gateway is not setup correctly.');
            }
        }

        return $this->_gateway;
    }

    /**
     * @param Order $order
     *
     * @return Transaction|null
     */
    public function getSuccessfulTransactionForOrder(Order $order)
    {
        return ArrayHelper::firstWhere($order->getTransactions(), function(Transaction $transaction) {
            return $transaction->status === TransactionRecord::STATUS_SUCCESS
                && $transaction->type === TransactionRecord::TYPE_AUTHORIZE
                && $transaction->parentId !== null;
        });
    }

    public function getIsExpress(): bool
    {
        return (bool)$this->_express;
    }

    public function setIsExpress(bool $value = true)
    {
        $this->_express = $value;

        return $this;
    }

    public function getOrderDetails(Order $order)
    {

    }

    public function isVippsGateway(Order $order): bool
    {
        $orderGateway = $order->getGateway();

        return $orderGateway !== null && $orderGateway instanceof Gateway;
    }
}
