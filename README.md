# Simple Profit Calculator for WooCommerce

See your **real** monthly and yearly profit in WooCommerce: sales minus refunds and tax, minus product costs, payment fees and your own monthly expenses.

[![CI](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/actions/workflows/ci.yml)
![License: GPL v2 or later](https://img.shields.io/badge/license-GPLv2%2B-blue)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bb4)

## Features

- **Product cost (COGS)**, whichever way suits your store:
  - WooCommerce's built-in Cost of Goods, or the Cost of Goods plugins by WPFactory or SkyVerge (detected automatically),
  - a "Cost price" field this plugin adds to products and variations when no cost plugin is installed,
  - one lump sum per month (e.g. supplier invoices), or
  - a percentage of sales.
- **Payment fees** per payment method: percentage and/or fixed fee per order.
- **Your own monthly costs** (shipping, ads, packaging, software, rent…), entered for a whole year on one screen and saved with one click.
- Monthly breakdown, yearly overview, CSV export, missing-cost warnings, partial refunds handled.
- HPOS and legacy order storage. Uses the store's currency, timezone and number format.
- No external requests, no tracking.

## Install

- **WordPress.org:** search for "Simple Profit Calculator for WooCommerce" in *Plugins → Add New* (once approved).
- **From GitHub:** download the zip from the [latest release](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/releases), then *Plugins → Add New → Upload Plugin*.

Requires WordPress 6.3+, WooCommerce 7.0+, PHP 7.4+.

## Support

- 🐞 **Bugs:** [open a bug report](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/issues/new?template=bug_report.yml)
- 💡 **Feature requests:** [suggest a feature](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/issues/new?template=feature_request.yml)
- 🔒 **Security issues:** please report **privately**. See [SECURITY.md](SECURITY.md).

## Development

```bash
# Coding standards (WordPress) and PHP 7.4+ compatibility
composer global require wp-coding-standards/wpcs phpcompatibility/phpcompatibility-wp
phpcs --standard=WordPress --extensions=php .
phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- --extensions=php .

# Build the installable zip (excludes files listed in .distignore)
bin/build-zip.sh
```

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPLv2 or later. See [LICENSE](LICENSE).

Made by [Ahmad Abdur Rehman](https://ahmad.science).
