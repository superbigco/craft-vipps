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
class Settings extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $clientId                    = '';
    public $clientSecret                = '';
    public $subscriptionKeyAccessToken  = '';
    public $subscriptionKeyEcommerce    = '';
    public $merchantSerialNumber        = '';
    public $transactionText             = '';
    public $testMode                    = false;
    public $expressCheckout             = true;
    public $createUserOnExpressCheckout = true;
    public $loginWithVipps              = false;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['merchantSerialNumber', 'transactionText'], 'string'],
            [['merchantSerialNumber'], 'required'],
            ['transactionText', 'default', 'value' => 'Order #{number}'],
        ];
    }
}
