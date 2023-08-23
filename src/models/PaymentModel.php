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
class PaymentModel extends Model
{
    /**
     * @var string
     */
    public string $shortId = '';

    /**
     * @var int
     */
    public int $orderId;

    /**
     * @var int
     */
    public int $transactionReference;

    // Public Methods
    // =========================================================================


    public function rules(): array
    {
        return [
            [['shortId', 'orderId', 'transactionReference'], 'required'],
        ];
    }
}
