<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\OrderStatus;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\helpers\StringHelper;

use superbig\vipps\Vipps;
use yii\base\Exception;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Gateway extends BaseGateway
{
    use GatewayTrait;

    public string $clientId = '';
    public string $clientSecret = '';
    public string $subscriptionKeyAccessToken = '';
    public string $subscriptionKeyEcommerce = '';
    public string $merchantSerialNumber = '';
    public string $transactionText = '';
    public bool $testMode = false;
    public bool $expressCheckout = true;
    public bool $createUserOnExpressCheckout = true;
    public bool $loginWithVipps = false;
    public bool $addItemToCartIfAlreadyExists = false;
    public bool $newCartOnExpressCheckout = true;
    public string $fallbackUrl = '';
    public string $errorFallbackUrl = '';
    public string $authToken = '';
    public bool $captureOnStatusChange = false;
    public string $captureStatusUid = '';
    public bool $useBillingPhoneAsVippsPhoneNumber = true;


    public static function displayName(): string
    {
        return Craft::t('vipps', 'Vipps');
    }

    /**
     * Makes an authorize request.
     *
     * @param Transaction     $transaction The authorize transaction
     * @param BasePaymentForm $form        A form filled with payment info
     *
     * @return RequestResponseInterface
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return Vipps::$plugin->getPayments()->intiatePaymentFromGateway($transaction);
    }

    /**
     * Makes a capture request.
     *
     * @param Transaction $transaction The capture transaction
     * @param string      $reference   Reference for the transaction being captured.
     *
     * @return RequestResponseInterface
     * @throws Exception
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        // https://github.com/craftcms/commerce-omnipay/blob/master/src/base/Gateway.php#L549
        return Vipps::$plugin->getPayments()->captureFromGateway($transaction);
    }

    /**
     * Makes an refund request.
     *
     * @param Transaction $transaction The refund transaction
     *
     * @return RequestResponseInterface
     * @throws Exception
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        return Vipps::$plugin->getPayments()->refundFromGateway($transaction);
    }

    public function getAuthToken()
    {
        return !empty($this->authToken) ? $this->authToken : StringHelper::UUID();
    }


    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('vipps/gatewaySettings', [
            'gateway' => $this,
            'authToken' => $this->getAuthToken(),
            'statuses' => $this->getOrderStatuses(),
        ]);
    }

    public function getPaymentTypeOptions(): array
    {
        return [
            'authorize' => Craft::t('commerce', 'Authorize Only (Manually Capture)'),
        ];
    }

    public function getOrderStatuses(): array
    {
        return array_map(function(OrderStatus $orderStatus) {
            return [
                'label' => $orderStatus->name,
                'value' => $orderStatus->uid,
            ];
        }, Plugin::getInstance()->getOrderStatuses()->getAllOrderStatuses());
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [
                [
                    'clientId',
                    'clientSecret',
                    'subscriptionKeyAccessToken',
                    'merchantSerialNumber',
                    'authToken',
                ],
                'required',
            ],
        ]);
    }
}
