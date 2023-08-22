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

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\models\LineItem;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
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
use yii\base\Exception;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Payments extends Component
{
    private ?bool $_express = null;

    /** @var mixed|null */
    private mixed $_gateway = null;

    public function init(): void
    {
    }

    /**
     * @param string|null $shortId
     */
    public function getOrderByShortId(string $shortId = null): ?Order
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

    public function getTransactionByShortId(string $shortId = null): ?Transaction
    {
        $reference = (new Query())
            ->from(PaymentRecord::tableName())
            ->select('shortId')
            ->where('shortId = :shortId', [':shortId' => $shortId])
            ->scalar();

        if (!$reference) {
            return null;
        }

        return Plugin::getInstance()->getTransactions()->getTransactionByReferenceAndStatus($reference, TransactionRecord::STATUS_REDIRECT);
    }

    public function savePayment(PaymentModel $payment): int
    {
        return (new Query())
            ->createCommand()
            ->upsert(PaymentRecord::tableName(), [
                'orderId' => $payment->orderId,
                'shortId' => $payment->shortId,
                'transactionReference' => $payment->transactionReference,
            ])
            ->execute();
    }

    public function initiatePayment(PaymentRequestModel $paymentRequest): ?array
    {
        // ref https://vippsas.github.io/vipps-ecom-api/#/Vipps_eCom_API/initiatePaymentV3UsingPOST
        $payload = $paymentRequest->getPayload();

        return Vipps::$plugin->getApi()->post('/ecomm/v2/payments/', $payload);
    }

    public function intiatePaymentFromGateway(Transaction $transaction): PaymentResponse
    {
        $order = $transaction->getOrder();
        $paymentRequest = new PaymentRequestModel([
            'order' => $order,
            'transaction' => $transaction,
            'type' => PaymentRequestModel::TYPE_REGULAR,
        ]);

        $this->savePayment($paymentRequest->getPaymentRecord());

        if ($this->getIsExpress()) {
            $paymentRequest->setType(PaymentRequestModel::TYPE_EXPRESS);
        }

        $request = $this->initiatePayment($paymentRequest);
        $url = $request['url'] ?? null;
        $response = new PaymentResponse($request);

        if ($url) {
            $response->setRedirectUrl($url);
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    public function captureFromGateway(Transaction $transaction): CaptureResponse
    {
        $order = $transaction->getOrder();
        $authorizedTransation = $this->getSuccessfulTransactionForOrder($order);
        $parentTransaction = $authorizedTransation->getParent();
        $gateway = $this->getGateway();
        //$amount            = (int)$transaction->amount * 100;
        $amount = 0;
        $response = Vipps::$plugin->getApi()->post(sprintf('/ecomm/v2/payments/%s/capture', $parentTransaction->reference), [
            'merchantInfo' => [
                'merchantSerialNumber' => App::parseEnv($gateway->merchantSerialNumber),
            ],
            'transaction' => [
                'amount' => $amount,
                // TODO: Set from status message?
                'transactionText' => $order->getEmail(),
            ],
        ]);

        return new CaptureResponse($response);
    }

    /**
     * @throws Exception
     */
    public function refundFromGateway(Transaction $transaction): CaptureResponse
    {
        $order = $transaction->getOrder();
        $authorizedTransation = $this->getSuccessfulTransactionForOrder($order);
        $parentTransaction = $authorizedTransation->getParent();
        $gateway = $this->getGateway();
        $amount = Currency::roundAndConvertToMinorUnit($transaction->amount);
        $transactionText = empty($transaction->note) ? $order->getEmail() : $transaction->note;
        //dd($parentTransaction->reference, $amount, $transactionText);
        $response = Vipps::$plugin->getApi()->post(sprintf('/ecomm/v2/payments/%s/refund', $parentTransaction->reference), [
            'merchantInfo' => [
                'merchantSerialNumber' => App::parseEnv($gateway->merchantSerialNumber),
            ],
            'transaction' => [
                'amount' => $amount,
                // TODO: Set from status message?
                'transactionText' => $transactionText,
            ],
        ]);


        return new CaptureResponse($response);
    }

    public function onStatusChange(OrderStatusEvent $e): void
    {
        try {
            $order = $e->order;
            $gateway = $this->getGateway();
            $enabled = $gateway->captureOnStatusChange && $this->isVippsGateway($order);

            if ($enabled && $gateway->captureStatusUid === $e->orderHistory->getNewStatus()->uid) {
                $transaction = $this->getSuccessfulTransactionForOrder($order);

                if ($transaction && $transaction->canCapture()) {
                    // capture transaction and display result
                    $child = Plugin::getInstance()->getPayments()->captureTransaction($transaction);

                    $message = $child->message !== '' && $child->message !== '0' ? ' (' . $child->message . ')' : '';

                    if ($child->status === TransactionRecord::STATUS_SUCCESS) {
                        $child->order->updateOrderPaidInformation();
                        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Transaction captured successfully: {message}', [
                            'message' => $message,
                        ]));
                    } else {
                        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t capture transaction: {message}', [
                            'message' => $message,
                        ]));
                    }
                } else {
                    Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t capture transaction.', ['id' => $transaction->id]));
                }
            }
        } catch (\Exception $exception) {
            LogToFile::error('Not able to change status: ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
            Craft::$app->getErrorHandler()->logException($exception);
        }
    }

    public function createResponseFromCallback($payload = []): void
    {
        // https://github.com/vippsas/vipps-ecom-api/blob/master/vipps-ecom-api.md#callback
    }

    public function getTransactionText(Order $order): string
    {
        $text = empty($this->getGateway()->transactionText) ? '{lineItemsText}' : $this->getGateway()->transactionText;
        $text = App::parseEnv($text);

        return Craft::$app->getView()->renderObjectTemplate($text, $order, [
            'lineItemsText' => $this->getLineItemsAsText($order),
        ]);
    }

    public function getLineItemsAsText(Order $order): string
    {
        // @todo Crop?
        $lineItems = $order->getLineItems();
        $lines = array_map(static function (LineItem $item): string {
            $variant = $item->getPurchasable();
            /** @var Variant $variant */
            return sprintf('%dx %s', $item->qty, $variant->title);
        }, $lineItems);

        return implode("\n", $lines);
    }

    public function getFallbackActionUrl(string $transactionId): string
    {
        return UrlHelper::siteUrl('vipps/callbacks/v2/return/' . $transactionId);
    }

    public function getFallbackUrl(Order $order): string
    {
        $gateway = $this->getGateway();
        $url = App::parseEnv($gateway->fallbackUrl);
        $parsedUrl = Craft::$app->getView()->renderObjectTemplate($url, $order);
        $defaultUrl = UrlHelper::siteUrl('/');

        return empty($parsedUrl) ? $defaultUrl : $parsedUrl;
    }

    public function getFallbackErrorUrl(Order $order): string
    {
        $gateway = $this->getGateway();
        $url = App::parseEnv($gateway->errorFallbackUrl);
        $parsedUrl = Craft::$app->getView()->renderObjectTemplate($url, $order);
        $defaultUrl = $this->getFallbackUrl($order);

        return empty($parsedUrl) ? $defaultUrl : $parsedUrl;
    }

    public function getGateway(): Gateway
    {
        if (!$this->_gateway) {
            $gateways = Plugin::getInstance()->getGateways()->getAllCustomerEnabledGateways();
            $this->_gateway = ArrayHelper::firstWhere($gateways, static fn($gateway): bool => $gateway instanceof Gateway);

            if (!$this->_gateway) {
                throw new Exception('The Vipps gateway is not setup correctly.');
            }
        }

        return $this->_gateway;
    }

    public function getSuccessfulTransactionForOrder(Order $order): ?Transaction
    {
        return ArrayHelper::firstWhere($order->getTransactions(), static fn(Transaction $transaction): bool => $transaction->status === TransactionRecord::STATUS_SUCCESS
            && $transaction->type === TransactionRecord::TYPE_AUTHORIZE
            && $transaction->parentId !== null);
    }

    public function getIsExpress(): bool
    {
        return (bool)$this->_express;
    }

    public function setIsExpress(bool $value = true): static
    {
        $this->_express = $value;

        return $this;
    }

    public function getOrderDetails(Order $order): void
    {
    }

    public function isVippsGateway(Order $order): bool
    {
        return $order->getGateway() instanceof Gateway;
    }
}
