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

use craft\helpers\Template;
use craft\helpers\UrlHelper;
use Exception;
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

    public function getHeaders(): array
    {
        $date    = gmdate('c');
        $ip      = $_SERVER['SERVER_ADDR'];
        $at      = '';
        $subKey  = 'Ocp_Apim_Key_eCommerce';
        $merch   = Vipps::$plugin->getSettings()->merchantSerialNumber;
        $headers = [
            'Authorization'             => 'Bearer ' . $at,
            'X-Request-Id'              => $requestId = 1,
            'X-TimeStamp'               => $date,
            'X-Source-Address'          => $ip,
            'Ocp-Apim-Subscription-Key' => $subKey,

        ];
    }

    public function getApiUrl(): string
    {
        $testMode = Vipps::$plugin->getSettings()->testMode;

        return $testMode ? 'https://apitest.vipps.no' : 'https://api.vipps.no';
    }

    // Fetch an access token if possible from the Vipps Api IOK 2018-04-18
    private function getAccessToken()
    {
        $clientid = $this->get_option('clientId');
        $secret   = $this->get_option('secret');
        $at       = $this->get_option('Ocp_Apim_Key_AccessToken');
        $command  = 'accessToken/get';
        try {
            $result = $this->http_call($command, [], 'POST', ['client_id' => $clientid, 'client_secret' => $secret, 'Ocp-Apim-Subscription-Key' => $at], 'url');

            return $result;
        } catch (TemporaryVippsAPIException $e) {
            $this->log(__("Could not get Vipps access token", 'woo-vipps') . ' ' . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            $this->log(__("Could not get Vipps access token", 'woo-vipps') . ' ' . $e->getMessage());
            throw new VippsAPIConfigurationException($e->getMessage());
        }
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