<?php
/**
 * Vipps plugin for Craft CMS 3.x
 *
 * Integrate Commerce with Vipps
 *
 * @link      https://superbig.co
 * @copyright Copyright (c) 2018 Superbig
 */

namespace superbig\vipps\controllers;

use superbig\vipps\Vipps;

use Craft;
use craft\web\Controller;

/**
 * @author    Superbig
 * @package   Vipps
 * @since     1.0.0
 */
class CallbackController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['shipping-details', 'do-something'];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionIndex()
    {
        // Send something back if no shipping is required/no results is returned
        $methods = [
            ['isDefault' => 'Y', 'priority' => '0', 'shippingCost' => '0.00', 'shippingMethod' => Craft::t('vipps', 'No shipping required'), 'shippingMethodId' => 'Free:Free;0'],
        ];
        $result  = ['addressId' => intval($addressId), 'orderId' => $vippsOrder, 'shippingDetails' => $methods];

        return $result;
    }

    /**
     * @return mixed
     */
    public function actionDoSomething()
    {
        $result = 'Welcome to the DefaultController actionDoSomething() method';

        return $result;
    }
}
