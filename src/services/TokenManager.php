<?php

declare(strict_types=1);

namespace superbig\vipps\services;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use superbig\vipps\exceptions\VippsAuthenticationException;

/**
 * Manages Vipps API access tokens with caching and automatic refresh.
 *
 * Tokens are cached using Craft's cache component. A 5-minute buffer
 * ensures tokens are refreshed before they actually expire, preventing
 * race conditions where a token expires mid-request.
 *
 * Production tokens last 24 hours, test tokens last 1 hour.
 */
class TokenManager extends Component
{
    /**
     * Buffer in seconds before token expiry to trigger refresh.
     * Prevents using a token that's about to expire mid-request.
     */
    public const EXPIRY_BUFFER_SECONDS = 300;

    private const CACHE_KEY_PREFIX = 'vipps_access_token_';

    private const PRODUCTION_BASE_URL = 'https://api.vipps.no';

    private const TEST_BASE_URL = 'https://apitest.vipps.no';

    private const TOKEN_ENDPOINT = '/accesstoken/get';

    /**
     * Get a valid access token for the given gateway credentials.
     *
     * Returns a cached token if still valid (with buffer), otherwise
     * fetches a new one from the Vipps API.
     *
     * @param string $clientId The client ID (from Vipps portal)
     * @param string $clientSecret The client secret (from Vipps portal)
     * @param string $subscriptionKey The Ocp-Apim-Subscription-Key
     * @param string $msn The Merchant Serial Number
     * @param bool $testMode Whether to use the test environment
     *
     * @throws VippsAuthenticationException If token fetch fails
     */
    public function getAccessToken(
        string $clientId,
        string $clientSecret,
        string $subscriptionKey,
        string $msn,
        bool $testMode = false,
    ): string {
        $cacheKey = $this->_getCacheKey($clientId, $msn);

        $cached = Craft::$app->getCache()->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        return $this->_fetchAndCacheToken(
            $clientId,
            $clientSecret,
            $subscriptionKey,
            $msn,
            $testMode,
        );
    }

    /**
     * Invalidate the cached token for the given credentials.
     * Use this when a request fails with 401 to force a refresh.
     */
    public function invalidateToken(string $clientId, string $msn): void
    {
        $cacheKey = $this->_getCacheKey($clientId, $msn);
        Craft::$app->getCache()->delete($cacheKey);
    }

    /**
     * Fetch a new token from the Vipps API and cache it.
     *
     * @throws VippsAuthenticationException
     */
    private function _fetchAndCacheToken(
        string $clientId,
        string $clientSecret,
        string $subscriptionKey,
        string $msn,
        bool $testMode,
    ): string {
        $baseUrl = $testMode ? self::TEST_BASE_URL : self::PRODUCTION_BASE_URL;

        $client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => 10,
        ]);

        try {
            $response = $client->post(self::TOKEN_ENDPOINT, [
                'headers' => [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'Ocp-Apim-Subscription-Key' => $subscriptionKey,
                    'Merchant-Serial-Number' => $msn,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new VippsAuthenticationException(
                'Failed to fetch Vipps access token: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e,
            );
        }

        $body = (string) $response->getBody();
        $data = Json::decodeIfJson($body);

        if (!is_array($data) || !isset($data['access_token'])) {
            throw new VippsAuthenticationException(
                'Invalid token response from Vipps API: missing access_token',
            );
        }

        $accessToken = $data['access_token'];
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        // Cache with buffer so we refresh before actual expiry
        $cacheDuration = max(0, $expiresIn - self::EXPIRY_BUFFER_SECONDS);

        $cacheKey = $this->_getCacheKey($clientId, $msn);
        Craft::$app->getCache()->set($cacheKey, $accessToken, $cacheDuration);

        Craft::info(
            sprintf('Vipps access token fetched, expires in %ds (cached for %ds)', $expiresIn, $cacheDuration),
            __METHOD__,
        );

        return $accessToken;
    }

    /**
     * Generate a unique cache key per gateway credentials.
     * Different MSNs (sales units) get different tokens.
     */
    private function _getCacheKey(string $clientId, string $msn): string
    {
        return self::CACHE_KEY_PREFIX . md5($clientId . ':' . $msn);
    }
}
