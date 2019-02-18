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
    // Public Methods
    // =========================================================================

    public function init()
    {
        // Set initial token
        $this->getAccessToken();
    }

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
        $testMode = Vipps::$plugin->getSettings()->testMode;

        return $testMode ? 'https://apitest.vipps.no' : 'https://api.vipps.no';
    }

    private function getAccessToken()
    {
        if (!$this->_accessToken) {
            $url                = 'accessToken/get';
            $response           = $this->post($url, []);
            $this->_accessToken = $response['access_token'] ?? null;
        }

        return $this->_accessToken;
    }

    // Public Methods
    // =========================================================================

    const ENDPOINT      = 'https://api.vipps.no';
    const TEST_ENDPOINT = 'https://apitest.vipps.no';

    private $_client;
    private $_accessToken;

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
                'headers' => $this->getDefaultHeaders(),
                'query'   => build_query($query),
            ]);
            $body     = (string)$response->getBody();
            $json     = Json::decodeIfJson($body);

            //dd($url, $query, $json);

            //if (!empty($query)) {
            // $request->getQuery()->set()
            // }

            // Cache the response
            //craft()->fileCache->set($url, $json);
            // Apply the limit and offset
            //$items = array_slice($items, $offset, $limit);


            return $json;
        } catch (BadResponseException $e) {
            $requestBody  = (string)$e->getRequest()->getBody();
            $responseBody = (string)$e->getResponse()->getBody();
            dd([
                'url'      => $url,
                'error'    => $e->getMessage(),
                'query'    => $query,
                'request'  => Json::decodeIfJson($requestBody),
                'response' => Json::decodeIfJson($responseBody),
            ]);

            return null;
        } catch (\Exception $e) {
            dd([
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

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
                'headers' => $this->getDefaultHeaders(),
                'json'    => $data,
            ]);
            $body     = (string)$response->getBody();
            $json     = Json::decodeIfJson($body);

            return $json;
        } catch (BadResponseException $e) {
            $requestBody  = (string)$e->getRequest()->getBody();
            $responseBody = (string)$e->getResponse()->getBody();
            $json     = Json::decodeIfJson($responseBody);

            dd([
                'url'      => $url,
                'error'    => $e->getMessage(),
                'headers'  => $e->getRequest()->getHeaders(),
                'request'  => Json::decodeIfJson($requestBody),
                'response' => Json::decodeIfJson($responseBody),
            ]);

            return $json;
        } catch (\Exception $e) {
            dd([
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

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
                'headers'  => $this->getDefaultHeaders(),
            ]);
        }

        return $this->_client;
    }

    // Protected Methods
    // =========================================================================

    private function getDefaultHeaders(): array
    {
        $date     = gmdate('c');
        $ip       = $_SERVER['SERVER_ADDR'];
        $settings = Vipps::$plugin->getSettings();
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
            //unset($headers['client_id']);
            //unset($headers['client_secret']);
        }

        return $headers;
    }
}

class VippsAPIException extends Exception
{
    public $responsecode = null;
}

// This is for 502 bad gateway, timeouts and other errors we can expect to recover from
class TemporaryVippsAPIException extends VippsAPIException
{
}

// This is for non-temporary problems with the keys and so forth
class VippsAPIConfigurationException extends VippsAPIException
{
}