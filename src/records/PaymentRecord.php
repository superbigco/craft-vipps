<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\records;

use superbig\vipps\Vipps;

use Craft;
use craft\db\ActiveRecord;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class PaymentRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%vipps_payment}}';
    }
}
