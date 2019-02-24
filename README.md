# Vipps plugin for Craft CMS 3.x

Integrate Craft Commerce with Vipps.

_NOTE: This plugin is in beta and should be tested properly before being put in production._

![Screenshot](resources/img/icon.png)

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require superbigco/craft-vipps

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Vipps.

## Vipps Overview

-Insert text here-

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
        'subscriptionKeyEcommerce'   => '',
        'merchantSerialNumber'       => '',
        
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

## Vipps Roadmap

Some things to do, and ideas for potential features:

- [ ] Handle signup after payment 
- [ ] Support for user signups via Vipps
- [ ] Support for subscriptions (once Vipps makes it available)

Brought to you by [Superbig](https://superbig.co)
