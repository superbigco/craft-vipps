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

use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\TransactionException;
use craft\commerce\models\Address;
use craft\commerce\models\ShippingMethod;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\helpers\Json;
use superbig\vipps\responses\CallbackResponse;
use superbig\vipps\Vipps;

use Craft;
use craft\web\Controller;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
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
     * @param \yii\base\Action $action
     *
     * @return bool
     * @throws HttpException
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->verifyAuthToken();

        return true;
    }

    /**
     * @param null $orderId
     *
     * @return mixed
     * @throws HttpException
     * @throws \craft\commerce\errors\TransactionException
     */
    public function actionComplete($orderId = null)
    {
        $payload     = $this->getPayload();
        $transaction = Vipps::$plugin->getPayments()->getTransactionByShortId($orderId);

        if (!$transaction) {
            throw new NotFoundHttpException('Could not find transaction.', 401);
        }

        $order = $transaction->getOrder();

        if ($order->getIsPaid()) {
            return $this->asJson([
                'success' => true,
            ]);
        }

        // If it's successful already, we're good.
        if (Plugin::getInstance()->getTransactions()->isTransactionSuccessful($transaction)) {
            return true;
        }

        $response = new CallbackResponse($payload);

        if ($response->isExpress()) {
            $country         = Plugin::getInstance()->getCountries()->getCountryByIso('NO');
            $shippingDetails = $payload['shippingDetails'];
            $addressPayload  = $shippingDetails['address'];
            $address         = new Address([
                'address1'  => $addressPayload['addressLine1'],
                'address2'  => $addressPayload['addressLine2'],
                'city'      => $addressPayload['city'],
                'zipCode'   => $addressPayload['zipCode'],
                'countryId' => $country->id,
            ]);

            $order->setBillingAddress($address);
            $order->setShippingAddress($address);
            $order->shippingMethodHandle = $shippingDetails['shippingMethodId'];

            Craft::$app->getElements()->saveElement($order, false);
        }

        $childTransaction = Plugin::getInstance()->getTransactions()->createTransaction(null, $transaction);
        $this->_updateTransaction($childTransaction, $response);

        // Success can mean 2 things in this context.
        // 1) The transaction completed successfully with the gateway, and is now marked as complete.
        // 2) The result of the gateway request was successful but also got a redirect response. We now need to redirect if $redirect is not null.
        $success = $response->isSuccessful() || $response->isProcessing();

        if ($success && $transaction->status === TransactionRecord::STATUS_SUCCESS) {
            $transaction->order->updateOrderPaidInformation();
        }

        return $this->asJson([
            'success' => true,
        ]);
    }

    /**
     * Save a transaction.
     *
     * @param Transaction $child
     *
     * @throws TransactionException
     */
    private function _saveTransaction($child)
    {
        if (!Plugin::getInstance()->getTransactions()->saveTransaction($child)) {
            throw new TransactionException('Error saving transaction: ' . implode(', ', $child->errors));
        }
    }

    /**
     * Updates a transaction.
     *
     * @param Transaction              $transaction
     * @param RequestResponseInterface $response
     *
     * @throws TransactionException
     */
    private function _updateTransaction(Transaction $transaction, RequestResponseInterface $response)
    {
        if ($response->isRedirect()) {
            $transaction->status = TransactionRecord::STATUS_REDIRECT;
        }
        elseif ($response->isSuccessful()) {
            $transaction->status = TransactionRecord::STATUS_SUCCESS;
        }
        elseif ($response->isProcessing()) {
            $transaction->status = TransactionRecord::STATUS_PROCESSING;
        }
        else {
            $transaction->status = TransactionRecord::STATUS_FAILED;
        }

        $transaction->response  = $response->getData();
        $transaction->code      = $response->getCode();
        $transaction->reference = $response->getTransactionReference();
        $transaction->message   = $response->getMessage();

        $this->_saveTransaction($transaction);
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

        $transaction = Vipps::$plugin->getPayments()->getTransactionByShortId($orderId);

        if (!$transaction) {
            throw new NotFoundHttpException('Could not find transaction.', 401);
        }

        $order         = $transaction->getOrder();
        $addressId     = $payload['addressId'] ?? null;
        $isFirst       = true;
        $currentHandle = $order->shippingMethodHandle;
        // $iso           = $payload['country'];
        $country = Plugin::getInstance()->getCountries()->getCountryByIso('NO');
        $address = new Address([
            'address1'  => $payload['addressLine1'],
            'address2'  => $payload['addressLine2'],
            'city'      => $payload['city'],
            'zipCode'   => $payload['postCode'],
            'countryId' => $country->id,
        ]);

        $order->setBillingAddress($address);
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
            'shippingDetails' => array_values($methods),
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
        //@file_put_contents($path, print_r($payload, true), FILE_APPEND);
        //@file_put_contents($path, print_r($headers, true), FILE_APPEND);
        //@file_put_contents($path, (string)Craft::$app->getRequest()->getRawBody(), FILE_APPEND);

        if (!$payload) {
            throw new HttpException(400, 'Invalid payload');
        }

        return $payload;
    }

    public function verifyAuthToken()
    {

        $token     = Craft::$app->getRequest()->getHeaders()->get('authorization');
        $gateway   = Vipps::$plugin->payments->getGateway();
        $authToken = $gateway->getAuthToken();

        if (!$token || $authToken !== $token) {
            throw new HttpException(400, 'Invalid auth token');
        }

        return true;
    }
}
