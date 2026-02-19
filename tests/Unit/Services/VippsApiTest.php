<?php

use superbig\vipps\services\VippsApi;

it('can instantiate the API service', function() {
    expect(new VippsApi())->toBeInstanceOf(VippsApi::class);
});

it('has correct production base URL', function() {
    expect(VippsApi::PRODUCTION_BASE_URL)->toBe('https://api.vipps.no');
});

it('has correct test base URL', function() {
    expect(VippsApi::TEST_BASE_URL)->toBe('https://apitest.vipps.no');
});
