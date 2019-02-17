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

use craft\commerce\models\Address;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\helpers\Json;
use superbig\vipps\Vipps;

use Craft;
use craft\web\Controller;
use yii\web\HttpException;
use yii\web\Response;

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
    protected $allowAnonymous = ['complete', 'shipping-details', 'consent', 'process-webhook'];


    // Public Properties
    // =========================================================================

    /**
     * @inheritdoc
     */
    public $enableCsrfValidation = false;

    // Public Methods
    // =========================================================================

    /**
     * @return mixed
     */
    public function actionComplete($orderId = null)
    {
        $payload = $this->getPayload();
        $order   = Plugin::getInstance()->getOrders()->getOrderByNumber($orderId) ?? Plugin::getInstance()->getOrders()->getOrderById($orderId);

        return $this->asJson([
            'success' => true,
        ]);
    }

    /**
     * @param string|null $userId
     *
     * @return mixed
     */
    public function actionConsentRemoval(string $userId = null)
    {
        // Delete customer details

        return $this->asJson([
            'success' => true,
        ]);
    }

    /**
     * @param string|null $orderId
     *
     * @return mixed
     * @throws HttpException
     */
    public function actionShippingDetails(string $orderId = null)
    {
        $payload = $this->getPayload();

        $order = Plugin::getInstance()->getOrders()->getOrderByNumber($orderId) ?? Plugin::getInstance()->getOrders()->getOrderById($orderId);
        /*$address = new Address([
            'firstName' => $payload['firstName'],
            'lastName'  => $payload['lastName'],
            'address1'  => $payload['address1'],
            'address2'  => $payload['address2'],
        ]);*/
        $addressId     = $payload['addressId'] ?? null;
        $isFirst       = true;
        $currentHandle = $order->shippingMethodHandle;
        $country       = Plugin::getInstance()->getCountries()->getCountryByIso($payload['country']);
        $address       = new Address([
            'address1'  => $payload['addressLine1'],
            'address2'  => $payload['addressLine2'],
            'city'      => $payload['city'],
            'zipCode'   => $payload['postCode'],
            'countryId' => $country->id,
        ]);

        $order->setShippingAddress($address);

        $methods = array_map(function(ShippingMethod $method) use ($order, &$isFirst, $currentHandle) {
            $price     = (string)$method->getPriceForOrder($order);
            $isDefault = 'N';

            if ((!$currentHandle && $isFirst) || $currentHandle === $method->getHandle()) {
                $isDefault = 'Y';
            }

            return [
                'isDefault'        => $isDefault,
                // 'priority'         => '0',
                'shippingCost'     => $price,
                'shippingMethod'   => $method->getName(),
                'shippingMethodId' => $method->getHandle(),
            ];
        }, $order->getAvailableShippingMethods());

        // Send something back if no shipping is required/no results is returned
        if (empty($methods)) {
            $methods = [
                [
                    'isDefault'        => 'Y',
                    'priority'         => '0',
                    'shippingCost'     => '0.00',
                    'shippingMethod'   => Craft::t('vipps', 'No shipping required'),
                    'shippingMethodId' => 'Free',
                ],
            ];
        }

        $result = [
            'addressId'       => \intval($addressId),
            'orderId'         => $orderId,
            'shippingDetails' => $methods,
        ];

        return $this->asJson($result);
    }

    /**
     * @return Response
     * @throws HttpException If webhook not expected.
     */
    public function actionProcessWebhook(): Response
    {
        $gatewayId = Craft::$app->getRequest()->getRequiredParam('gateway');
        $gateway   = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

        $response = null;

        try {
            if ($gateway && $gateway->supportsWebhooks()) {
                $response = $gateway->processWebHook();
            }
        } catch (\Throwable $exception) {
            $message = 'Exception while processing webhook: ' . $exception->getMessage() . "\n";
            $message .= 'Exception thrown in ' . $exception->getFile() . ':' . $exception->getLine() . "\n";
            $message .= 'Stack trace:' . "\n" . $exception->getTraceAsString();

            Craft::error($message, 'commerce');

            $response = Craft::$app->getResponse();
            $response->setStatusCodeByException($exception);
        }

        return $response;
    }

    public function getPayload()
    {
        $payload = Json::decodeIfJson((string)Craft::$app->getRequest()->getRawBody());
        $headers = Craft::$app->getRequest()->getHeaders();

        $path = Craft::$app->getPath()->getStoragePath() . '/vipps.txt';
        @file_put_contents($path, print_r($payload, true), FILE_APPEND);
        @file_put_contents($path, print_r($headers, true), FILE_APPEND);
        //@file_put_contents($path, (string)Craft::$app->getRequest()->getRawBody(), FILE_APPEND);

        if (!$payload) {
            throw new HttpException(400, 'Invalid payload');
        }

        return $payload;
    }
}
