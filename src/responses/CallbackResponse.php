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
use craft\helpers\ArrayHelper;
use superbig\vipps\Vipps;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class CallbackResponse implements RequestResponseInterface
{
    const STATUS_SALE = 'SALE';
    const STATUS_RESERVE = 'RESERVE';
    const STATUS_RESERVED = 'RESERVED';
    const STATUS_RESERVE_FAILED = 'RESERVE_FAILED';
    const STATUS_SALE_FAILED = 'SALE_FAILED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_REJECTED = 'REJECTED';

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

    /**
     * Response constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
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
     * @throws \Exception
     */
    public function isSuccessful(): bool
    {
        $error = ArrayHelper::getValue($this->data, 'errorInfo') || ArrayHelper::getValue($this->data, 'callbackErrorInfo');
        $status = ArrayHelper::getValue($this->data, 'transactionInfo.status');

        return !$error &&
            \in_array($status, [
                self::STATUS_RESERVE,
                self::STATUS_RESERVED,
                self::STATUS_SALE,
            ]);
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

    /**
     * Returns whether or not this is a Express order
     *
     * @return bool
     */
    public function isExpress(): bool
    {
        return isset($this->data['userDetails']) && isset($this->data['shippingDetails']);
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
        if (empty($this->data)) {
            return '';
        }

        return (string)$this->data['transactionInfo']['transactionId'] ?? '';
    }

    /**
     * Returns the response code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->data['callbackErrorInfo']['errorCode'] ?? '200';
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
        return $this->data['callbackErrorInfo']['errorMessage'] ?? '';
    }

    /**
     * Returns the users email (only in Express callback)
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->data['userDetails']['email'] ?? null;
    }

    /**
     * Returns paid amount for this transaction
     *
     * @param bool $convert
     *
     * @return int
     */
    public function getAmount($convert = true): int
    {
        $amount = ArrayHelper::getValue($this->data, 'transactionInfo.amount', 0);

        if ($convert) {
            // @todo Use helper method for this
            $amount = $amount / 100;
        }

        return $amount;
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
