<?php

use superbig\vipps\services\TokenManager;

it('can instantiate the token manager', function() {
    expect(new TokenManager())->toBeInstanceOf(TokenManager::class);
});

it('has a 5-minute expiry buffer', function() {
    expect(TokenManager::EXPIRY_BUFFER_SECONDS)->toBe(300);
});
