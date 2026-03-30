# Vipps Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.0.0 - Unreleased

### Added
- Craft 5 and Commerce 5 support
- PHP 8.2+ support
- Vipps ePayment API v1 (replaces legacy eCom v2)
- Vipps MobilePay branding (unified Nordic payment)
- Cached token management with automatic refresh
- Partial capture and partial refund support
- Environment variable support for all gateway credentials

### Changed
- Complete rebuild targeting modern Vipps API
- Gateway settings simplified (removed legacy subscription key fields)

### Removed
- Craft 3/4 support
- Legacy eCom v2 API support
- Express Checkout (to be re-added)
- Login with Vipps
