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

use Craft;
use craft\commerce\base\RequestResponseInterface;

use superbig\vipps\Vipps;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class PaymentResponse implements RequestResponseInterface
{
    /**
     * @var
     */
    protected $data = [];
    /**
     * @var string
     */
    private $_redirect = '';
    /**
     * @var bool
     */
    private $_processing = false;

    private $_error;
    private $_code = 200;

    /**
     * Response constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
        $statusCode = $this->data['statusCode'] ?? null;
        $message = $this->data['message'] ?? null;

        if ($statusCode) {
            $this->_code = $statusCode;
        }

        if ($statusCode && $statusCode !== 200) {
            $this->_error = $message;
        }

        if (isset($this->data[0]['errorMessage'])) {
            $this->_error = $this->data[0]['errorMessage'];
        }
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
        if ($this->isRedirect()) {
            return false;
        }

        return !$this->_error;
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
        return !empty($this->_redirect);
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
        if (empty($this->data['orderId'])) {
            return '';
        }

        return (string)$this->data['orderId'];
    }

    /**
     * Returns the response code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->_code;
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
        if ($this->_error) {
            return $this->_error;
        }

        return '';
    }

    /**
     * Perform the redirect.
     *
     * @return mixed
     */
    public function redirect()
    {
        return Craft::$app->getResponse()->redirect($this->_redirect)->send();
    }
}
