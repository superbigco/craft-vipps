<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\models;

use craft\base\Model;

use superbig\vipps\Vipps;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class ErrorModel extends Model
{
    /**
     * @var string
     */
    public string $errorCode = '';

    /**
     * @var string
     */
    public string $errorMessage = '';

    /**
     * @var string
     */
    public string $errorGroup = '';

    /**
     * @var int
     */
    public int $orderId;

    // Public Methods
    // =========================================================================

    public function getErrorGroupLabel(): void
    {
        $groups = [
            'Authentication' => 'Authentication', // Authentication Failure because of wrong token provided
            'Payment' => 'Payment', // Failure while doing a payment Authorization, mostly because of PSP errors
            'InvalidRequest' => 'InvalidRequest', // Request contains invalid parameters
            'VippsError' => 'VippsError', // Internal Vipps application error
            'Customer' => 'Customer', // Error raised because of Vipps user (Example: User not registered with Vipps ....)
            'Merchant' => 'Merchant', // Errors regarding the merchant
        ];
    }


    public function rules(): array
    {
        return [
            [['shortId', 'orderId'], 'required'],
        ];
    }
}
