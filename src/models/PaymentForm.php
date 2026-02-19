<?php

declare(strict_types=1);

namespace superbig\vipps\models;

use craft\commerce\models\payments\BasePaymentForm;

/**
 * Minimal payment form for Vipps.
 *
 * Vipps handles all payment details in the app — the only optional
 * field is a phone number to pre-fill the Vipps login.
 */
class PaymentForm extends BasePaymentForm
{
    public ?string $phoneNumber = null;

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['phoneNumber'], 'string'];

        return $rules;
    }
}
