<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\models;

use Craft;
use craft\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\models\Settings;
use craft\commerce\models\Transaction;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use DateTime;

use superbig\vipps\helpers\StringHelper;
use superbig\vipps\Vipps;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 *
 * @property string $mobileNumber The account to send Vipps request to
 * @property Order $order
 * @property Transaction $transaction
 * @property float $amount       Total amount in minor units (øre)
 */
class PaymentRequestModel extends Model
{

    const TYPE_EXPRESS = 'express';
    const TYPE_REGULAR = 'regular';
    const TYPE_CAPTURE = 'capture';

    const PAYMENT_TYPE_PARAMS = [
        self::TYPE_EXPRESS => 'eComm Express Payment',
        self::TYPE_REGULAR => 'eComm Regular Payment',
    ];

    public string $mobileNumber = '';
    public string $type = self::TYPE_EXPRESS;
    public float|int $amount = 0;
    public string $orderId;
    public ?Order $order;
    public ?Transaction $transaction;
    private string $transactionShortId;
    private string $_transactionText = '';
    private string $_url = '';

    // Public Methods
    // =========================================================================

    public function init(): void
    {
        parent::init();

        $this->transactionShortId = StringHelper::transactionId();
    }

    public function getPayload(): array
    {
        // Settings
        $callbackPrefix = UrlHelper::siteUrl('vipps/callbacks');
        $timestamp = (new DateTime())->format(DateTime::ATOM);

        // Order info
        $orderId = $this->order->id;
        $fallbackUrl = Vipps::$plugin->getPayments()->getFallbackActionUrl($this->getTransactionShortId());
        $billingAddress = $this->order->getBillingAddress();
        $phoneNumber = !empty($billingAddress->phone) ? $billingAddress->phone : '48059154';
        $gateway = Vipps::$plugin->getPayments()->getGateway();
        $billingAddress = $this->order->getBillingAddress();

        // Getting amount
        // Note: We have to convert it to int in this sequence because Order::getTotalPrice() returns it as double
        // which might have unseen decimals which leads to weird results like 1259,09 when everywhere else its 1259,10
        // @todo Should check if price is set to Settings::MINIMUM_TOTAL_PRICE_STRATEGY_SHIPPING
        $orderTotal = $this->order->getTotalPrice();
        $orderTotalMinorUnit = intval(round(floatval("{$orderTotal}") * 100, 2));
        $payload = [
            'customerInfo' => [
                'mobileNumber' => '',
            ],
            'merchantInfo' =>
                [
                    'authToken' => $gateway->getAuthToken(),
                    'callbackPrefix' => $callbackPrefix,
                    'shippingDetailsPrefix' => $callbackPrefix,
                    'consentRemovalPrefix' => $callbackPrefix,
                    'fallBack' => $fallbackUrl,
                    'isApp' => false,
                    'merchantSerialNumber' => App::parseEnv($gateway->merchantSerialNumber),
                    'paymentType' => $this->getType(),
                ],
            'transaction' =>
                [
                    'amount' => $orderTotalMinorUnit, // In øre
                    'orderId' => $this->getTransactionShortId(),
                    'timeStamp' => $timestamp,
                    'transactionText' => $this->getTransactionText(),
                ],
        ];

        if ($gateway->useBillingPhoneAsVippsPhoneNumber && !empty($billingAddress->phone)) {
            $payload['customerInfo'] = [
                'mobileNumber' => StringHelper::getCleanPhone($billingAddress->phone),
            ];
        }

        return $payload;
    }

    public function getTransactionText(): string
    {
        if (empty($this->_transactionText)) {
            $this->_transactionText = Vipps::$plugin->getPayments()->getTransactionText($this->order);
        }

        return $this->_transactionText;
    }


    public function getTransactionShortId(): string
    {
        return $this->transactionShortId;
    }

    public function getPaymentRecord(): PaymentModel
    {
        return new PaymentModel([
            'shortId' => $this->getTransactionShortId(),
            'orderId' => $this->order->id,
            'transactionReference' => $this->getTransactionShortId(),
        ]);
    }

    public function setType($type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return self::PAYMENT_TYPE_PARAMS[$this->type];
    }

    public function getUrl(): string
    {
        return $this->_url;
    }

    public function setUrl($url = null): static
    {
        $this->_url = $url;

        return $this;
    }


    public function rules(): array
    {
        return [
            [['order'], 'required'],
        ];
    }
}
