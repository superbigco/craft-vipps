<?php

declare(strict_types=1);

use superbig\vipps\gateways\Gateway;

// === Express Checkout Settings ===

it('has express checkout disabled by default', function () {
    $gateway = new Gateway();
    expect($gateway->expressCheckout)->toBeFalse();
});

it('has default profile scope', function () {
    $gateway = new Gateway();
    expect($gateway->expressProfileScope)->toBe('name phoneNumber address');
});

it('can enable express checkout', function () {
    $gateway = new Gateway();
    $gateway->expressCheckout = true;
    expect($gateway->expressCheckout)->toBeTrue();
});

it('can set custom profile scope', function () {
    $gateway = new Gateway();
    $gateway->expressProfileScope = 'name phoneNumber';
    expect($gateway->expressProfileScope)->toBe('name phoneNumber');
});

// === Callback Token ===

it('generates a deterministic callback token', function () {
    $gateway = new Gateway();
    $gateway->clientSecret = 'test-secret-key';

    $token1 = $gateway->verifyCallbackToken(
        hash_hmac('sha256', 'txn-hash-123', 'test-secret-key'),
        'txn-hash-123',
    );

    expect($token1)->toBeTrue();
});

it('rejects invalid callback tokens', function () {
    $gateway = new Gateway();
    $gateway->clientSecret = 'test-secret-key';

    $result = $gateway->verifyCallbackToken('invalid-token', 'txn-hash-123');

    expect($result)->toBeFalse();
});

it('generates different tokens for different references', function () {
    $gateway = new Gateway();
    $gateway->clientSecret = 'test-secret-key';

    $secret = 'test-secret-key';
    $token1 = hash_hmac('sha256', 'ref-1', $secret);
    $token2 = hash_hmac('sha256', 'ref-2', $secret);

    expect($token1)->not->toBe($token2);
    expect($gateway->verifyCallbackToken($token1, 'ref-1'))->toBeTrue();
    expect($gateway->verifyCallbackToken($token2, 'ref-2'))->toBeTrue();
    expect($gateway->verifyCallbackToken($token1, 'ref-2'))->toBeFalse();
});

// === Address Building (via reflection) ===

it('builds address from Vipps userDetails and shippingDetails', function () {
    $gateway = new Gateway();

    $method = new ReflectionMethod($gateway, '_buildAddressFromVippsProfile');
    $method->setAccessible(true);

    $userDetails = [
        'firstName' => 'Ada',
        'lastName' => 'Lovelace',
        'email' => 'ada@example.com',
        'mobileNumber' => '4712345678',
    ];

    $shippingDetails = [
        'address' => [
            'addressLine1' => 'Robert Levins gate 5',
            'addressLine2' => 'Apt 3',
            'city' => 'Oslo',
            'postCode' => '0154',
            'country' => 'NO',
        ],
        'shippingOptionId' => 'posten-standard',
    ];

    $result = $method->invoke($gateway, $userDetails, $shippingDetails);

    expect($result)->toBeArray();
    expect($result['addressLine1'])->toBe('Robert Levins gate 5');
    expect($result['addressLine2'])->toBe('Apt 3');
    expect($result['locality'])->toBe('Oslo');
    expect($result['postalCode'])->toBe('0154');
    expect($result['countryCode'])->toBe('NO');
    expect($result['firstName'])->toBe('Ada');
    expect($result['lastName'])->toBe('Lovelace');
});

it('falls back to userDetails addresses when no shippingDetails address', function () {
    $gateway = new Gateway();

    $method = new ReflectionMethod($gateway, '_buildAddressFromVippsProfile');
    $method->setAccessible(true);

    $userDetails = [
        'firstName' => 'Ada',
        'lastName' => 'Lovelace',
        'addresses' => [
            [
                'addressLine1' => 'Fallback Street 1',
                'city' => 'Bergen',
                'postCode' => '5003',
                'country' => 'NO',
            ],
        ],
    ];

    $result = $method->invoke($gateway, $userDetails, null);

    expect($result)->toBeArray();
    expect($result['addressLine1'])->toBe('Fallback Street 1');
    expect($result['locality'])->toBe('Bergen');
    expect($result['postalCode'])->toBe('5003');
});

it('returns null when no address data available', function () {
    $gateway = new Gateway();

    $method = new ReflectionMethod($gateway, '_buildAddressFromVippsProfile');
    $method->setAccessible(true);

    $result = $method->invoke($gateway, null, null);

    expect($result)->toBeNull();
});

it('returns null when userDetails has no addresses', function () {
    $gateway = new Gateway();

    $method = new ReflectionMethod($gateway, '_buildAddressFromVippsProfile');
    $method->setAccessible(true);

    $result = $method->invoke($gateway, ['firstName' => 'Ada'], null);

    expect($result)->toBeNull();
});

it('defaults country to NO when not provided', function () {
    $gateway = new Gateway();

    $method = new ReflectionMethod($gateway, '_buildAddressFromVippsProfile');
    $method->setAccessible(true);

    $shippingDetails = [
        'address' => [
            'addressLine1' => 'Some Street 1',
            'city' => 'Oslo',
            'postCode' => '0154',
            // No country field
        ],
    ];

    $result = $method->invoke($gateway, null, $shippingDetails);

    expect($result['countryCode'])->toBe('NO');
});
