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
use craft\commerce\models\LineItem;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\UrlHelper;
use superbig\vipps\gateways\Gateway;
use superbig\vipps\models\PaymentModel;
use superbig\vipps\models\PaymentRequestModel;
use superbig\vipps\records\PaymentRecord;
use superbig\vipps\responses\PaymentResponse;
use superbig\vipps\Vipps;

use Craft;
use craft\base\Component;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Payments extends Component
{
    // Public Methods
    // =========================================================================

    /*
     * @return mixed
     */
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

    public function savePayment(PaymentModel $payment)
    {
        $query = (new Query())
            ->createCommand()
            ->upsert(PaymentRecord::tableName(), [
                'orderId' => $payment->orderId,
                'shortId' => $payment->shortId,
            ])
            ->execute();

        return true;
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
            'order' => $order,
            'type'  => PaymentRequestModel::TYPE_REGULAR,
        ]);

        $request  = $this->initiatePayment($paymentRequest);
        $url      = $request['url'] ?? null;
        $response = new PaymentResponse($request);

        if ($url) {
            $response->setRedirectUrl($url);
        }

        return $response;

    }

    public function captureFromGateway(Transaction $transaction): PaymentResponse
    {
        $order          = $transaction->getOrder();
        $paymentRequest = new PaymentRequestModel([
            'order' => $order,
            'type'  => PaymentRequestModel::TYPE_CAPTURE,
        ]);

        $request = $this->initiatePayment($paymentRequest);

        return $request;
    }

    public function paymentStatus($orderId = null)
    {
        $response = Vipps::$plugin->api->get("/ecomm/v2/payments/{$orderId}/details");

        dd($response);
    }

    public function capturePayment($orderId = null)
    {
        $payload  = [];
        $response = Vipps::$plugin->api->post("/ecomm/v2/payments/{$orderId}/capture", $payload);

        dd($response);
    }

    public function refundPayment($orderId = null)
    {
        $payload  = [];
        $response = Vipps::$plugin->api->post("/ecomm/v2/payments/{$orderId}/refund", $payload);

        dd($response);
    }

    public function handleCallback($payload = [])
    {
        // https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api.md#callback
    }

    public function getTransactionText(Order $order): string
    {
        $settings = Vipps::$plugin->getSettings();

        return Craft::$app->getView()->renderObjectTemplate($settings->transactionText, $order, [
            'lineItems' => $this->getLineItemsAsText($order),
        ]);
    }

    public function getLineItemsAsText(Order $order): string
    {
        $lineItems = $order->getLineItems();
        $lines     = array_map(function(LineItem $item) {
            $variant = $item->getPurchasable();

            /** @var Variant $variant */

            return "{$item->qty}x {$variant->title}";
        }, $lineItems);

        return implode("\n", $lines);
    }


    public function getFallbackUrl(Order $order): string
    {
        $gateway    = $this->getGateway();
        $parsedUrl  = Craft::$app->getView()->renderObjectTemplate($gateway->fallbackUrl, $order);
        $defaultUrl = UrlHelper::siteUrl('/');

        return !empty($parsedUrl) ? $parsedUrl : $defaultUrl;
    }

    public function getGateway(): Gateway
    {
        $gateways = Plugin::getInstance()->getGateways()->getAllCustomerEnabledGateways();

        return collect($gateways)
            ->first(function($gateway) {
                return $gateway instanceof Gateway;
            });
    }
}
