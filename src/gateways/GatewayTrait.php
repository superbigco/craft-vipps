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
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\payments\OffsitePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;
use Throwable;
use yii\base\NotSupportedException;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
trait GatewayTrait
{
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
     * Returns payment Form HTML
     *
     * @param array $params
     *
     * @return string|null
     */
    public function getPaymentFormHtml(array $params): ?string
    {
        return '';
    }

    /**
     * Creates a payment source from source data and user id.
     *
     * @param BasePaymentForm $sourceData
     * @param int             $userId
     *
     * @return PaymentSource
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
    }

    /**
     * Deletes a payment source on the gateway by its token.
     *
     * @param string $token
     *
     * @return bool
     */
    public function deletePaymentSource(string $token): bool
    {
        return false;
    }

    /**
     * Processes a webhook and return a response
     *
     * @return WebResponse
     * @throws Throwable if something goes wrong
     */
    public function processWebHook(): WebResponse
    {
        // TODO: Implement processWebHook() method.
    }

    /**
     * Returns payment form model to use in payment forms.
     *
     * @return BasePaymentForm
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new OffsitePaymentForm();
    }

    /**
     * Complete the authorization for offsite payments.
     *
     * @param Transaction $transaction The transaction
     *
     * @return RequestResponseInterface
     * @throws NotSupportedException
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        throw new NotSupportedException(Craft::t('commerce', 'Complete Authorize is not supported by this gateway'));
    }

    /**
     * Returns true if gateway supports authorize requests.
     *
     * @return bool
     */
    public function supportsAuthorize(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports capture requests.
     *
     * @return bool
     */
    public function supportsCapture(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports completing authorize requests
     *
     * @return bool
     */
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    /**
     * Returns true if gateway supports completing purchase requests
     *
     * @return bool
     */
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    /**
     * Returns true if gateway supports payment sources
     *
     * @return bool
     */
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    /**
     * Returns true if gateway supports purchase requests.
     *
     * @return bool
     */
    public function supportsPurchase(): bool
    {
        return false;
    }

    /**
     * Returns true if gateway supports refund requests.
     *
     * @return bool
     */
    public function supportsRefund(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports partial refund requests.
     *
     * @return bool
     */
    public function supportsPartialRefund(): bool
    {
        return true;
    }

    /**
     * Returns true if gateway supports webhooks.
     *
     * @return bool
     */
    public function supportsWebhooks(): bool
    {
        return false;
    }
}
