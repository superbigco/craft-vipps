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
class PaymentModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $shortId = '';

    /**
     * @var int
     */
    public $orderId;

    // Public Methods
    // =========================================================================

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
