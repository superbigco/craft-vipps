# Vipps MobilePay for Craft Commerce

Vipps MobilePay payment gateway for [Craft Commerce](https://craftcms.com/commerce) 5.

> **⚠️ Work in Progress** — This is a complete rebuild targeting the Vipps ePayment API v1. Not yet ready for production use.

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.2 |
| Craft CMS | ^5.5 |
| Craft Commerce | ^5.0 |

## Installation

```bash
composer require superbig/craft-vipps
php craft plugin/install vipps
```

## Configuration

Add the Vipps MobilePay gateway in **Commerce → Settings → Gateways**.

### Gateway Settings

| Setting | Description | Env Var Support |
|---|---|---|
| Client ID | From the Vipps MobilePay portal | ✅ |
| Client Secret | From the Vipps MobilePay portal | ✅ |
| Subscription Key | Ocp-Apim-Subscription-Key for your sales unit | ✅ |
| Merchant Serial Number | MSN (4-10 digits) for your sales unit | ✅ |
| Transaction Text | Text shown in the Vipps app (supports object templates) | ✅ |
| Test Mode | Use the Vipps test environment | — |

### Environment Variables

All credential fields support Craft's environment variable syntax:

```env
VIPPS_CLIENT_ID=your-client-id
VIPPS_CLIENT_SECRET=your-client-secret
VIPPS_SUBSCRIPTION_KEY=your-subscription-key
VIPPS_MSN=123456
```

## Supported Features

- ✅ Authorize (redirect to Vipps app)
- ✅ Capture (full and partial)
- ✅ Refund (full and partial)
- 🚧 Express Checkout
- 🚧 Webhooks

## API

This plugin uses the [Vipps ePayment API v1](https://developer.vippsmobilepay.com/docs/APIs/epayment-api/).

## License

This plugin is licensed under the [Craft License](LICENSE.md).

Brought to you by [Superbig](https://superbig.co).
