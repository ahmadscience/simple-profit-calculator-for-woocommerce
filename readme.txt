=== Simple Profit Calculator for WooCommerce ===
Contributors: ahmadscience
Tags: woocommerce, profit, cost of goods, reports, accounting
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See your real monthly and yearly profit: sales minus product costs, payment fees and your own monthly expenses.

== Description ==

WooCommerce shows you what you sold. This plugin shows you what you **earned**.

For every month it takes your WooCommerce sales, subtracts refunds and tax, and then subtracts:

* **Product cost (COGS)**, using whichever method suits your store:
  * cost prices from WooCommerce's built-in Cost of Goods feature, or from the popular Cost of Goods plugins by WPFactory or SkyVerge (detected automatically),
  * a "Cost price" field this plugin adds to products and variations when no other cost plugin is installed,
  * a single lump sum per month (for example your supplier invoices), or
  * a percentage of sales, for a quick estimate.
* **Payment fees** per payment method: a percentage and/or a fixed fee per order. Every payment method installed in your store is listed automatically.
* **Your own monthly costs**: shipping/postage, advertising, packaging, software, accounting… Rename them or add your own.

= Features =

* Monthly view with a clear breakdown, and a yearly overview with month-by-month totals.
* Enter the costs for a whole year on one screen and save them all at once. Copy last month's costs with one click.
* See how much shipping you charged customers compared with what it actually cost.
* Warns you when items were sold without a cost price, so your profit isn't silently overstated.
* Partial refunds are subtracted from revenue, tax and product cost.
* CSV export of any year (opens correctly in Excel and Google Sheets).
* Works with both HPOS and legacy order storage. Uses your store's currency, timezone and number format.
* Fast: results are cached and refreshed automatically when orders change.
* Read-only towards your store: it never changes orders or products (except saving the optional cost price field).
* Translation ready.

= Bugs, feature requests and security =

* Report bugs and request features on [GitHub Issues](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/issues).
* Report security vulnerabilities **privately** through [GitHub security advisories](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/security/advisories/new). Please don't post them in public issues or the support forum.
* Source code and development happen on [GitHub](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce).

= Privacy =

This plugin does not send any data anywhere, does not track you and does not load anything from external servers. All data stays in your WordPress database.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin through **Plugins → Add New → Upload Plugin**, or install it from the WordPress plugin directory, and activate it.
3. Go to **Profit → Settings** and choose how product costs should be found, and enter your payment fees.
4. Go to **Profit → Monthly costs** and enter your costs.
5. Open **Profit → Dashboard**.

== Frequently Asked Questions ==

= Do I need a Cost of Goods plugin? =

No. If none is installed, the plugin adds a "Cost price" field to your products. You can also skip per-product costs entirely and enter one amount per month, or use a percentage of sales.

= Which Cost of Goods plugins are supported? =

WooCommerce's own Cost of Goods feature, "Cost of Goods: Product Cost & Profit Calculator for WooCommerce" by WPFactory, and "Cost of Goods for WooCommerce" by SkyVerge. Any other plugin that stores the cost in a product meta field works too: choose "Custom product meta key" in Settings.

= Is product cost taken from the time of the sale? =

The current cost price of each product is used. If you change a product's cost price, past months are recalculated with the new price.

= Should I enter costs with or without VAT? =

Without VAT. Sales are also shown without VAT, so profit is comparable.

= Which orders are counted? =

Orders with the statuses you choose in Settings (Processing and Completed by default), placed in that month in your store's timezone. Partial refunds are subtracted.

= My numbers look out of date. =

Click **Refresh data** on the dashboard. Data is normally refreshed automatically when an order changes.

= Who can see the profit pages? =

Users with the "manage_woocommerce" capability (Administrators and Shop Managers). Developers can change this with the `spcw_capability` filter.

= Where do I report a bug or ask for a feature? =

On [GitHub Issues](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/issues). Security problems: please use a [private security advisory](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/security/advisories/new) instead.

= What happens to my data when I delete the plugin? =

It is kept unless you tick "Delete all … when the plugin is deleted" under Settings → Data.

== Changelog ==

= 2.0.0 =
* Complete rewrite for public release.
* New: product cost from WooCommerce Cost of Goods, WPFactory, SkyVerge, a built-in cost field, a monthly lump sum or a percentage.
* New: custom monthly cost types.
* New: fixed per-order payment fees; all installed payment methods listed automatically.
* New: CSV export, "Copy previous month", missing-cost warnings, refresh button.
* Fixed: Monthly costs page saved only the row whose button was clicked. All months are now saved together.
* Fixed: orders without a payment method were charged another gateway's fee.
* Fixed: partial refunds were not subtracted.
* Fixed: new orders could take up to 6 hours to appear.
* Fixed: HPOS stores counted orders near midnight in the wrong month during daylight saving time.
* Fixed: amounts like "1.234,56" were saved incorrectly.
* Security: escaped all output, validated all input, removed the diagnostics page.
* Data from version 1 ("Profit Calculator") is imported automatically.

== Upgrade Notice ==

= 2.0.0 =
Major update. Your existing monthly costs and settings are imported automatically from version 1.
