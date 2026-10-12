# Security model and audit

This document describes how Simple Profit Calculator protects your store, and records the security reviews done on it. To **report** a vulnerability, see [SECURITY.md](../SECURITY.md).

- [What the plugin can access](#what-the-plugin-can-access)
- [Threat model](#threat-model)
- [Controls](#controls)
- [Audit log](#audit-log)
- [Verification](#verification)
- [Known limitations](#known-limitations)

## What the plugin can access

| Data | Access |
|---|---|
| WooCommerce orders | **Read only**: totals, tax, shipping, refunds, payment method, line items. No customer names, addresses, emails or payment details are read or stored. |
| Products | Read cost-price meta. **Writes** only `_spcw_cost`, and only from the product edit screen. |
| Own data | `spcw_settings` option, `{prefix}spcw_monthly_costs` table, `spcw_*` transients. |
| Network | **None.** No external requests, no telemetry, no remote scripts, styles or fonts. |
| Front end | Nothing is output on the front end. No shortcodes, blocks, REST routes or AJAX actions. |

Personal data: the plugin stores no personal data, so it needs no privacy exporter or eraser. Free-text notes on monthly costs are business notes entered by staff.

## Threat model

| Actor | Can do | Must not be able to |
|---|---|---|
| Logged-out visitor | Nothing | Reach any plugin page, handler or data; trigger the v1 import |
| Customer / subscriber | Nothing | Same as above |
| Shop Manager (`manage_woocommerce`) | View profit, edit monthly costs and settings, export CSV | Opt in to data deletion; run code; read data unrelated to profit |
| Administrator | Everything above + data-deletion opt-in | n/a |
| Attacker via a staff member's browser | n/a | Make a logged-in user change data (CSRF); run script in the admin (XSS); inject formulas into exported spreadsheets |
| Another plugin on the same site | n/a | Have its data silently imported or deleted by this plugin |

## Controls

| Risk | Control | Where |
|---|---|---|
| Unauthorised access | `current_user_can( spcw_capability )` on every page and every handler. No `nopriv` handlers, no REST/AJAX endpoints. | `SPCW_Admin::render()`, `check_capability()` |
| CSRF | Unique nonce per form, `check_admin_referer()` in every handler before anything else runs. State changes only via POST. | `admin/class-spcw-admin.php` |
| Stored / reflected XSS | All input sanitised at read time (`map_deep` + `sanitize_text_field`/`sanitize_textarea_field`); all output escaped at the point of output (`esc_html`, `esc_attr`, `esc_url`, `esc_textarea`, `wp_kses_post` for prices). JS uses `textContent` only, no `innerHTML`/`eval`; no inline scripts. | views, `admin.js` |
| SQL injection | Every query with a variable uses `$wpdb->prepare()`, `%i` for table names; order statuses are whitelisted against WooCommerce's list. | `includes/` |
| Open redirect | Only `wp_safe_redirect()` to the plugin's own admin URLs; redirect targets are whitelisted, not taken from input. | handlers |
| CSV / formula injection | Text cells starting with `= + - @ \| %` (also after leading whitespace/tabs/newlines) are prefixed with `'`; real numbers stay numeric. Download sent with `nosniff` and `no-cache` headers. | `SPCW_Admin::csv_safe()` |
| Privilege escalation to data loss | Only users with `manage_options` **and** `delete_plugins` can opt in to "delete data on uninstall"; Shop Managers see it read-only and their submissions can't change it. | `SPCW_Settings::can_manage_data()` |
| Importing another plugin's data | v1 import only runs if the v1 table exists **with the exact v1 columns**, only for logged-in users with access, never in AJAX; imported values are validated and clamped. | `SPCW_Migration` |
| Oversized input / resource abuse | Limits: amounts ±9 999 999 999.99, notes 1 000 chars, labels 60 chars, 50 cost types, 100 payment methods. Memory guard while aggregating large months. Stale cache rows purged. | `SPCW_Settings::MAX_*`, `SPCW_Orders` |
| Direct file access | Every PHP file starts with `defined( 'ABSPATH' ) \|\| exit;` (`WP_UNINSTALL_PLUGIN` for `uninstall.php`). | all files |
| Data loss on uninstall | Nothing is deleted unless an administrator opted in, per site on multisite. Other plugins' cost meta is never touched. | `uninstall.php` |
| Race conditions | Month rows written with one atomic `INSERT … ON DUPLICATE KEY UPDATE`. | `SPCW_DB::save_month()` |

## Audit log

### 2.0.1: in-depth review (2026-10)

Manual review of every file from an attacker's point of view, beyond what automated tools detect.

| # | Severity | Finding | Fix |
|---|---|---|---|
| F1 | Medium | Shop Managers could enable "delete data on uninstall", leading to permanent data loss when an administrator deletes the plugin. | Requires `manage_options` + `delete_plugins`; read-only for others. |
| F2 | Medium | The v1 import keyed on generic names (`pc_settings`, `pc_gateway_fees`, `profit_monthly_data`) and could import another plugin's data into the settings. | Import requires the v1 table with its exact columns; option values validated. |
| F3 | Low | The import ran on `admin_init`, which also fires for logged-out `admin-ajax.php`/`admin-post.php` requests. | Requires a logged-in user with access; skipped for AJAX. |
| F4 | Low | Multisite: uninstall only processed the main site. | Iterates all sites, each with its own opt-in. |
| F5 | Low | Select-then-insert when saving a month could fail under concurrent saves. | Atomic upsert. |
| F6 | Low | No limits on note length, number of cost types/payment methods, or negative amounts. | Limits added. |
| F7 | Low | Each cache invalidation left old rows in `wp_options` for up to 30 days. Bulk cost imports could create thousands. | Throttled purge of stale rows. |
| F8 | Low | CSV formula guard bypassable with leading whitespace; no `nosniff` header. | Guard checks after whitespace and more trigger characters; header added. |
| F9 | Low | Very large months could exhaust `memory_limit`. | Runtime cache flushed when memory use is high. |
| F10 | Info | No Cart/Checkout blocks compatibility declaration, so WooCommerce could show false warnings. | Declared. |

### 2.0.0: WordPress.org guideline review (2026-10)

- Input sanitised at read time instead of later; escaping moved to the point of output; table names via `%i`; uninstall via `delete_post_meta_by_key()`.
- Compared with v1, removed: a diagnostics page that reset PHP OPcache for the whole server, enabled `display_errors`, and overwrote user settings; unescaped output; unvalidated `$_GET`/`$_POST`; `wp_redirect`.

## Verification

Every release must pass, in CI (`.github/workflows/ci.yml`):

- **WordPress Coding Standards** (`WordPress` ruleset, which includes the security sniffs for escaping, sanitising, nonces and SQL): 0 errors.
- **PHPCompatibilityWP** for PHP 7.4+: 0 errors.
- **Official WordPress Plugin Check** on the distributable files: 0 errors.
- **Integration tests** in real WordPress + WooCommerce, legacy and HPOS storage. Security-specific tests:
  - the import does not run for logged-out users or customers,
  - foreign data with v1-like names is not imported,
  - customers have no access; Shop Managers can't opt in to data deletion,
  - CSV formula payloads (`=`, `+`, `@`, `|`, `-`, leading space/tab/newline) are neutralised,
  - HTML in amounts is stripped; amounts and notes are clamped,
  - uninstall keeps data unless opted in, and never touches other plugins' meta.

Also verified manually in the browser: a forged request without a valid nonce returns HTTP 403 and changes nothing; `<script>` and `<img onerror>` payloads in notes and cost-type names are stripped or rendered inert.

## Known limitations

- Access is all-or-nothing per capability: anyone who can use the plugin can see all profit figures and edit costs. Use the `spcw_capability` filter to restrict it further.
- The product cost field is written through WooCommerce's product save, which checks WooCommerce's own nonce and the user's `edit_product` permission.
- WooCommerce's authenticated REST API returns product meta (including `_spcw_cost`) to API users with product read access, as it does for other Cost of Goods plugins.
