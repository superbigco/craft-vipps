<?php

declare(strict_types=1);

namespace superbig\vipps\services;

use Craft;
use craft\base\Component;
use craft\commerce\Plugin as CommercePlugin;
use craft\helpers\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use superbig\vipps\exceptions\VippsApiException;
use superbig\vipps\exceptions\VippsAuthenticationException;
use superbig\vipps\Vipps;

/**
 * HTTP client for the Vipps ePayment API v1.
 *
 * All methods require gateway credentials to be passed explicitly.
 * This service handles:
 * - Bearer token authentication (via TokenManager)
 * - Required headers (MSN, subscription key, system info)
 * - Idempotency keys for mutating operations
 * - Structured error handling with typed exceptions
 * - Request/response logging
 *
 * @see https://developer.vippsmobilepay.com/api/epayment
 */
class VippsApi extends Component
{
    public const PRODUCTION_BASE_URL = 'https://api.vipps.no';

    public const TEST_BASE_URL = 'https://apitest.vipps.no';

    private const EPAYMENT_PATH = '/epayment/v1';

    /**
     * Create a payment.
     *
     * @param array{
     *     amount: array{currency: string, value: int},
     *     paymentMethod: array{type: string},
     *     reference: string,
     *     returnUrl: string,
     *     userFlow: string,
     *     paymentDescription?: string,
     *     customer?: array{phoneNumber?: string},
     *     receipt?: array{orderLines?: array},
     *     metadata?: array,
     * } $payload The payment creation payload
     * @param string $idempotencyKey Unique key for this request (≤50 chars)
     * @param array{
     *     clientId: string,
     *     clientSecret: string,
     *     subscriptionKey: string,
     *     msn: string,
     *     testMode: bool,
     * } $credentials Gateway credentials
     *
     * @return array{redirectUrl: string, reference: string}
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    public function createPayment(array $payload, string $idempotencyKey, array $credentials): array
    {
        return $this->_post(
            self::EPAYMENT_PATH . '/payments',
            $payload,
            $credentials,
            $idempotencyKey,
        );
    }

    /**
     * Get payment details.
     *
     * @param string $reference The payment reference (8-64 chars)
     * @param array $credentials Gateway credentials
     *
     * @return array{
     *     aggregate: array{
     *         authorizedAmount: array{currency: string, value: int},
     *         cancelledAmount: array{currency: string, value: int},
     *         capturedAmount: array{currency: string, value: int},
     *         refundedAmount: array{currency: string, value: int},
     *     },
     *     amount: array{currency: string, value: int},
     *     state: string,
     *     paymentMethod: array{type: string},
     *     reference: string,
     *     pspReference: string,
     * }
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    public function getPayment(string $reference, array $credentials): array
    {
        return $this->_get(
            self::EPAYMENT_PATH . '/payments/' . $reference,
            $credentials,
        );
    }

    /**
     * Get payment event log.
     *
     * @param string $reference The payment reference
     * @param array $credentials Gateway credentials
     *
     * @return array<int, array{
     *     reference: string,
     *     pspReference: string,
     *     name: string,
     *     amount: array{currency: string, value: int},
     *     timestamp: string,
     *     success: bool,
     * }>
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    public function getPaymentEvents(string $reference, array $credentials): array
    {
        return $this->_get(
            self::EPAYMENT_PATH . '/payments/' . $reference . '/events',
            $credentials,
        );
    }

    /**
     * Capture a payment (full or partial).
     *
     * @param string $reference The payment reference
     * @param array{currency: string, value: int} $amount The amount to capture in minor units
     * @param string $idempotencyKey Unique key for this request
     * @param array $credentials Gateway credentials
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    public function capturePayment(string $reference, array $amount, string $idempotencyKey, array $credentials): array
    {
        return $this->_post(
            self::EPAYMENT_PATH . '/payments/' . $reference . '/capture',
            ['modificationAmount' => $amount],
            $credentials,
            $idempotencyKey,
        );
    }

    /**
     * Refund a payment (full or partial).
     *
     * @param string $reference The payment reference
     * @param array{currency: string, value: int} $amount The amount to refund in minor units
     * @param string $idempotencyKey Unique key for this request
     * @param array $credentials Gateway credentials
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    public function refundPayment(string $reference, array $amount, string $idempotencyKey, array $credentials): array
    {
        return $this->_post(
            self::EPAYMENT_PATH . '/payments/' . $reference . '/refund',
            ['modificationAmount' => $amount],
            $credentials,
            $idempotencyKey,
        );
    }

    /**
     * Cancel a payment.
     *
     * @param string $reference The payment reference
     * @param array $credentials Gateway credentials
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    public function cancelPayment(string $reference, array $credentials): array
    {
        return $this->_post(
            self::EPAYMENT_PATH . '/payments/' . $reference . '/cancel',
            [],
            $credentials,
        );
    }

    /**
     * Force approve a payment (test environment only).
     *
     * @param string $reference The payment reference
     * @param string $phoneNumber Customer phone number
     * @param array $credentials Gateway credentials
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    public function forceApprovePayment(string $reference, string $phoneNumber, array $credentials): array
    {
        if (!($credentials['testMode'] ?? false)) {
            throw new VippsApiException('forceApprovePayment is only available in test mode', 400);
        }

        return $this->_post(
            self::EPAYMENT_PATH . '/test/payments/' . $reference . '/approve',
            ['customer' => ['phoneNumber' => $phoneNumber]],
            $credentials,
        );
    }

    /**
     * Make a GET request to the Vipps API.
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    private function _get(string $path, array $credentials): array
    {
        return $this->_request('GET', $path, null, $credentials);
    }

    /**
     * Make a POST request to the Vipps API.
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    private function _post(string $path, array $data, array $credentials, ?string $idempotencyKey = null): array
    {
        return $this->_request('POST', $path, $data, $credentials, $idempotencyKey);
    }

    /**
     * Execute an HTTP request against the Vipps API.
     *
     * Handles authentication, headers, logging, and error mapping.
     * On 401, invalidates the cached token and retries once.
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    private function _request(
        string $method,
        string $path,
        ?array $data,
        array $credentials,
        ?string $idempotencyKey = null,
        bool $isRetry = false,
    ): array {
        $testMode = $credentials['testMode'] ?? false;
        $baseUrl = $testMode ? self::TEST_BASE_URL : self::PRODUCTION_BASE_URL;

        $tokenManager = $this->_getTokenManager();
        $accessToken = $tokenManager->getAccessToken(
            $credentials['clientId'],
            $credentials['clientSecret'],
            $credentials['subscriptionKey'],
            $credentials['msn'],
            $testMode,
        );

        $headers = $this->_buildHeaders($accessToken, $credentials, $idempotencyKey);

        $client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 30,
        ]);

        $options = ['headers' => $headers];
        if ($data !== null) {
            $options['json'] = $data;
        }

        Craft::info(
            sprintf('Vipps API %s %s', $method, $path),
            __METHOD__,
        );

        try {
            $response = $client->request($method, $path, $options);
        } catch (GuzzleException $e) {
            return $this->_handleRequestError($e, $method, $path, $data, $credentials, $idempotencyKey, $isRetry);
        }

        $body = (string) $response->getBody();
        $decoded = Json::decodeIfJson($body);

        if (!is_array($decoded)) {
            // Some endpoints return empty body on success (e.g., force approve)
            return [];
        }

        return $decoded;
    }

    /**
     * Handle a failed HTTP request.
     *
     * On 401 (unauthorized), invalidates the token cache and retries once.
     * All other errors are thrown as VippsApiException.
     *
     * @throws VippsApiException
     * @throws VippsAuthenticationException
     */
    private function _handleRequestError(
        GuzzleException $e,
        string $method,
        string $path,
        ?array $data,
        array $credentials,
        ?string $idempotencyKey,
        bool $isRetry,
    ): array {
        $statusCode = 0;
        $responseBody = null;

        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $responseBody = Json::decodeIfJson((string) $response->getBody());
            if (!is_array($responseBody)) {
                $responseBody = null;
            }
        }

        // On 401, invalidate token and retry once
        if ($statusCode === 401 && !$isRetry) {
            Craft::warning('Vipps API returned 401, invalidating token and retrying', __METHOD__);

            $this->_getTokenManager()->invalidateToken(
                $credentials['clientId'],
                $credentials['msn'],
            );

            return $this->_request($method, $path, $data, $credentials, $idempotencyKey, true);
        }

        $errorMessage = $this->_formatErrorMessage($method, $path, $statusCode, $responseBody, $e);

        Craft::error($errorMessage, __METHOD__);

        throw new VippsApiException(
            $errorMessage,
            $statusCode,
            $responseBody,
            $e,
        );
    }

    /**
     * Build the required headers for a Vipps API request.
     */
    private function _buildHeaders(string $accessToken, array $credentials, ?string $idempotencyKey): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'Ocp-Apim-Subscription-Key' => $credentials['subscriptionKey'],
            'Merchant-Serial-Number' => $credentials['msn'],
            'Vipps-System-Name' => 'craft-commerce',
            'Vipps-System-Version' => $this->_getCommerceVersion(),
            'Vipps-System-Plugin-Name' => 'craft-vipps',
            'Vipps-System-Plugin-Version' => $this->_getPluginVersion(),
        ];

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    /**
     * Format a human-readable error message from an API failure.
     */
    private function _formatErrorMessage(
        string $method,
        string $path,
        int $statusCode,
        ?array $responseBody,
        \Throwable $e,
    ): string {
        $parts = [
            sprintf('Vipps API %s %s failed', $method, $path),
            sprintf('HTTP %d', $statusCode),
        ];

        if ($responseBody !== null) {
            // Vipps error responses have a 'title' and 'detail' field
            $title = $responseBody['title'] ?? null;
            $detail = $responseBody['detail'] ?? null;

            if ($title) {
                $parts[] = $title;
            }
            if ($detail) {
                $parts[] = $detail;
            }
        }

        if (empty($responseBody)) {
            $parts[] = $e->getMessage();
        }

        return implode(' — ', $parts);
    }

    private function _getTokenManager(): TokenManager
    {
        /** @var TokenManager */
        return Vipps::getInstance()->get('tokenManager');
    }

    private function _getCommerceVersion(): string
    {
        $commerce = CommercePlugin::getInstance();

        return $commerce ? $commerce->getVersion() : 'unknown';
    }

    private function _getPluginVersion(): string
    {
        $plugin = Vipps::getInstance();

        return $plugin ? $plugin->getVersion() : 'unknown';
    }
}
