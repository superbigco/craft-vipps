<?php

use superbig\vipps\gateways\Gateway;

it('can instantiate the gateway', function() {
    expect(new Gateway())->toBeInstanceOf(Gateway::class);
});

it('supports authorize', function() {
    $gateway = new Gateway();
    expect($gateway->supportsAuthorize())->toBeTrue();
});

it('supports capture', function() {
    $gateway = new Gateway();
    expect($gateway->supportsCapture())->toBeTrue();
});

it('supports refund', function() {
    $gateway = new Gateway();
    expect($gateway->supportsRefund())->toBeTrue();
});

it('supports partial refund', function() {
    $gateway = new Gateway();
    expect($gateway->supportsPartialRefund())->toBeTrue();
});

it('does not support purchase', function() {
    $gateway = new Gateway();
    expect($gateway->supportsPurchase())->toBeFalse();
});

it('does not support payment sources', function() {
    $gateway = new Gateway();
    expect($gateway->supportsPaymentSources())->toBeFalse();
});

it('returns credentials with parsed env vars', function() {
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
