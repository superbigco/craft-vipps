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
class DefaultController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['index', 'do-something'];

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionPaymentStatus()
    {
        $orderId = Craft::$app->getRequest()->getParam('orderId', '8728377');

        return $this->asJson([
            'status' => Vipps::$plugin->payments->paymentStatus($orderId),
        ]);
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
