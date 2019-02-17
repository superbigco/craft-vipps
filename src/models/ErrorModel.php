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

use superbig\vipps\Vipps;

use Craft;
use craft\base\Model;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class ErrorModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $errorCode = '';

    /**
     * @var string
     */
    public $errorMessage = '';

    /**
     * @var string
     */
    public $errorGroup = '';

    /**
     * @var int
     */
    public $orderId;

    // Public Methods
    // =========================================================================

    public function getErrorGroupLabel()
    {
        $groups = [
            'Authentication' => 'Authentication', // Authentication Failure because of wrong token provided
            'Payment'        => 'Payment', // Failure while doing a payment Authorization, mostly because of PSP errors
            'InvalidRequest' => 'InvalidRequest', // Request contains invalid parameters
            'VippsError' => 'VippsError', // Internal Vipps application error
            'Customer' => 'Customer', // Error raised because of Vipps user (Example: User not registered with Vipps ....
            'Merchant' => 'Merchant', // Errors regarding the merchant
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['shortId', 'orderId'], 'required'],
        ];
    }
}
