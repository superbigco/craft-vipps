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

use craft\commerce\Plugin;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
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
        $result = 'something';
        // Check our Plugin's settings for `someAttribute`
        if (Vipps::$plugin->getSettings()->someAttribute) {
        }

        return $result;
    }

    public function initiatePayment($data = [])
    {
        // ref https://vippsas.github.io/vipps-ecom-api/#/Vipps_eCom_API/initiatePaymentV3UsingPOST
        $settings        = Vipps::$plugin->getSettings();
        $cart            = Plugin::getInstance()->cart->getCart();
        $callbackPrefix  = UrlHelper::siteUrl('vipps/callbacks');
        $transactionText = Craft::$app->getView()->renderObjectTemplate($settings->transactionText, $cart);
        $timestamp       = (new \DateTime())->format(DateTime::ATOM);
        $payload         = [
            'customerInfo' =>
                [
                    //'mobileNumber' => 91234567,
                ],
            'merchantInfo' =>
                [
                    'authToken'             => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1Ni ',
                    'callbackPrefix'        => $callbackPrefix,
                    'consentRemovalPrefix'  => $callbackPrefix,
                    'fallBack'              => UrlHelper::siteUrl('/vipps/fallback/' . $cart->number),
                    'isApp'                 => false,
                    'merchantSerialNumber'  => $settings->merchantSerialNumber,
                    'paymentType'           => 'eComm Regular Payment',
                    'shippingDetailsPrefix' => $callbackPrefix,
                ],
            'transaction'  =>
                [
                    'amount'          => 20000, // In øre
                    'orderId'         => $cart->number,
                    //'refOrderId'      => 'merchantOrder123abc',
                    'timeStamp'       => $timestamp,
                    'transactionText' => $transactionText,
                ],
        ];
    }

    public function capturePayment($orderId = null)
    {

    }

    public function refundPayment($orderId = null)
    {

    }
}
