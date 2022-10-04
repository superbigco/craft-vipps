<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\responses;

use craft\commerce\base\RequestResponseInterface;
use craft\helpers\Json;
use GuzzleHttp\Exception\BadResponseException;
use superbig\vipps\Vipps;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 *
 * @property BadResponseException $exception
 * @property array                $data
 * @property string               $_redirect
 * @property bool                 $_processing
 */
class ErrorResponse implements RequestResponseInterface
{
    protected $exception;
    private $orderId = '';
    protected $data = [];
    private $_redirect = '';
    private $_processing = false;

    /**
     * Response constructor.
     *
     * @param BadResponseException $exception
     */
    public function __construct(BadResponseException $exception, $orderId = '')
    {
        $this->exception = $exception;
        $this->orderId = $orderId;
        $this->data = $exception->hasResponse() ? Json::decodeIfJson((string)$exception->getResponse()->getBody()) : [];
    }

    // Public Properties
    // =========================================================================

    public function setRedirectUrl(string $url)
    {
        $this->_redirect = $url;
    }

    public function setProcessing(bool $status)
    {
        $this->_processing = $status;
    }


    /**
     * Returns whether or not the payment was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return false;
    }

    /**
     * Returns whether or not the payment is being processed by gateway.
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->_processing;
    }


    public function isRedirect(): bool
    {
        return false;
    }


    public function getRedirectMethod(): string
    {
        return 'GET';
    }


    /**
     * Returns the redirect data provided.
     *
     * @return array
     */
    public function getRedirectData(): array
    {
        return [];
    }

    /**
     * Returns the redirect URL to use, if any.
     *
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return $this->_redirect;
    }

    /**
     * Returns the transaction reference.
     *
     * @return string
     */
    public function getTransactionReference(): string
    {
        return $this->orderId;
    }

    /**
     * Returns the response code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->exception->getCode();
    }

    /**
     * Returns the data.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns the gateway message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->data['errorMessage'] ?? '';
    }

    /**
     * Perform the redirect.
     *
     * @return mixed
     */
    public function redirect()
    {
        return false;
    }
}
