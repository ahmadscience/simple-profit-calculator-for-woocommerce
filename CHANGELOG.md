# Changelog

All notable changes to this project. Versions follow [Semantic Versioning](https://semver.org/).

## [2.0.2] - 2026-10-19

Code-quality release. **No functional changes.**

### Changed
- Passes the WordPress Coding Standards (WPCS 3.4) at the strictest level: every error and warning severity, all files including tests.
- Places where the capability comes from the filterable `spcw_capability` setting are annotated, so static analysis can't mistake them for a missing permission check.
- Test suite brought up to the WordPress standard (docblocks, prepared SQL, escaping).

### CI
- PHPCS runs at `--error-severity=1 --warning-severity=1`.
- Plugin Check runs in strict mode with experimental checks.
- Plugin Check tooling is only installed from npm releases at least 7 days old, which guards against half-published or compromised packages.

## [2.0.1] - 2026-10-12

Security hardening after an in-depth review. Recommended for all users. Details: [docs/security.md](docs/security.md#audit-log).

### Security
- Only administrators can enable "delete data on uninstall"; it was possible for Shop Managers (F1, medium).
- The one-time import from version 1 only runs when the version 1 table really exists with its original columns (F2, medium), and only for logged-in users who can use the plugin, never in AJAX requests (F3).
- Stronger CSV formula-injection protection (leading whitespace, more trigger characters); CSV download sends `X-Content-Type-Options: nosniff` (F8).
- Limits on amounts, note length, number of cost types and payment methods (F6).

### Fixed
- Multisite: uninstall cleans up every site that opted in, not only the main site (F4).
- Concurrent saves of the same month could fail; saves are now atomic (F5).
- Outdated cache entries are removed from the database instead of waiting up to 30 days to expire (F7).
- Fixed payment fees are shown with two decimals.
- Yearly table fits on 1280px+ screens without horizontal scrolling.

### Added
- Developer filters `spcw_product_unit_cost`, `spcw_order_statuses`, `spcw_month_result`.
- Lower memory use when a month has many orders (F9).
- Declared compatibility with WooCommerce Cart & Checkout blocks (F10).
- Integration test suite (WordPress Playground) and WordPress Plugin Check in CI.
- User guide, developer guide, security documentation, screenshots.

## [2.0.0] - 2026-10-12

First public release, a complete rewrite of the private "Profit Calculator" 1.x.

### Added
- Product cost from WooCommerce Cost of Goods, the WPFactory or SkyVerge Cost of Goods plugins, a built-in "Cost price" field, a monthly lump sum, or a percentage of sales.
- Custom monthly cost types; whole-year editing with one Save button; "Copy previous" month.
- Payment fees per method with percentage and fixed fee per order.
- CSV export, missing-cost warnings, Refresh data button, opt-in data removal on uninstall.
- HPOS support; store currency, timezone and number format; translation ready.
- Automatic import of version 1 data.

### Fixed (compared with 1.x)
- The Monthly Data page saved only the row whose button was clicked.
- Orders without a payment method were charged another gateway's fee.
- Partial refunds weren't subtracted.
- New orders took up to 6 hours to appear.
- HPOS month boundaries ignored daylight saving time.
- Amounts like `1.234,56` were stored as `1.234`.

### Security (compared with 1.x)
- Removed a diagnostics page that reset PHP OPcache server-wide, enabled `display_errors` and overwrote settings.
- All input validated and sanitised, all output escaped, nonces and capability checks on every action.

[2.0.2]: https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/compare/v2.0.1...v2.0.2
[2.0.1]: https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/releases/tag/v2.0.0
