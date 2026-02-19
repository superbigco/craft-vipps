<?php

declare(strict_types=1);

namespace superbig\vipps\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\helpers\App;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Response as WebResponse;
use superbig\vipps\exceptions\VippsApiException;
use superbig\vipps\models\PaymentForm;
use superbig\vipps\responses\VippsResponse;
use superbig\vipps\Vipps;
use yii\base\NotSupportedException;

/**
 * Vipps MobilePay payment gateway for Craft Commerce 5.
 *
 * Payment flow:
 * 1. authorize() → creates payment, returns redirect to Vipps
 * 2. User approves in Vipps app
 * 3. completeAuthorize() → polls Vipps for AUTHORIZED state
 * 4. capture() → captures the authorized amount
 * 5. refund() → refunds a captured payment
 *
 * All credential properties support environment variable syntax ($MY_VAR).
 */
class Gateway extends BaseGateway
{
    // Gateway credential properties (support env vars)
    public string $clientId = '';

    public string $clientSecret = '';

    public string $subscriptionKey = '';

    public string $merchantSerialNumber = '';

    public string $transactionText = '';

    public bool $testMode = false;

    // Express Checkout settings
    public bool $expressCheckout = false;

    /** @var string Profile scope to request from Vipps (e.g., "name phoneNumber address") */
    public string $expressProfileScope = 'name phoneNumber address';

    public static function displayName(): string
    {
        return Craft::t('vipps', 'Vipps MobilePay');
    }

    /**
     * Resolve gateway credentials, parsing environment variables.
     *
     * @return array{
     *     clientId: string,
     *     clientSecret: string,
     *     subscriptionKey: string,
     *     msn: string,
     *     testMode: bool,
     * }
     */
    public function getCredentials(): array
    {
        return [
            'clientId' => App::parseEnv($this->clientId),
            'clientSecret' => App::parseEnv($this->clientSecret),
            'subscriptionKey' => App::parseEnv($this->subscriptionKey),
            'msn' => App::parseEnv($this->merchantSerialNumber),
            'testMode' => $this->testMode,
        ];
    }

    // =========================================================================
    // Payment Operations
    // =========================================================================

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        try {
            $credentials = $this->getCredentials();
            $order = $transaction->getOrder();

            $payload = [
                'amount' => [
                    'currency' => $transaction->paymentCurrency,
                    'value' => (int) ($transaction->paymentAmount * 100),
                ],
                'paymentMethod' => ['type' => 'WALLET'],
                'reference' => $transaction->hash,
                'userFlow' => 'WEB_REDIRECT',
                'returnUrl' => UrlHelper::actionUrl('commerce/payments/complete-payment', [
                    'commerceTransactionHash' => $transaction->hash,
                ]),
                'paymentDescription' => $this->_getPaymentDescription($order),
            ];

            // Pre-fill phone number if provided
            if ($form instanceof PaymentForm && $form->phoneNumber) {
                $payload['customer'] = ['phoneNumber' => $form->phoneNumber];
            }

            // Express Checkout: add shipping options and profile scope
            if ($this->expressCheckout) {
                $payload['shipping'] = $this->_buildShippingPayload($transaction);
                $payload['profile'] = ['scope' => $this->expressProfileScope];
            }

            $response = $this->_getApi()->createPayment(
                $payload,
                $transaction->hash,
                $credentials,
            );

            // createPayment returns {redirectUrl, reference} — add CREATED state for VippsResponse
            $response['state'] = 'CREATED';

            return new VippsResponse($response);
        } catch (VippsApiException $e) {
            return VippsResponse::fromError($e->getMessage(), $e->getHttpStatusCode());
        }
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        try {
            $credentials = $this->getCredentials();
            $response = $this->_getApi()->getPayment($transaction->reference, $credentials);

            // Express Checkout: extract user profile and update order addresses
            if ($this->expressCheckout) {
                $this->_applyExpressProfileToOrder($transaction, $response);
            }

            return new VippsResponse($response);
        } catch (VippsApiException $e) {
            return VippsResponse::fromError($e->getMessage(), $e->getHttpStatusCode());
        }
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        try {
            $credentials = $this->getCredentials();
            $amount = [
                'currency' => $transaction->paymentCurrency,
                'value' => (int) ($transaction->paymentAmount * 100),
            ];

            $response = $this->_getApi()->capturePayment(
                $reference,
                $amount,
                $reference . '-capture',
                $credentials,
            );

            // Capture returns aggregate data — set state for VippsResponse
            $response['state'] = 'CAPTURED';

            return new VippsResponse($response);
        } catch (VippsApiException $e) {
            return VippsResponse::fromError($e->getMessage(), $e->getHttpStatusCode());
        }
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        try {
            $credentials = $this->getCredentials();
            $parentTransaction = $transaction->getParent();

            if ($parentTransaction === null) {
                return VippsResponse::fromError('Cannot refund: no parent transaction found');
            }

            $amount = [
                'currency' => $transaction->paymentCurrency,
                'value' => (int) ($transaction->paymentAmount * 100),
            ];

            $response = $this->_getApi()->refundPayment(
                $parentTransaction->reference,
                $amount,
                $parentTransaction->reference . '-refund-' . $transaction->id,
                $credentials,
            );

            // Refund returns aggregate data — mark as successful
            $response['state'] = 'CAPTURED';

            return new VippsResponse($response);
        } catch (VippsApiException $e) {
            return VippsResponse::fromError($e->getMessage(), $e->getHttpStatusCode());
        }
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        throw new NotSupportedException('Vipps does not support direct purchase. Use authorize + capture.');
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        throw new NotSupportedException('Vipps does not support direct purchase. Use authorize + capture.');
    }

    // =========================================================================
    // Payment Sources (not supported)
    // =========================================================================

    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new NotSupportedException('Payment sources are not supported by Vipps');
    }

    public function deletePaymentSource(string $token): bool
    {
        throw new NotSupportedException('Payment sources are not supported by Vipps');
    }

    // =========================================================================
    // Webhooks
    // =========================================================================

    public function processWebHook(): WebResponse
    {
        $rawBody = Craft::$app->getRequest()->getRawBody();
        $response = Craft::$app->getResponse();
        $response->format = WebResponse::FORMAT_RAW;

        $data = Json::decodeIfJson($rawBody);
        if (!is_array($data)) {
            Craft::warning('Vipps webhook: invalid JSON body', __METHOD__);
            $response->data = 'invalid payload';
            $response->statusCode = 400;

            return $response;
        }

        $eventName = $data['name'] ?? 'unknown';
        $reference = $data['reference'] ?? 'unknown';

        Craft::info(
            sprintf('Vipps webhook received: %s for reference %s', $eventName, $reference),
            __METHOD__,
        );

        // Commerce handles transaction status updates via completeAuthorize/capture.
        // The webhook confirms the event happened — we log it and return 200.
        // Commerce's mutex on transactionHash prevents race conditions with the return URL.

        $response->data = 'ok';
        $response->statusCode = 200;

        return $response;
    }

    public function getTransactionHashFromWebhook(): ?string
    {
        $rawBody = Craft::$app->getRequest()->getRawBody();
        $data = Json::decodeIfJson($rawBody);

        if (!is_array($data)) {
            return null;
        }

        // The Vipps reference IS the Commerce transaction hash
        return $data['reference'] ?? null;
    }

    // =========================================================================
    // Payment Form
    // =========================================================================

    public function getPaymentFormModel(): BasePaymentForm
    {
        return new PaymentForm();
    }

    public function getPaymentFormHtml(array $params): ?string
    {
        // Vipps handles payment UI in the app — no form needed on the site.
        // Phone number can be passed programmatically via PaymentForm.
        return null;
    }

    // =========================================================================
    // Capabilities
    // =========================================================================

    public function supportsAuthorize(): bool
    {
        return true;
    }

    public function supportsCapture(): bool
    {
        return true;
    }

    public function supportsCompleteAuthorize(): bool
    {
        return true;
    }

    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    public function supportsPaymentSources(): bool
    {
        return false;
    }

    public function supportsPurchase(): bool
    {
        return false;
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    public function supportsPartialRefund(): bool
    {
        return true;
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public function getPaymentTypeOptions(): array
    {
        return [
            'authorize' => Craft::t('commerce', 'Authorize Only (Manually Capture)'),
        ];
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('vipps/gateway-settings', [
            'gateway' => $this,
        ]);
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            [
                'clientId',
                'clientSecret',
                'subscriptionKey',
                'merchantSerialNumber',
            ],
            'required',
        ];

        return $rules;
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function _getApi(): \superbig\vipps\services\VippsApi
    {
        /** @var \superbig\vipps\services\VippsApi */
        return Vipps::getInstance()->get('vippsApi');
    }

    /**
     * Build a payment description from the order.
     */
    private function _getPaymentDescription(?\craft\commerce\elements\Order $order): string
    {
        if ($this->transactionText) {
            return App::parseEnv($this->transactionText);
        }

        if ($order === null) {
            return 'Craft Commerce payment';
        }

        return sprintf('Order %s', $order->shortNumber ?: $order->number);
    }

    /**
     * Build the shipping payload for Express Checkout.
     *
     * Uses dynamic shipping via a callback URL that Vipps will call
     * with the user's address to get available shipping options.
     */
    private function _buildShippingPayload(Transaction $transaction): array
    {
        $callbackUrl = UrlHelper::actionUrl('vipps/express/shipping-callback', [
            'reference' => $transaction->hash,
        ]);

        return [
            'dynamicOptions' => [
                'callbackUrl' => $callbackUrl,
                'callbackAuthorizationToken' => $this->_generateCallbackToken($transaction),
            ],
        ];
    }

    /**
     * Generate a simple HMAC token for verifying shipping callbacks.
     *
     * This prevents unauthorized parties from probing our shipping endpoint.
     * The token is derived from the transaction hash and the client secret.
     */
    private function _generateCallbackToken(Transaction $transaction): string
    {
        $secret = App::parseEnv($this->clientSecret);

        return hash_hmac('sha256', $transaction->hash, $secret);
    }

    /**
     * Verify a shipping callback authorization token.
     */
    public function verifyCallbackToken(string $token, string $reference): bool
    {
        $secret = App::parseEnv($this->clientSecret);
        $expected = hash_hmac('sha256', $reference, $secret);

        return hash_equals($expected, $token);
    }

    /**
     * Extract user profile data from a Vipps Express payment response
     * and apply it to the Commerce order's billing/shipping addresses.
     */
    private function _applyExpressProfileToOrder(Transaction $transaction, array $paymentData): void
    {
        $userDetails = $paymentData['userDetails'] ?? null;
        $shippingDetails = $paymentData['shippingDetails'] ?? null;

        if ($userDetails === null && $shippingDetails === null) {
            return;
        }

        $order = $transaction->getOrder();
        if ($order === null) {
            return;
        }

        // Build address from Vipps profile data
        $addressData = $this->_buildAddressFromVippsProfile($userDetails, $shippingDetails);

        if ($addressData !== null) {
            $order->setShippingAddress($addressData);
            $order->setBillingAddress($addressData);
        }

        // Set shipping method if returned
        if ($shippingDetails !== null && !empty($shippingDetails['shippingOptionId'])) {
            $order->shippingMethodHandle = $shippingDetails['shippingOptionId'];
        }

        // Set email from profile if order doesn't have one
        $email = $userDetails['email'] ?? null;
        if ($email && empty($order->getEmail())) {
            $order->setEmail($email);
        }

        $order->recalculate();

        if (!Craft::$app->getElements()->saveElement($order, false)) {
            Craft::error(
                sprintf('Failed to save order after Express profile update: %s', Json::encode($order->getErrors())),
                __METHOD__,
            );
        }
    }

    /**
     * Build a Craft Address array from Vipps userDetails and shippingDetails.
     *
     * @return array|null Address attributes for Order::setShippingAddress()
     */
    private function _buildAddressFromVippsProfile(?array $userDetails, ?array $shippingDetails): ?array
    {
        // Prefer shipping address from shippingDetails, fall back to userDetails addresses
        $addressSource = $shippingDetails['address'] ?? null;

        if ($addressSource === null && $userDetails !== null) {
            $addresses = $userDetails['addresses'] ?? [];
            $addressSource = $addresses[0] ?? null;
        }

        if ($addressSource === null) {
            return null;
        }

        $address = [
            'addressLine1' => $addressSource['addressLine1'] ?? null,
            'addressLine2' => $addressSource['addressLine2'] ?? null,
            'locality' => $addressSource['city'] ?? null,
            'postalCode' => $addressSource['postCode'] ?? null,
            'countryCode' => $addressSource['country'] ?? 'NO',
        ];

        // Add name from userDetails
        if ($userDetails !== null) {
            $address['firstName'] = $userDetails['firstName'] ?? null;
            $address['lastName'] = $userDetails['lastName'] ?? null;
        }

        return $address;
    }
}
