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

use craft\commerce\elements\Order;
use craft\commerce\models\Transaction;
use craft\helpers\UrlHelper;
use DateTime;
use superbig\vipps\helpers\StringHelper;
use superbig\vipps\Vipps;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 *
 * @property string      $mobileNumber The account to send Vipps request to
 * @property Order       $order
 * @property Transaction $transaction
 * @property float       $amount       Total amount in minor units (øre)
 */
class PaymentRequestModel extends Model
{
    // Public Properties
    // =========================================================================

    const TYPE_EXPRESS = 'express';
    const TYPE_REGULAR = 'regular';
    const TYPE_CAPTURE = 'capture';

    const PAYMENT_TYPE_PARAMS = [
        self::TYPE_EXPRESS => 'eComm Express Payment',
        self::TYPE_REGULAR => 'eComm Regular Payment',
    ];

    public  $mobileNumber = '';
    public  $type         = self::TYPE_EXPRESS;
    public  $amount;
    public  $orderId;
    public  $order;
    public  $transaction;
    private $transactionShortId;
    private $_transactionText;
    private $_url;

    // Public Methods
    // =========================================================================

    public function init()
    {
        parent::init();

        $this->transactionShortId = StringHelper::transactionId();
    }

    public function getPayload()
    {
        // Settings
        $callbackPrefix = UrlHelper::siteUrl('vipps/callbacks');
        $timestamp      = (new \DateTime())->format(DateTime::ATOM);

        // Order info
        $orderId             = $this->order->id;
        $fallbackUrl         = Vipps::$plugin->payments->getFallbackUrl($this->order);
        $orderTotalMinorUnit = $this->order->getTotalPrice() * 100;
        $billingAddress      = $this->order->getBillingAddress();
        $phoneNumber         = !empty($billingAddress->phone) ? $billingAddress->phone : '48059154';
        $gateway             = Vipps::$plugin->payments->getGateway();
        $billingAddress      = $this->order->getBillingAddress();
        $settings            = $gateway;

        $payload = [
            'customerInfo' => [
                'mobileNumber' => '',
            ],
            'merchantInfo' =>
                [
                    'authToken'             => $gateway->getAuthToken(),
                    'callbackPrefix'        => $callbackPrefix,
                    'shippingDetailsPrefix' => $callbackPrefix,
                    'consentRemovalPrefix'  => $callbackPrefix,
                    'fallBack'              => $fallbackUrl,
                    'isApp'                 => false,
                    'merchantSerialNumber'  => Craft::parseEnv($gateway->merchantSerialNumber),
                    'paymentType'           => $this->getType(),
                ],
            'transaction'  =>
                [
                    'amount'          => $orderTotalMinorUnit, // In øre
                    'orderId'         => $this->getTransactionShortId(),
                    'timeStamp'       => $timestamp,
                    'transactionText' => $this->getTransactionText(),
                ],
        ];

        if ($gateway->useBillingPhoneAsVippsPhoneNumber && !empty($billingAddress->phone)) {
            $payload['customerInfo'] = [
                'mobileNumber' => $billingAddress->phone,
            ];
        }

        return $payload;
    }

    public function getTransactionText(): string
    {
        if (!$this->_transactionText) {
            $this->_transactionText = Vipps::$plugin->payments->getTransactionText($this->order);
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
            'shortId'              => $this->getTransactionShortId(),
            'orderId'              => $this->order->id,
            'transactionReference' => $this->getTransactionShortId(),
        ]);
    }

    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return self::PAYMENT_TYPE_PARAMS[ $this->type ];
    }

    public function getUrl()
    {
        return $this->_url;
    }

    public function setUrl($url = null)
    {
        $this->_url = $url;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['order'], 'required'],
        ];
    }
}
