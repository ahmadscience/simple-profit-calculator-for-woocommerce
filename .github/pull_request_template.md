## What does this change?

<!-- Short description. Link the issue: "Fixes #123". -->

## How was it tested?

<!-- WordPress / WooCommerce / PHP versions, HPOS on or off, what you clicked. -->

## Checklist

- [ ] Input is read through `SPCW_Admin::posted()` (or otherwise unslashed + sanitised) and validated
- [ ] Output is escaped at the point of output
- [ ] New form handlers check capability **and** nonce
- [ ] SQL uses `$wpdb->prepare()` (`%i` for table names)
- [ ] New strings are translatable with the `simple-profit-calculator-for-woocommerce` text domain
- [ ] Tests added or updated in `tests/run-tests.php` when behaviour changes
- [ ] `readme.txt` / `CHANGELOG.md` / docs updated if users or developers will notice the change

<!-- Security issues: don't open a PR. Report privately, see SECURITY.md. -->
