<?php

declare(strict_types=1);

namespace superbig\vipps\gateways;

use Craft;
use craft\commerce\base\Gateway as BaseGateway;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\PaymentSource;
use craft\commerce\models\Transaction;
use craft\web\Response as WebResponse;

/**
 * Vipps MobilePay payment gateway for Craft Commerce 5.
 *
 * Gateway settings are stored as properties and configured in the CP.
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
            'clientId' => Craft::parseEnv($this->clientId),
            'clientSecret' => Craft::parseEnv($this->clientSecret),
            'subscriptionKey' => Craft::parseEnv($this->subscriptionKey),
            'msn' => Craft::parseEnv($this->merchantSerialNumber),
            'testMode' => $this->testMode,
        ];
    }

    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        // TODO: Step 4 — implement authorize flow
        throw new \RuntimeException('Not yet implemented');
    }

    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        // TODO: Step 4 — implement capture flow
        throw new \RuntimeException('Not yet implemented');
    }

    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        // TODO: Step 4 — implement complete purchase flow
        throw new \RuntimeException('Not yet implemented');
    }

    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        // TODO: Step 4 — implement purchase flow
        throw new \RuntimeException('Not yet implemented');
    }

    public function refund(Transaction $transaction): RequestResponseInterface
    {
        // TODO: Step 4 — implement refund flow
        throw new \RuntimeException('Not yet implemented');
    }

    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        // TODO: Step 4 — implement complete authorize (return from Vipps redirect)
        throw new \RuntimeException('Not yet implemented');
    }

    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        throw new \RuntimeException('Payment sources are not supported by Vipps');
    }

    public function deletePaymentSource(string $token): bool
    {
        throw new \RuntimeException('Payment sources are not supported by Vipps');
    }

    public function processWebHook(): WebResponse
    {
        // TODO: Step 6 — implement webhook processing
        throw new \RuntimeException('Webhooks not yet implemented');
    }

    public function getPaymentFormModel(): BasePaymentForm
    {
        // TODO: Step 5 — implement payment form
        throw new \RuntimeException('Not yet implemented');
    }

    public function getPaymentFormHtml(array $params): ?string
    {
        // TODO: Step 5 — implement payment form HTML
        return null;
    }

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
        return false; // TODO: Step 6 — webhooks
    }

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
}
