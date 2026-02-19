<?php

declare(strict_types=1);

use superbig\vipps\responses\VippsResponse;

// === State Mapping ===

it('treats AUTHORIZED as successful', function () {
    $response = new VippsResponse([
        'state' => 'AUTHORIZED',
        'pspReference' => 'psp-123',
        'reference' => 'ref-456',
    ]);

    expect($response->isSuccessful())->toBeTrue();
    expect($response->isRedirect())->toBeFalse();
    expect($response->isProcessing())->toBeFalse();
    expect($response->getMessage())->toBe('Payment authorized');
});

it('treats CAPTURED as successful', function () {
    $response = new VippsResponse([
        'state' => 'CAPTURED',
        'pspReference' => 'psp-789',
    ]);

    expect($response->isSuccessful())->toBeTrue();
    expect($response->isRedirect())->toBeFalse();
});

it('treats CREATED with redirectUrl as redirect', function () {
    $response = new VippsResponse([
        'state' => 'CREATED',
        'redirectUrl' => 'https://vipps.no/pay/123',
        'reference' => 'ref-abc',
    ]);

    expect($response->isRedirect())->toBeTrue();
    expect($response->isSuccessful())->toBeFalse();
    expect($response->isProcessing())->toBeFalse();
    expect($response->getRedirectUrl())->toBe('https://vipps.no/pay/123');
    expect($response->getRedirectMethod())->toBe('GET');
    expect($response->getRedirectData())->toBe([]);
});

it('treats CREATED without redirectUrl as processing', function () {
    $response = new VippsResponse([
        'state' => 'CREATED',
        'reference' => 'ref-abc',
    ]);

    expect($response->isProcessing())->toBeTrue();
    expect($response->isRedirect())->toBeFalse();
    expect($response->isSuccessful())->toBeFalse();
});

// === Failed States ===

it('treats ABORTED as failed', function () {
    $response = new VippsResponse(['state' => 'ABORTED']);

    expect($response->isSuccessful())->toBeFalse();
    expect($response->isRedirect())->toBeFalse();
    expect($response->isProcessing())->toBeFalse();
    expect($response->getMessage())->toBe('Payment aborted');
});

it('treats EXPIRED as failed', function () {
    $response = new VippsResponse(['state' => 'EXPIRED']);

    expect($response->isSuccessful())->toBeFalse();
    expect($response->getMessage())->toBe('Payment expired');
});

it('treats TERMINATED as failed', function () {
    $response = new VippsResponse(['state' => 'TERMINATED']);

    expect($response->isSuccessful())->toBeFalse();
    expect($response->getMessage())->toBe('Payment terminated');
});

it('treats CANCELLED as failed', function () {
    $response = new VippsResponse(['state' => 'CANCELLED']);

    expect($response->isSuccessful())->toBeFalse();
    expect($response->getMessage())->toBe('Payment cancelled');
});

// === Transaction Reference ===

it('returns pspReference as transaction reference', function () {
    $response = new VippsResponse([
        'state' => 'AUTHORIZED',
        'pspReference' => 'psp-primary',
        'reference' => 'ref-fallback',
    ]);

    expect($response->getTransactionReference())->toBe('psp-primary');
});

it('falls back to reference when pspReference is missing', function () {
    $response = new VippsResponse([
        'state' => 'AUTHORIZED',
        'reference' => 'ref-fallback',
    ]);

    expect($response->getTransactionReference())->toBe('ref-fallback');
});

it('returns empty string when no reference exists', function () {
    $response = new VippsResponse(['state' => 'AUTHORIZED']);

    expect($response->getTransactionReference())->toBe('');
});

// === Error Responses ===

it('handles error responses from fromError factory', function () {
    $response = VippsResponse::fromError('Something went wrong', 500);

    expect($response->isSuccessful())->toBeFalse();
    expect($response->isRedirect())->toBeFalse();
    expect($response->getMessage())->toBe('Something went wrong');
    expect($response->getCode())->toBe('500');
});

it('handles Vipps API error format with title and detail', function () {
    $response = new VippsResponse([
        'title' => 'Bad Request',
        'detail' => 'Invalid amount format',
    ]);

    expect($response->isSuccessful())->toBeFalse();
    expect($response->getMessage())->toBe('Bad Request: Invalid amount format');
});

it('handles Vipps API error format with title only', function () {
    $response = new VippsResponse([
        'title' => 'Internal Server Error',
    ]);

    expect($response->getMessage())->toBe('Internal Server Error');
});

// === REFUNDED State ===

it('treats REFUNDED as successful', function () {
    $response = new VippsResponse([
        'state' => 'REFUNDED',
        'pspReference' => 'psp-refund-123',
    ]);

    expect($response->isSuccessful())->toBeTrue();
    expect($response->isRedirect())->toBeFalse();
    expect($response->getMessage())->toBe('Payment refunded');
});

// === Raw Data ===

it('returns raw data via getData', function () {
    $data = ['state' => 'AUTHORIZED', 'pspReference' => 'psp-123', 'extra' => 'field'];
    $response = new VippsResponse($data);

    expect($response->getData())->toBe($data);
});

// === Code ===

it('returns state as code', function () {
    $response = new VippsResponse(['state' => 'AUTHORIZED']);

    expect($response->getCode())->toBe('AUTHORIZED');
});

it('returns explicit code when present', function () {
    $response = VippsResponse::fromError('Error', 422);

    expect($response->getCode())->toBe('422');
});
