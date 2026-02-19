<?php

declare(strict_types=1);

use superbig\vipps\models\PaymentForm;

it('can be instantiated with default values', function () {
    $form = new PaymentForm();

    expect($form->phoneNumber)->toBeNull();
});

it('accepts a phone number', function () {
    $form = new PaymentForm();
    $form->phoneNumber = '+4712345678';

    expect($form->phoneNumber)->toBe('+4712345678');
});

it('extends BasePaymentForm', function () {
    $form = new PaymentForm();

    expect($form)->toBeInstanceOf(\craft\commerce\models\payments\BasePaymentForm::class);
});
