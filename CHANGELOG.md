# Vipps Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## Unreleased

### Changed
- If there is a discrepancy between paid amount in Vipps and Commerce, it will now use the order total if the discrepancy is less than 0.10

### Fixed
- Fixed marking order as completed on Express callback
- Fixed class param for express checkout links 
- Fixed payment amount for successful express transactions
- Fixed missing address lines on Express payment that made status update fail silently
- Fixed JS event issue when there is multiple buttons
- Fixed error check on gateway response
- Fixed error when calling API through console commands

### Added
- Added Twig params to express button 
- Added better error handling in shipping callback

## 1.0.2 - 2019-06-10
### Fixed
- Fixed error with wrong status type in Commerce 2.1 when redirecting to Vipps

## 1.0.1 - 2019-02-26
### Fixed
- Fixed payment request when no phone number is set
- Fixed error message for payment request


## 1.0.0 - 2019-02-26
### Added
- Initial release
