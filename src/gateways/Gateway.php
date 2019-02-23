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

use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\helpers\StringHelper;
use superbig\vipps\Vipps;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Gateway extends BaseGateway
{
    use GatewayTrait;

    // Public Properties
    // =========================================================================

    public $clientId                     = '';
    public $clientSecret                 = '';
    public $subscriptionKeyAccessToken   = '';
    public $subscriptionKeyEcommerce     = '';
    public $merchantSerialNumber         = '';
    public $transactionText              = '';
    public $testMode                     = false;
    public $expressCheckout              = true;
    public $createUserOnExpressCheckout  = true;
    public $loginWithVipps               = false;
    public $addItemToCartIfAlreadyExists = false;
    public $newCartOnExpressCheckout     = true;
    public $fallbackUrl                  = '';
    public $authToken                    = '';

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('vipps', 'Vipps');
    }

    // Public Methods
    // =========================================================================

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
        $request = Vipps::$plugin->payments->intiatePaymentFromGateway($transaction);

        return $request;
    }

    /**
     * Makes a capture request.
     *
     * @param Transaction $transaction The capture transaction
     * @param string      $reference   Reference for the transaction being captured.
     *
     * @return RequestResponseInterface
     * @throws \yii\base\Exception
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        // https://github.com/craftcms/commerce-omnipay/blob/master/src/base/Gateway.php#L549
        $response = Vipps::$plugin->getPayments()->captureFromGateway($transaction);

        return $response;
    }

    /**
     * Complete the purchase for offsite payments.
     *
     * @param Transaction $transaction The transaction
     *
     * @return RequestResponseInterface
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        // TODO: Implement completePurchase() method.
    }

    /**
     * Makes a purchase request.
     *
     * @param Transaction     $transaction The purchase transaction
     * @param BasePaymentForm $form        A form filled with payment info
     *
     * @return RequestResponseInterface
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        // TODO: Implement purchase() method.
    }

    /**
     * Makes an refund request.
     *
     * @param Transaction $transaction The refund transaction
     *
     * @return RequestResponseInterface
     * @throws \yii\base\Exception
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        $response = Vipps::$plugin->getPayments()->refundFromGateway($transaction);

        return $response;
    }

    public function getAuthToken()
    {
        return !empty($this->authToken) ? $this->authToken : StringHelper::UUID();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('vipps/gatewaySettings', [
            'gateway'   => $this,
            'authToken' => $this->getAuthToken(),
        ]);
    }

    public function getPaymentTypeOptions(): array
    {
        return [
            'authorize' => Craft::t('commerce', 'Authorize Only (Manually Capture)'),
        ];
    }

    public function rules()
    {
        return array_merge(parent::rules(), [
            [
                [
                    'clientId',
                    'clientSecret',
                    'subscriptionKeyAccessToken',
                    'subscriptionKeyEcommerce',
                    'merchantSerialNumber',
                    'authToken',
                ],
                'required',
            ],
        ]);
    }
}
