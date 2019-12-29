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
use superbig\vipps\helpers\Currency;
use superbig\vipps\helpers\LogToFile;
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
     * @throws NotFoundHttpException
     * @throws TransactionException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function actionComplete($orderId = null)
    {
        $payload     = $this->getPayload();
        $transaction = Vipps::$plugin->getPayments()->getTransactionByShortId($orderId);

        if (!$transaction) {
            LogToFile::error("Could not find transaction {$orderId}");

            throw new NotFoundHttpException('Could not find transaction.', 401);
        }

        $order = $transaction->getOrder();

        if ($order->getIsPaid()) {
            LogToFile::info("Skipping {$orderId} because order already is paid");

            return $this->asJson([
                'success' => true,
            ]);
        }

        // @todo Check if total amount is less than x NOK? Or if there is earlier successful authorization attempts
        // If it's successful already, we're good.
        if (Plugin::getInstance()->getTransactions()->isTransactionSuccessful($transaction)) {
            LogToFile::info('Skipping because there already is a successful transactions');

            return true;
        }

        $response = new CallbackResponse($payload);

        if ($response->isExpress()) {
            // @todo This is hardcoded while Vipps only support Norway
            $country         = Plugin::getInstance()->getCountries()->getCountryByIso('NO');
            $shippingDetails = $payload['shippingDetails'];
            $addressPayload  = $shippingDetails['address'];
            $address         = new Address([
                'firstName' => \data_get($payload, 'userDetails.firstName'),
                'lastName'  => \data_get($payload, 'userDetails.lastName'),
                'address1'  => $addressPayload['addressLine1'] ?? null,
                'address2'  => $addressPayload['addressLine2'] ?? null,
                'city'      => $addressPayload['city'],
                'zipCode'   => $addressPayload['zipCode'],
                'countryId' => $country->id,
            ]);

            $order->setBillingAddress($address);
            $order->setShippingAddress($address);
            $order->shippingMethodHandle = $shippingDetails['shippingMethodId'];

            if (!$order->getCustomer()->getUser() && empty($order->getEmail())) {
                $order->setEmail($response->getEmail());
            }

            $order->recalculate();

            if (!Craft::$app->getElements()->saveElement($order, false)) {
                LogToFile::error(Craft::t(
                    'vipps',
                    '(Vipps) Failed to save order after Express callback and address update: {error}',
                    [
                        'error' => Json::encode($order->getErrors()),
                    ]));
            }
        }

        $childTransaction = Plugin::getInstance()->getTransactions()->createTransaction(null, $transaction);

        if ($response->isExpress()) {
            $amount = $response->getAmount(false) / 100;

            // Make sure the amount is correct since it can change on the Vipps side
            // when customer selects shipping method
            $orderTotal = $order->getTotalPrice();
            if ($orderTotal !== $amount) {
                $diff = $orderTotal - $amount;
                LogToFile::info("The paid amount ({$amount}) and order total ({$orderTotal}) did not match. There was a discrepancy of {$diff}");

                // Check if there is a minor discrepancy
                if (0.10 > $diff) {
                    LogToFile::info('The discrepancy is below 0.10 - setting transaction to order total.');

                    $childTransaction->amount        = $orderTotal;
                    $childTransaction->paymentAmount = $orderTotal;
                }
                else {
                    $childTransaction->amount        = $amount;
                    $childTransaction->paymentAmount = $amount;
                }
            }
        }

        $this->_updateTransaction($childTransaction, $response);

        // Success can mean 2 things in this context.
        // 1) The transaction completed successfully with the gateway, and is now marked as complete.
        // 2) The result of the gateway request was successful but also got a redirect response. We now need to redirect if $redirect is not null.
        $success = $response->isSuccessful() || $response->isProcessing();

        if ($success) {
            $transaction->getOrder()->updateOrderPaidInformation();
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
            $error = 'Error saving transaction: ' . implode(', ', $child->errors);

            LogToFile::error($error);
            throw new TransactionException($error);
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
        // @todo Get user
        // @todo Get other things?
        $user = $userId;

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
            'address1'  => $payload['addressLine1'] ?? null,
            'address2'  => $payload['addressLine2'] ?? null,
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
        $body    = (string)Craft::$app->getRequest()->getRawBody();
        $payload = Json::decodeIfJson($body);
        $headers = Craft::$app->getRequest()->getHeaders();

        $message = Craft::t(
            'vipps',
            "Payload received from Vipps:\n{body}\n\nHeaders:\n{headers}",
            [
                'body'    => $body,
                'headers' => Json::encode($headers),
            ]
        );
        LogToFile::info($message);

        if (!$payload) {
            $error = Craft::t(
                'vipps',
                "Invalid payload received from Vipps:\n{body}\n\nHeaders:\n{headers}",
                [
                    'body'    => $body,
                    'headers' => Json::encode($headers),
                ]
            );
            LogToFile::error($error);

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
            $error = Craft::t(
                'vipps',
                "Invalid auth token received from Vipps:\n{token}",
                [
                    'token' => $token,
                ]
            );
            LogToFile::error($error);

            throw new HttpException(400, 'Invalid auth token');
        }

        return true;
    }
}
