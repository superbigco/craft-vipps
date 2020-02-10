# Vipps plugin for Craft CMS 3.x

Integrate Craft Commerce with Vipps.

![Screenshot](resources/img/icon.png)

## Requirements

This plugin requires Craft CMS 3.1.0 and Craft Commerce 2.0 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require superbigco/craft-vipps

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Vipps.

## Vipps Overview

This plugin provides your Craft Commerce shop with two payment alternatives:

- A normal gateway for Craft Commerce, where a customer can select Vipps when checkout out
- Express Checkout - where a user taps the familiar, orange Vipps Checkout button and jumps right to the Vipps app to pay.

### What is Vipps?

Vipps is a mobile app that makes it easy to transfer money to others, pay in stores, online stores and bills.

Vipps has 2.9 million users in Norway and is used daily by tens of thousands to shop online.

## Configuring Vipps

You may either configure the gateway on the Gateway Settings screen, or create a config file named `commerce-gateways.php` in `config/`.

```php
<?php
return [
    'gatewayHandle' => [
        'testMode'                   => true,
        'clientId'                   => '',
        'clientSecret'               => '',
        'subscriptionKeyAccessToken' => '',
        'merchantSerialNumber'       => '',
        'useBillingPhoneAsVippsPhoneNumber' => true,
        'captureOnStatusChange'             => true,
        'captureStatusUid'                  => '',
        
        // You have access to any properties on the order +
        // a property called lineItemsText that will list the 
        // line items as text + their quantity
        'transactionText'            => '{lineItemsText}',

        // Users will be redirected here on success or when something goes wrong
        'fallbackUrl'                => \craft\helpers\UrlHelper::siteUrl('/shop/confirmation?order={number}'),

        // Express checkout
        'expressCheckout'              => true,
        'newCartOnExpressCheckout'     => true,
    ],
];
```

Note that the `gatewayHandle` here has to match your gateway's handle in the Commerce Gateway settings.

See [Commerce docs](https://docs.craftcms.com/commerce/v2/gateway-config.html) for more information on how to configure gateways with a config file.

### Overview of config settings

This is an overview that shows you where to get the different config values through the Vipps Developer Portal.

| Config value | Description | Where to get it | Example value |
| :--- | :--- | :--- | ---: |
| **testMode** | Enable test mode |  | `true/false` |
| **clientId** | Client ID | Utvikler -> Select _Showing test keys_ -> Show keys -> Client Id | String |
| **clientSecret** | Client Secret | Utvikler -> Select _Showing test keys_ -> Show keys -> Client Secret | String |
| **subscriptionKeyAccessToken** | Subscription Key for authorizing Vipps API calls | Utvikler -> Select _Showing test keys_ -> Show keys -> Primary or secondary key | String |
| **merchantSerialNumber** | Merchant Serial Number | Utvikler -> Select _Showing test keys_ -> Merchant Serial Number | String |
| **transactionText** | This text will show up in the Vipps app when a customer is paying |  | String |
| **expressCheckout** | Enable Express Checkout | | `true/false` |  
| **addItemToCartIfAlreadyExists** | On Express Checkout, this will decide if a item should be added to the existing cart instead of replaced | | `true/false` |
| **newCartOnExpressCheckout** | Creates a new cart on Express Checkout | | `true/false` | 
| **fallbackUrl** | Vipps will redirect to this URL when a payment is completed or cancelled. |  | String |
| **authToken** | Read-only - Token used to verify callbacks from Vipps |  | String |
| **captureOnStatusChange** | Enable automatic capture when switching order to new Order Status | | `true/false` | 
| **captureStatusUid** | The uid of the Order Status to capture automatically |  | String |
| **useBillingPhoneAsVippsPhoneNumber** | Pull the Vipps phone number automatically from billing address, if set and not empty | | `true/false` | 

## Using Vipps

## Express Checkout Buttons

Vipps allow a customer to check and pay for a order straight in the Vipps out, decreasing the number of steps a customer have to take to finish a order.

To display a Express Checkout button for a variant:
```twig
{{ craft.vipps.getExpressFormButton(variant) }}
```

To display a Express Checkout button for a cart:
```twig
{{ craft.vipps.getExpressFormButton() }}
```

## Custom Express Checkout

If you need to do something custom around the Express Checkout flow, like allow the customer to provide a note, select quantity or use line item `options`, you can handle the input manually and submit to `/vipps/express/checkout`. This endpoint receives the same `purchasables` input as the normal Commerce endpoint.

```twig
{% set product = craft.products.one() %}
{% set variant = product.defaultVariant %}
<form method="POST">
    <input type="hidden" name="action" value="vipps/express/checkout">
    {{ csrfInput() }}
    <input type="hidden" name="purchasables[0][qty]" value="1">

    <input type="text" name="purchasables[0][note]" value="" placeholder="note">

    <select name="purchasables[0][options][engraving]">
        <option value="happy-birthday">Happy Birthday</option>
        <option value="good-riddance">Good Riddance</option>
    </select>

    <select name="purchasables[0][options][giftwrap]">
        <option value="yes">Yes Please</option>
        <option value="no">No Thanks</option>
    </select>

    <input type="hidden" name="purchasables[0][id]" value="{{ variant.id }}">
    <input type="submit" value="Vipps Express Checkout">
</form>
```

See the [Commerce docs on Add to Cart](https://docs.craftcms.com/commerce/v2/adding-to-and-updating-the-cart.html) for more information.

See [Brand Guidelines](https://paper.dropbox.com/doc/Vipps-Payment-guidelines--AYLr~Jd2mNsXGXJV1IGf7FBLAg-tBvSIbJpzDrqBziYeQMCH) for more advice on how to use the payment buttons.

## Known issues

If there is a discrepancy between paid amount in Vipps and Commerce, it will now use the order total if the discrepancy is less than 0.10

## Get Support

### Discord

Get in touch via the [Craft Discord](https://craftcms.com/discord), in the `#craft3-help` channel. Mention one of our team members on the following handles:

- `@superbig`
- `@fred`

### Twitter

Get our attention on Twitter by using the `#craftcms` hashtag and mentioning [@sjelfull](https://twitter.com/sjelfull)

### Email

If you have any feedback, comments, questions or suggestion: email us at `contact at superbig.co`.

## Vipps Roadmap

Some things to do, and ideas for potential features:

- [ ] Handle signup after payment 
- [ ] Support for user signups via Vipps
- [ ] Support for subscriptions (once Vipps makes it available)
- [ ] Better handling of addresses and existing customers

Brought to you by [Superbig](https://superbig.co)
