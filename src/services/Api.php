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

use craft\helpers\Json;
use craft\helpers\Template;
use craft\helpers\UrlHelper;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use function GuzzleHttp\Psr7\build_query;
use superbig\vipps\Vipps;

use Craft;
use craft\base\Component;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class Api extends Component
{
    const ENDPOINT      = 'https://api.vipps.no';
    const TEST_ENDPOINT = 'https://apitest.vipps.no';

    private $_client;
    private $_accessToken;

    // Public Methods
    // =========================================================================

    public function init()
    {
        // Set initial token
        $this->_getAccessToken();
    }

    /**
     * @return array|null
     */
    public function getAccessTokenHeader()
    {
        $token = $this->_accessToken;

        if (!$token) {
            return null;
        }

        return [
            'Authorization'             => 'Bearer ' . $token,
            'ocp-apim-subscription-key' => Vipps::$plugin->getSettings()->subscriptionKeyEcommerce,
        ];
    }

    public function getApiUrl(): string
    {
        $testMode = Vipps::$plugin->getPayments()->getGateway()->testMode;

        return $testMode ? self::TEST_ENDPOINT : self::ENDPOINT;
    }

    // Public Methods
    // =========================================================================

    /**
     * @param string $url
     * @param array  $query
     *
     * @return array|null
     */
    public function get($url = '', $query = [])
    {
        try {
            $client   = $this->getClient();
            $response = $client->get($url, [
                'headers' => $this->_getDefaultHeaders(),
                'query'   => build_query($query),
            ]);
            $body     = (string)$response->getBody();
            $json     = Json::decodeIfJson($body);


            return $json;
        } catch (BadResponseException $e) {
            $responseBody = (string)$e->getResponse()->getBody();
            $json         = Json::decodeIfJson($responseBody);
            $this->_logException($e);

            return $json;
        } catch (\Exception $e) {
            $this->_logException($e);

            return null;
        }
    }

    /**
     * @param string $url
     * @param array  $query
     *
     * @return array|null
     */
    public function post($url = '', $data = [])
    {
        try {
            $client   = $this->getClient();
            $response = $client->post($url, [
                'headers' => $this->_getDefaultHeaders(),
                'json'    => $data,
            ]);
            $body     = (string)$response->getBody();
            $json     = Json::decodeIfJson($body);

            return $json;
        } catch (BadResponseException $e) {
            $responseBody = (string)$e->getResponse()->getBody();
            $json         = Json::decodeIfJson($responseBody);

            $this->_logException($e);

            return $json;
        } catch (\Exception $e) {
            $this->_logException($e);

            return null;
        }
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        if (!$this->_client) {
            $this->_client = new Client([
                'base_uri' => $this->getApiUrl(),
                'headers'  => $this->_getDefaultHeaders(),
            ]);
        }

        return $this->_client;
    }

    // Private Methods
    // =========================================================================


    private function _getAccessToken()
    {
        if (!$this->_accessToken) {
            $url                = 'accessToken/get';
            $response           = $this->post($url, []);
            $this->_accessToken = $response['access_token'] ?? null;
        }

        return $this->_accessToken;
    }

    private function _logException(\Exception $e)
    {
        if ($e instanceof BadResponseException) {
            $url          = $e->getRequest()->getUri();
            $method       = $e->getRequest()->getMethod();
            $responseBody = (string)$e->getResponse()->getBody();
            $json         = Json::decodeIfJson($responseBody);

            $error = Craft::t(
                'vipps',
                "API call failed for {method} {url}: {message} @ {file}:{line}. \n{stacktrace}",
                [
                    'url'        => $url,
                    'method'     => $method,
                    'message'    => $e->getMessage(),
                    'file'       => $e->getFile(),
                    'line'       => $e->getLine(),
                    'stacktrace' => $e->getTraceAsString(),
                    'response'   => print_r($json, true),
                ]
            );
        }
        else {
            $error = Craft::t(
                'vipps',
                "API call failed: {message} @ {file}:{line}. \n{stacktrace}",
                [
                    'message'    => $e->getMessage(),
                    'file'       => $e->getFile(),
                    'line'       => $e->getLine(),
                    'stacktrace' => $e->getTraceAsString(),
                ]
            );
        }


        Craft::warning($error, 'vipps');
    }

    private function _getDefaultHeaders(): array
    {
        $date     = gmdate('c');
        $ip       = $_SERVER['SERVER_ADDR'];
        $settings = Vipps::$plugin->getPayments()->getGateway();
        $headers  = [
            'content-type'              => 'application/json',
            'X-Request-Id'              => $requestId = 1,
            'X-TimeStamp'               => $date,
            'X-Source-Address'          => $ip,
            'cache-control'             => 'no-cache',
            'ocp-apim-subscription-key' => $settings->subscriptionKeyAccessToken,
            'client_id'                 => $settings->clientId,
            'client_secret'             => $settings->clientSecret,
        ];

        if ($tokenHeader = $this->getAccessTokenHeader()) {
            $headers = array_merge($headers, $tokenHeader);
        }

        return $headers;
    }
}