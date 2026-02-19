<?php

declare(strict_types=1);

namespace superbig\vipps\controllers;

use Craft;
use craft\commerce\Plugin as CommercePlugin;
use craft\elements\Address;
use craft\helpers\Json;
use craft\web\Controller;
use superbig\vipps\gateways\Gateway;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Handles Vipps Express Checkout callbacks.
 *
 * Vipps calls the shipping-callback action with the user's address
 * to get available shipping options for the order.
 */
class ExpressController extends Controller
{
    /**
     * Allow anonymous access — Vipps calls this endpoint server-to-server.
     */
    protected array|bool|int $allowAnonymous = ['shipping-callback'];

    /**
     * Disable CSRF for server-to-server callbacks from Vipps.
     */
    public $enableCsrfValidation = false;

    /**
     * Handle dynamic shipping callback from Vipps.
     *
     * Vipps POSTs the user's address, we return available shipping options
     * in the Vipps shipping groups format.
     *
     * @return Response JSON response with shipping groups
     */
    public function actionShippingCallback(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $reference = $request->getQueryParam('reference');

        if (empty($reference)) {
            return $this->_errorResponse('Missing reference parameter', 400);
        }

        // Parse the callback body
        $rawBody = $request->getRawBody();
        $payload = Json::decodeIfJson($rawBody);

        if (!is_array($payload)) {
            return $this->_errorResponse('Invalid JSON body', 400);
        }

        // Find the gateway and verify the callback token
        $gateway = $this->_findGatewayForReference($reference);
        if ($gateway === null) {
            return $this->_errorResponse('Could not find gateway for reference', 404);
        }

        // Verify callback authorization token
        $authHeader = $request->getHeaders()->get('Authorization');
        $token = null;
        if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        }

        if ($token === null || !$gateway->verifyCallbackToken($token, $reference)) {
            Craft::warning(
                sprintf('Vipps Express shipping callback: invalid authorization token for reference %s', $reference),
                __METHOD__,
            );

            return $this->_errorResponse('Invalid authorization token', 401);
        }

        // Find the order by transaction hash (reference = transaction hash)
        $order = $this->_findOrderByTransactionHash($reference);
        if ($order === null) {
            return $this->_errorResponse('Order not found for reference', 404);
        }

        // Build a temporary address from the Vipps callback data
        $address = new Address();
        $address->addressLine1 = $payload['AddressLine1'] ?? null;
        $address->addressLine2 = $payload['AddressLine2'] ?? null;
        $address->locality = $payload['City'] ?? null;
        $address->postalCode = $payload['PostCode'] ?? null;
        $address->countryCode = $payload['Country'] ?? 'NO';

        // Set the address on the order so Commerce can calculate shipping
        $order->setShippingAddress($address);

        try {
            // Get available shipping methods for this address
            $shippingOptions = $order->getAvailableShippingMethodOptions();
        } catch (\Throwable $e) {
            Craft::error(
                sprintf('Failed to get shipping methods: %s', $e->getMessage()),
                __METHOD__,
            );

            return $this->_errorResponse('Failed to calculate shipping options', 500);
        }

        // Map Commerce shipping methods to Vipps shipping groups format
        $groups = $this->_mapShippingMethodsToVippsGroups($shippingOptions, $order);

        // If no shipping methods available, return a free "no shipping" option
        if (empty($groups)) {
            $groups = [
                [
                    'isDefault' => true,
                    'type' => 'HOME_DELIVERY',
                    'brand' => 'MERCHANT',
                    'options' => [
                        [
                            'id' => 'no-shipping',
                            'isDefault' => true,
                            'amount' => [
                                'currency' => $order->paymentCurrency,
                                'value' => 0,
                            ],
                            'name' => 'No shipping required',
                        ],
                    ],
                ],
            ];
        }

        return $this->asJson(['groups' => $groups]);
    }

    /**
     * Map Commerce ShippingMethodOption[] to Vipps shipping groups format.
     *
     * Each Commerce shipping method becomes a group with a single option.
     * The first method is marked as default.
     *
     * @param \craft\commerce\models\ShippingMethodOption[] $shippingOptions
     * @param \craft\commerce\elements\Order $order
     *
     * @return array Vipps shipping groups
     */
    private function _mapShippingMethodsToVippsGroups(array $shippingOptions, \craft\commerce\elements\Order $order): array
    {
        $groups = [];
        $isFirst = true;
        $currentHandle = $order->shippingMethodHandle;

        foreach ($shippingOptions as $option) {
            $method = $option->shippingMethod;
            if ($method === null) {
                continue;
            }

            $handle = $method->getHandle();
            $isDefault = (!$currentHandle && $isFirst) || $currentHandle === $handle;

            $priceInMinorUnits = (int) ($option->getPrice() * 100);

            $groups[] = [
                'isDefault' => $isDefault,
                'type' => 'HOME_DELIVERY',
                'brand' => 'MERCHANT',
                'options' => [
                    [
                        'id' => $handle,
                        'isDefault' => $isDefault,
                        'amount' => [
                            'currency' => $order->paymentCurrency,
                            'value' => $priceInMinorUnits,
                        ],
                        'name' => $method->getName(),
                    ],
                ],
            ];

            $isFirst = false;
        }

        return $groups;
    }

    /**
     * Find the Vipps gateway associated with a transaction reference.
     */
    private function _findGatewayForReference(string $reference): ?Gateway
    {
        $commerce = CommercePlugin::getInstance();
        if ($commerce === null) {
            return null;
        }

        // Find the transaction by hash
        $transaction = $commerce->getTransactions()->getTransactionByHash($reference);
        if ($transaction === null) {
            return null;
        }

        $gateway = $transaction->getGateway();

        return $gateway instanceof Gateway ? $gateway : null;
    }

    /**
     * Find a Commerce order by its transaction hash.
     */
    private function _findOrderByTransactionHash(string $hash): ?\craft\commerce\elements\Order
    {
        $commerce = CommercePlugin::getInstance();
        if ($commerce === null) {
            return null;
        }

        $transaction = $commerce->getTransactions()->getTransactionByHash($hash);
        if ($transaction === null) {
            return null;
        }

        return $transaction->getOrder();
    }

    /**
     * Return a JSON error response.
     */
    private function _errorResponse(string $message, int $statusCode): Response
    {
        $response = Craft::$app->getResponse();
        $response->statusCode = $statusCode;

        return $this->asJson([
            'error' => $message,
        ]);
    }
}
