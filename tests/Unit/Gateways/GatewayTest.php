<?php

declare(strict_types=1);

use superbig\vipps\gateways\Gateway;
use superbig\vipps\models\PaymentForm;
use yii\base\NotSupportedException;

// === Instantiation ===

it('can instantiate the gateway', function () {
    expect(new Gateway())->toBeInstanceOf(Gateway::class);
});

// === Capabilities ===

it('supports authorize', function () {
    $gateway = new Gateway();
    expect($gateway->supportsAuthorize())->toBeTrue();
});

it('supports capture', function () {
    $gateway = new Gateway();
    expect($gateway->supportsCapture())->toBeTrue();
});

it('supports complete authorize', function () {
    $gateway = new Gateway();
    expect($gateway->supportsCompleteAuthorize())->toBeTrue();
});

it('supports refund', function () {
    $gateway = new Gateway();
    expect($gateway->supportsRefund())->toBeTrue();
});

it('supports partial refund', function () {
    $gateway = new Gateway();
    expect($gateway->supportsPartialRefund())->toBeTrue();
});

it('supports webhooks', function () {
    $gateway = new Gateway();
    expect($gateway->supportsWebhooks())->toBeTrue();
});

it('does not support purchase', function () {
    $gateway = new Gateway();
    expect($gateway->supportsPurchase())->toBeFalse();
});

it('does not support complete purchase', function () {
    $gateway = new Gateway();
    expect($gateway->supportsCompletePurchase())->toBeFalse();
});

it('does not support payment sources', function () {
    $gateway = new Gateway();
    expect($gateway->supportsPaymentSources())->toBeFalse();
});

// === Credentials ===

it('returns credentials with parsed env vars', function () {
    $gateway = new Gateway();
    $gateway->clientId = 'test-client-id';
    $gateway->clientSecret = 'test-secret';
    $gateway->subscriptionKey = 'test-sub-key';
    $gateway->merchantSerialNumber = '123456';
    $gateway->testMode = true;

    $creds = $gateway->getCredentials();

    expect($creds)->toMatchArray([
        'clientId' => 'test-client-id',
        'clientSecret' => 'test-secret',
        'subscriptionKey' => 'test-sub-key',
        'msn' => '123456',
        'testMode' => true,
    ]);
});

// === Payment Form ===

it('returns a PaymentForm model', function () {
    $gateway = new Gateway();

    expect($gateway->getPaymentFormModel())->toBeInstanceOf(PaymentForm::class);
});

it('returns null for payment form HTML', function () {
    $gateway = new Gateway();

    expect($gateway->getPaymentFormHtml([]))->toBeNull();
});

// === Unsupported Operations ===

it('throws NotSupportedException for purchase', function () {
    $gateway = new Gateway();
    $transaction = (new ReflectionClass(\craft\commerce\models\Transaction::class))
        ->newInstanceWithoutConstructor();
    $gateway->purchase($transaction, new PaymentForm());
})->throws(NotSupportedException::class);

it('throws NotSupportedException for completePurchase', function () {
    $gateway = new Gateway();
    $transaction = (new ReflectionClass(\craft\commerce\models\Transaction::class))
        ->newInstanceWithoutConstructor();
    $gateway->completePurchase($transaction);
})->throws(NotSupportedException::class);

it('throws NotSupportedException for createPaymentSource', function () {
    $gateway = new Gateway();
    $gateway->createPaymentSource(new PaymentForm(), 1);
})->throws(NotSupportedException::class);

it('throws NotSupportedException for deletePaymentSource', function () {
    $gateway = new Gateway();
    $gateway->deletePaymentSource('token-123');
})->throws(NotSupportedException::class);

// === Validation Rules ===

it('has validation rules including required credentials', function () {
    $gateway = new Gateway();
    $rules = $gateway->defineRules();

    // Flatten all required rules to find our credential fields
    $requiredFields = [];
    foreach ($rules as $rule) {
        if (is_array($rule) && isset($rule[1]) && $rule[1] === 'required') {
            $fields = is_array($rule[0]) ? $rule[0] : [$rule[0]];
            $requiredFields = array_merge($requiredFields, $fields);
        }
    }

    expect($requiredFields)->toContain('clientId');
    expect($requiredFields)->toContain('clientSecret');
    expect($requiredFields)->toContain('subscriptionKey');
    expect($requiredFields)->toContain('merchantSerialNumber');
});
