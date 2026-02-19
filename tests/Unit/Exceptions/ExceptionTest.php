<?php

use superbig\vipps\exceptions\VippsApiException;
use superbig\vipps\exceptions\VippsAuthenticationException;

it('VippsApiException carries HTTP status and response body', function() {
    $exception = new VippsApiException(
        'Payment failed',
        400,
        ['title' => 'Bad Request', 'detail' => 'Invalid amount'],
    );

    expect($exception->getMessage())->toBe('Payment failed');
    expect($exception->getHttpStatusCode())->toBe(400);
    expect($exception->getResponseBody())->toBe(['title' => 'Bad Request', 'detail' => 'Invalid amount']);
});

it('VippsApiException handles null response body', function() {
    $exception = new VippsApiException('Network error', 0);

    expect($exception->getResponseBody())->toBeNull();
    expect($exception->getHttpStatusCode())->toBe(0);
});

it('VippsAuthenticationException is a RuntimeException', function() {
    $exception = new VippsAuthenticationException('Auth failed');

    expect($exception)->toBeInstanceOf(\RuntimeException::class);
    expect($exception->getMessage())->toBe('Auth failed');
});
