# Developer guide

- [Architecture](#architecture)
- [Data model](#data-model)
- [Filters](#filters)
- [Caching](#caching)
- [Order queries](#order-queries)
- [Coding rules](#coding-rules)
- [Testing](#testing)
- [Releasing](#releasing)

## Architecture

```
simple-profit-calculator-for-woocommerce.php   header, constants, requires, hooks (activation, HPOS/blocks compatibility)
uninstall.php                                   opt-in data removal (multisite aware)
includes/
  class-spcw-plugin.php       bootstrap: WooCommerce check, table upgrades, wires the classes below
  class-spcw-settings.php     single option array, input parsing, limits, cache version
  class-spcw-db.php           {prefix}spcw_monthly_costs: manual costs per month
  class-spcw-cogs.php         per-unit product cost lookup + built-in "Cost price" field
  class-spcw-orders.php       per-month WooCommerce aggregates, transient cache, invalidation hooks
  class-spcw-calculator.php   profit maths, payment-fee matching
  class-spcw-migration.php    one-time import from v1 ("Profit Calculator")
admin/
  class-spcw-admin.php        menu, assets, admin-post handlers (save costs/settings, refresh, CSV export)
  views/                      dashboard.php, costs.php, settings.php (plain PHP templates)
assets/                       admin.css, admin.js (vanilla JS, no build step, no jQuery)
tests/                        integration tests run in WordPress Playground
```

Data flow for one month:

```
SPCW_Orders::get_month()  ──┐  (cached WooCommerce aggregates: gross, refunds, tax, shipping, COGS, gateways)
SPCW_DB::get_month()      ──┼─▶ SPCW_Calculator::month() ──▶ filter spcw_month_result ──▶ views / CSV
SPCW_Settings (fees, mode) ─┘
```

All classes are static and prefixed `SPCW_`. Nothing is hooked unless WooCommerce is active; admin screens, handlers and the v1 import are only hooked in `is_admin()`. On the front end the plugin only listens for order and cost-meta changes to keep its cache fresh.

## Data model

### Options

| Option | Autoload | Content |
|---|---|---|
| `spcw_settings` | yes | Array: `order_statuses` (string[] with `wc-` prefix), `cogs_mode` (`product`/`monthly`/`percent`), `cogs_source` (`auto`/`wc_core`/`wpfactory`/`skyverge`/`own`/`custom`), `cogs_meta_key`, `cogs_percent` (0–100), `gateways` (id ⇒ `label`, `percent`, `fixed`), `categories` (key ⇒ label; `shipping` always present), `delete_on_uninstall` (bool) |
| `spcw_db_version` | yes | Schema version; the table is (re)installed with `dbDelta()` when it differs from `SPCW_DB::VERSION` |
| `spcw_cache_version` | yes | Integer in every cache key; incremented to invalidate all cached months |
| `spcw_v1_migrated` | no | Timestamp of the v1 import check |

### Table `{prefix}spcw_monthly_costs`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `period_year` | smallint unsigned | Unique together with `period_month` |
| `period_month` | tinyint unsigned | 1–12 |
| `costs` | longtext | JSON object, category key ⇒ amount. Key `_cogs` holds the lump-sum product cost (monthly mode). Amounts are clamped to ±9 999 999 999.99 |
| `notes` | text | Max 1 000 characters |
| `updated_at` | datetime | UTC |

Rows are written with a single `INSERT … ON DUPLICATE KEY UPDATE`. A month with no amounts and no note has no row.

### Post meta

| Meta key | Where | Content |
|---|---|---|
| `_spcw_cost` | products and variations | Unit cost excl. VAT, decimal string. Only written by the built-in Cost price field. |

The plugin reads, but never writes, `_alg_wc_cog_cost` (WPFactory), `_wc_cog_cost` (SkyVerge), WooCommerce core COGS, or a custom meta key.

### Transients

| Transient | TTL | Content |
|---|---|---|
| `spcw_m_{cacheVersion}_{year}_{month}` | 1 h (current month) / 30 days (past months) | Aggregates from `SPCW_Orders::get_month()` |
| `spcw_first_order_year` | 1 day | Year of the first order, for the year selectors |
| `spcw_purge_lock` | 1 min | Throttles cleanup of stale cache rows |

## Filters

### `spcw_capability`

Capability required to see and use the plugin. Default `manage_woocommerce` (Administrators and Shop Managers).

```php
// Only administrators.
add_filter( 'spcw_capability', fn() => 'manage_options' );
```

Changing *"Delete data on uninstall"* additionally requires `manage_options` and `delete_plugins`. This filter can't lower that.

### `spcw_product_unit_cost`

Per-unit cost (excl. VAT) of a product or variation. Return `null` for "unknown" (it is then counted in the missing-cost warning).

```php
// Products in the "Dropship" category cost 65 % of their regular price.
add_filter( 'spcw_product_unit_cost', function ( $cost, $product ) {
	$id = $product->get_parent_id() ?: $product->get_id();
	if ( null === $cost && has_term( 'dropship', 'product_cat', $id ) ) {
		return (float) $product->get_regular_price() * 0.65;
	}
	return $cost;
}, 10, 2 );
```

Results are cached. After changing this filter's logic, click **Refresh data** or call `SPCW_Settings::bump_cache_version()`.

### `spcw_order_statuses`

Order statuses that count as sales (slugs with `wc-` prefix). Unknown statuses are removed afterwards.

```php
add_filter( 'spcw_order_statuses', function ( $statuses ) {
	$statuses[] = 'wc-shipped'; // A custom status.
	return $statuses;
} );
```

### `spcw_month_result`

The final figures for one month. They're also used for the yearly totals and the CSV. Keys:

`order_count`, `gross`, `refunds`, `tax`, `net_sales`, `shipping_charged`, `cogs`, `items_missing_cost`, `payment_fees`, `fee_breakdown` (id ⇒ `label`, `gross`, `orders`, `percent`, `fixed`, `amount`, `unset`), `expenses` (category key ⇒ amount), `expenses_total`, `total_costs`, `profit`, `margin` (float or null), `notes`.

If you change amounts, keep `total_costs`, `profit` and `margin` consistent:

```php
// Add a fixed monthly salary that isn't entered by hand.
add_filter( 'spcw_month_result', function ( $r ) {
	$r['total_costs'] += 3000;
	$r['profit']      -= 3000;
	$r['margin']       = SPCW_Calculator::margin( $r['profit'], $r['net_sales'] );
	return $r;
} );
```

## Caching

- Aggregates are cached per month in transients keyed with the **cache version**.
- **Per-month invalidation:** `woocommerce_new_order`, `woocommerce_update_order`, `woocommerce_order_status_changed`, `woocommerce_order_refunded`, `woocommerce_before_delete_order`, `woocommerce_before_trash_order`, plus `before_delete_post`/`wp_trash_post` for legacy storage. Changed months are collected during the request and deleted on `shutdown`.
- **Global invalidation** (cache version + 1): saving settings, **Refresh data**, and any change to a cost meta key (`added/updated/deleted_post_meta`; at most once per request). Old-version rows are purged from `wp_options` (max 1 000 per run, throttled to once a minute; skipped with a persistent object cache, where entries expire on their own).
- Future months are never queried.

## Order queries

- `WC_Order_Query` date filtering is avoided (it returned no results on some WooCommerce 10.x sites).
- **Legacy storage:** `WP_Query` on `shop_order` with a `date_query` on `post_date` (store-local time), inclusive from the 1st 00:00:00 to the last second of the month.
- **HPOS:** a prepared query on `{prefix}wc_orders` using `date_created_gmt`. The month boundaries are built in the store's timezone (`wp_timezone()`) and each converted to UTC on its own date, so daylight-saving changes are handled.
- Orders are loaded with `wc_get_order()`. Every 200 orders, the in-memory object cache is flushed if memory use exceeds 60 % of `memory_limit`.

## Coding rules

These are enforced in review and CI. They match the WordPress.org plugin guidelines.

- **Input:** read `$_POST` only through `SPCW_Admin::posted()` (`map_deep( wp_unslash(), sanitize_* )`), then validate (whitelists, `absint`, `parse_amount()`, length/count limits in `SPCW_Settings::MAX_*`).
- **Output:** escape at the point of output: `esc_html`, `esc_attr`, `esc_url`, `esc_textarea`; money with `wp_kses_post( SPCW_Admin::money() )`.
- **Requests:** every `admin_post_*` handler calls `check_capability()` and `check_admin_referer()` first, and ends with `wp_safe_redirect()` + `exit` (or a download).
- **SQL:** `$wpdb->prepare()` for everything with a variable, `%i` for table names.
- **Naming:** `SPCW_` / `spcw_` prefix for classes, constants, options, transients, hooks and globals in templates.
- **i18n:** every user-facing string goes through `__()`/`esc_html__()` etc. with the text domain `simple-profit-calculator-for-woocommerce`.
- **No external requests**, no tracking, no remote assets.

## Testing

### Static checks

```bash
composer global require wp-coding-standards/wpcs:^3 phpcompatibility/phpcompatibility-wp
phpcs --standard=WordPress --extensions=php --ignore=tests .
phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- --extensions=php .
```

### Integration tests

`tests/run-tests.php` runs inside a real WordPress + WooCommerce (latest) in [WordPress Playground](https://wordpress.github.io/wordpress-playground/), seeded by `tests/seed.php`. It covers the v1 import (including its security gates), profit maths, refunds, fees, DST month boundaries, caching, storage limits, input parsing, CSV injection, permissions, filters, COGS modes and uninstall.

```bash
npx @wp-playground/cli@3.1.55 run-blueprint --blueprint=tests/blueprint.json \
  --mount=.:/wordpress/wp-content/plugins/simple-profit-calculator-for-woocommerce \
  --mount=./tests:/tests
cat tests/results.txt
```

For HPOS, change `'woocommerce_custom_orders_table_enabled', 'no'` to `'yes'` in a copy of the blueprint. CI runs both storages on PHP 8.1 and 8.3.

> Playground's PHP 7.4 build crashes when WooCommerce completes an order, even without this plugin, so PHP 7.4 is covered by syntax and compatibility checks rather than integration tests. Playground uses SQLite, so the tests avoid MySQL-only statements such as `RENAME TABLE`.

### CI

`.github/workflows/ci.yml` runs on every push and pull request:

1. PHP syntax on 7.4 – 8.4
2. WordPress Coding Standards + PHP 7.4 compatibility
3. Integration tests (legacy + HPOS × PHP 8.1, 8.3)
4. The official [WordPress Plugin Check](https://github.com/WordPress/plugin-check-action) on the distributable files

## Releasing

1. Bump `Version` in the main file, `SPCW_VERSION`, and `Stable tag` in `readme.txt`; add a changelog entry to `readme.txt` and `CHANGELOG.md`.
2. If the table schema changes, bump `SPCW_DB::VERSION`. `dbDelta()` applies the change on the next request.
3. Push and wait for CI to pass.
4. `bin/build-zip.sh`, then `gh release create vX.Y.Z ../simple-profit-calculator-for-woocommerce.zip`.
5. WordPress.org: copy the zip contents to SVN `trunk/`, tag `tags/X.Y.Z`, and copy `.wordpress-org/*` to SVN `assets/`.
