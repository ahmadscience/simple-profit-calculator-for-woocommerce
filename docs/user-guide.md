# User guide

Simple Profit Calculator shows what your WooCommerce store actually **earned** each month, after product costs, payment fees and your running costs.

- [1. Install](#1-install)
- [2. Set up (5 minutes)](#2-set-up-5-minutes)
- [3. Enter your monthly costs](#3-enter-your-monthly-costs)
- [4. Read the dashboard](#4-read-the-dashboard)
- [5. How every number is calculated](#5-how-every-number-is-calculated)
- [6. Export to CSV](#6-export-to-csv)
- [7. Who can see and change what](#7-who-can-see-and-change-what)
- [8. Troubleshooting](#8-troubleshooting)
- [9. Upgrading from "Profit Calculator" 1.x](#9-upgrading-from-profit-calculator-1x)
- [10. Removing the plugin](#10-removing-the-plugin)

---

## 1. Install

Requirements: WordPress 6.3+, WooCommerce 7.0+, PHP 7.4+.

1. **Plugins → Add New**, search for *Simple Profit Calculator for WooCommerce*, then **Install** and **Activate**.
   Or download the zip from [GitHub releases](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/releases) and use **Plugins → Add New → Upload Plugin**.
2. A new **Profit** menu appears in the admin sidebar.

## 2. Set up (5 minutes)

Open **Profit → Settings**.

### 2.1 Product cost (cost of goods sold)

Choose **one** method:

| Method | Use it when | What you do |
|---|---|---|
| **Cost price per product** *(recommended)* | You know what each product costs you | Nothing, if you already use a Cost of Goods plugin. Otherwise enter a cost on each product (see below). |
| **One amount per month** | You only know your monthly supplier invoices | Enter the total on **Profit → Monthly costs** in the *Product cost (COGS)* column. |
| **Percentage of sales** | You want a quick estimate | Enter e.g. `40` for "products cost me about 40% of what I sell them for". |

**Where cost prices come from** (only for *Cost price per product*):

- **Automatic** *(default)* uses the first source that has a cost for the product:
  1. the *Cost of Goods* plugin by WPFactory (`_alg_wc_cog_cost`),
  2. the *Cost of Goods* plugin by SkyVerge (`_wc_cog_cost`),
  3. WooCommerce's built-in Cost of Goods feature (when enabled in WooCommerce → Settings → Advanced → Features),
  4. this plugin's own **Cost price** field.
- If none of those plugins is found, a **Cost price** field is added to every product (**Products → Edit → General**) and every variation. A variation without its own cost uses the parent product's cost.
- **Custom product meta key**: any other plugin that stores the cost in a product field. Enter the field's meta key.

Enter cost prices **excluding VAT**, per single unit.

### 2.2 Payment fees

Every payment method installed in WooCommerce is listed. For each one enter what the provider charges you:

- **Fee %**: percentage of the amount the customer paid (e.g. Stripe `2.9`).
- **Fixed fee per order**: e.g. Stripe `0.30`.

Leave `0` for methods without fees (bank transfer, cash). Orders without a payment method (e.g. free orders) never get a fee.

If you used a payment plugin in the past that is no longer installed, add its id in the last row so old orders still get the right fee. The id is shown on the order edit screen under *Payment via*, or in the order list export.

### 2.3 Monthly cost types

These are the columns on the Monthly costs page. Defaults: *Shipping / postage, Advertising, Packaging, Software & subscriptions, Accounting, Other*. Rename them, remove the ones you don't need, or add your own (e.g. *Rent*, *Salaries*). Up to 50.

*Shipping / postage* can't be removed. It's compared with what customers paid for shipping.

### 2.4 Which orders count

By default **Processing** and **Completed** orders count as sales. Cancelled, failed, pending and fully refunded orders don't. Partial refunds are always subtracted.

## 3. Enter your monthly costs

**Profit → Monthly costs** shows a whole year in one table.

- Type amounts **excluding VAT**. Any format works: `1234.50`, `1 234,50`, `1.234,50`, `$ 99`.
- An **empty** field means "no cost". `0` is also fine.
- Change **as many months as you like**, then click **Save all months** once. Only changed months are saved.
- **Copy previous** fills a row with the month above, which is handy for fixed costs like software.
- The **Total** column updates as you type.
- If you try to leave the page with unsaved changes, the browser asks first.

You can also edit a single month directly on the dashboard (right-hand panel).

## 4. Read the dashboard

**Profit → Dashboard**

- **Month** view: the three cards (net sales, total costs, profit and margin), then the full breakdown:
  - **Sales**: gross sales → refunds → tax → **net sales**
  - **Costs**: product cost, payment fees (per method), each monthly cost type
  - **Profit**
- Next to *Shipping / postage* you see what customers paid for shipping and the difference. A negative difference means shipping loses you money.
- **Year** view: all twelve months side by side with yearly totals. Click a month to open it.
- A yellow warning appears when items were sold **without a cost price**. Your profit is then higher than it really is. Follow the link to fix the costs.
- **Refresh data** recalculates from WooCommerce right away. Normally you never need it, because data refreshes automatically when an order changes.

## 5. How every number is calculated

| Line | Formula |
|---|---|
| Gross sales | Sum of order totals (including tax and shipping) of counted orders created in that month, in your store's timezone |
| Refunds | Sum of refunds on those orders |
| Tax | Order tax minus refunded tax |
| **Net sales** | Gross sales − refunds − tax |
| Product cost | *Per product:* Σ (unit cost × quantity sold − refunded quantity). *Monthly:* the amount you entered. *Percentage:* (net sales − shipping charged) × % |
| Payment fees | Per payment method: amount paid × fee % + fixed fee × number of orders |
| Monthly costs | The amounts you entered for that month |
| **Total costs** | Product cost + payment fees + monthly costs |
| **Profit** | Net sales − total costs |
| **Margin** | Profit ÷ net sales × 100 |

**Worked example.** Mugs sell for €125 and cost you €40 each. No tax in this example.

- Order A: 2 mugs = €250, paid by bank transfer (0 % fee). One mug was refunded (€125).
- Order B: 1 mug = €125, paid by card (2.5 % fee).
- Monthly costs entered: shipping €150.

| | |
|---|---|
| Net sales | 375 − 125 − 0 = **250.00** |
| Product cost | (3 sold − 1 refunded) × 40 = **80.00** |
| Payment fees | 250 × 0 % + 125 × 2.5 % = **3.13** |
| Total costs | 80 + 3.13 + 150 = **233.13** |
| Profit | 250 − 233.13 = **16.87** (margin 6.7 %) |

Good to know:

- An order belongs to the month it was **created** in, using the timezone in *Settings → General*.
- Product cost uses each product's **current** cost price. Changing a cost recalculates past months too.
- Payment fees are calculated on the original amount paid. Providers usually keep their fee when you refund.

## 6. Export to CSV

On the dashboard, **Export [year] (CSV)** downloads every month plus a total row: orders, gross sales, refunds, tax, net sales, shipping charged, product cost, payment fees, each cost type, total costs, profit, margin and notes. Numbers use a dot as the decimal separator so spreadsheets read them correctly. The file opens in Excel, Numbers, LibreOffice and Google Sheets with special characters intact.

## 7. Who can see and change what

| Role | Dashboard & monthly costs | Settings | "Delete data on uninstall" |
|---|---|---|---|
| Administrator | ✅ | ✅ | ✅ |
| Shop Manager | ✅ | ✅ | ❌ (read-only) |
| Everyone else | ❌ | ❌ | ❌ |

Developers can change who has access with the `spcw_capability` filter (see the [developer guide](developer.md)).

## 8. Troubleshooting

| Problem | Fix |
|---|---|
| Profit looks too high | Look for the yellow "no cost price" warning. Products without a cost count as free. |
| Product cost is 0 | Settings → *Where cost prices come from*. Choose the plugin you actually use, or enter costs on products. The *System status* box at the bottom of Settings shows which sources were found. |
| A payment method shows "set fee" | That method has no fee configured yet. Click the link. |
| Orders appear in the wrong month | Check *Settings → General → Timezone*. |
| Numbers look out of date | Click **Refresh data**. If it keeps happening, an external tool may be changing orders directly in the database. |
| "The database table for monthly costs is missing" | Deactivate and reactivate the plugin. |
| The yearly view is slow the first time | The first load calculates every order of the year, then results are cached. Very large stores may need a higher PHP `memory_limit`/`max_execution_time` for that first load. |

Still stuck? [Open an issue](https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce/issues/new/choose).

## 9. Upgrading from "Profit Calculator" 1.x

Install and activate this plugin next to the old one. The first time an administrator or Shop Manager opens the WordPress admin, it copies:

- monthly costs (postal → *Shipping / postage*, ads → *Advertising*, accountant → *Accounting*, packing → *Packaging*, SaaS → *Software & subscriptions*) and notes,
- order statuses, the cost-price meta key and payment fees.

The old plugin's data is not changed. Check a few months, then deactivate and delete the old plugin. Expect small differences: this version subtracts partial refunds and never charges a fee on orders without a payment method.

## 10. Removing the plugin

Deactivating keeps everything. Deleting also keeps your data **unless** an administrator ticked **Settings → Data → Delete all … when the plugin is deleted**. In that case the monthly costs table, settings, cached data and the plugin's own *Cost price* field values are removed. Cost prices stored by other plugins are never touched. On multisite, each site follows its own setting.
