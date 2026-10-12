# Contributing

Thanks for helping! Bug reports, feature ideas and pull requests are welcome.

## Reporting

- **Bugs / features:** use the [issue templates](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/issues/new/choose).
- **Security:** never in public issues. See [SECURITY.md](SECURITY.md).

## Pull requests

1. Fork and create a branch from `main`.
2. Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/). CI runs `phpcs --standard=WordPress` and a PHP 7.4+ compatibility check on every PR.
3. Keep the rules every change must follow:
   - Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) at the point of output.
   - Sanitise all input when it's read (`sanitize_*`, `absint`, `map_deep`), after `wp_unslash()`.
   - Every form handler checks `current_user_can()` **and** `check_admin_referer()`.
   - Use `$wpdb->prepare()` for every query with variables (`%i` for table names).
   - Prefix everything with `spcw_` / `SPCW_`.
   - All user-facing text is translatable with the text domain `simple-profit-calculator-for-woocommerce`.
   - No external requests, no tracking.
4. Add or update tests in `tests/run-tests.php` and run them (see the [developer guide](docs/developer.md#testing)).
5. Describe how you tested the change (WordPress/WooCommerce/PHP versions, HPOS on/off). The PR template has a checklist.

## Project layout

```
simple-profit-calculator-for-woocommerce.php   main plugin file
includes/   settings, database, product cost, order data, calculator, v1 migration
admin/      admin controller and views
assets/     admin CSS and JS (no build step)
tests/      integration tests (WordPress Playground)
docs/       user, developer and security documentation
```
