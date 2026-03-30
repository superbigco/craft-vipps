<?php

declare(strict_types=1);

use superbig\vipps\controllers\ExpressController;

// === Instantiation ===

it('can instantiate the express controller', function () {
    $controller = (new ReflectionClass(ExpressController::class))
        ->newInstanceWithoutConstructor();

    expect($controller)->toBeInstanceOf(ExpressController::class);
});

// === Anonymous Access ===

it('allows anonymous access to shipping-callback', function () {
    $controller = (new ReflectionClass(ExpressController::class))
        ->newInstanceWithoutConstructor();

    $property = new ReflectionProperty($controller, 'allowAnonymous');
    $property->setAccessible(true);
    $value = $property->getValue($controller);

    expect($value)->toContain('shipping-callback');
});

// === CSRF Disabled ===

it('has CSRF validation disabled', function () {
    $controller = (new ReflectionClass(ExpressController::class))
        ->newInstanceWithoutConstructor();

    expect($controller->enableCsrfValidation)->toBeFalse();
});
