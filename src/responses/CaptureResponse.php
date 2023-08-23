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

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class CaptureResponse implements RequestResponseInterface
{
    /**
     * @var array
     */
    protected array $data = [];
    /**
     * @var string
     */
    private string $_redirect = '';
    /**
     * @var bool
     */
    private bool $_processing = false;

    /**
     * Response constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

  
    public function setRedirectUrl(string $url): void
    {
        $this->_redirect = $url;
    }

    public function setProcessing(bool $status): void
    {
        $this->_processing = $status;
    }


    /**
     * Returns whether the payment was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return !isset($this->data[0]['errorGroup']);
    }

    /**
     * Returns whether the payment is being processed by gateway.
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
        if (empty($this->data['transactionInfo'])) {
            return '';
        }

        return $this->data['transactionInfo']['transactionId'];
    }

    /**
     * Returns the response code.
     *
     * @return string
     */
    public function getCode(): string
    {
        if (isset($this->data[0]['errorGroup'])) {
            return $this->data[0]['errorCode'];
        }

        return '200';
    }

    /**
     * Returns the data.
     *
     * @return mixed
     */
    public function getData(): mixed
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
        if (isset($this->data[0]['errorMessage'])) {
            return $this->data[0]['errorMessage'];
        }

        return $this->data['transactionInfo']['transactionText'] ?? '';
    }

    /**
     * Perform the redirect.
     *
     * @return void
     */
    public function redirect(): void
    {
    }
}
