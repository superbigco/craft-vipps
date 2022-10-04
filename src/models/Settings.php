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
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $clientId = '';
    public $clientSecret = '';
    public $subscriptionKeyAccessToken = '';
    public $subscriptionKeyEcommerce = '';
    public $merchantSerialNumber = '';
    public $transactionText = '';
    public $testMode = false;
    public $expressCheckout = true;
    public $createUserOnExpressCheckout = true;
    public $loginWithVipps = false;
    public $addItemToCartIfAlreadyExists = false;
    public $newCartOnExpressCheckout = true;

    // Public Methods
    // =========================================================================


    public function rules()
    {
        return [
            [['merchantSerialNumber', 'transactionText'], 'string'],
            [['merchantSerialNumber'], 'required'],
            ['transactionText', 'default', 'value' => 'Order #{number}'],
        ];
    }
}
