=== Patsch9 Orderdate for WooCommerce ===
Contributors: patsch9
Tags: woocommerce, pickup, order date, collection
Requires at least: 6.9.5
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 2026.11.1-beta1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Choose a pickup date per product or at checkout, with weekdays, specific dates, blackout dates and preparation time.

== Description ==

Patsch9 Orderdate adds product-specific collection dates to WooCommerce. Configure date selection on the product page or choose a common date at checkout.

* Availability start and end dates.
* Recurring weekdays, specific calendar dates, or both.
* Global and product-specific blackout dates.
* Preparation time in calendar days and a daily order cutoff.
* A bounded advance booking window.
* Calendar with unavailable days disabled and an alternative date list.
* Current rules are validated on the server when an order is placed.
* Pickup dates appear in the cart, orders, administration and emails.
* Classic checkout, Checkout Blocks and HPOS support.

Supports simple and variable products. Variations inherit the parent product rules. Requires WooCommerce 10.9.4 or newer.

Use classic product pages for product-level date selection. Product Add-to-Cart Blocks, the new product editor and express checkout integrations do not include a dedicated product date selector. Purchase requests without a required date are rejected.

No time slots, appointment capacity, multiple pickup locations, automatic public holidays or shipping method are included. Date selection applies to enabled products independently of the shipping method; configure pickup shipping separately in WooCommerce.

No external services, telemetry or CDN assets. Dates are stored locally with WooCommerce orders and follow their retention and privacy processes. Uninstallation preserves configuration and historical order dates.

WooCommerce is a trademark of Automattic Inc. This independent extension is not affiliated with, endorsed by or supported by Automattic.

German setup and development documentation is available in the repository README.md.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the installable release ZIP and activate this plugin.
3. Configure the booking horizon and global blackout dates under WooCommerce > Abholtermine.
4. Edit a product, open Product data > Abholtermine and configure the selection mode and availability.

The Checkout Block date selector is inserted at render time if missing. The saved checkout page is not modified. You can also place the Abholdatum block manually inside the Checkout fields block.

== Frequently Asked Questions ==

= What happens when different products have different rules? =

Checkout offers the intersection of all products configured for checkout-level date selection. Dates already selected on product pages remain specific to their order lines. If no common checkout date exists, customers must change the cart or place separate orders.

= How do specific dates work? =

Specific-dates mode permits only listed dates. Combined mode permits selected weekdays or listed dates. Blackout dates, availability boundaries, preparation time and the booking horizon always apply.

= How is preparation time calculated? =

In calendar days using the WordPress timezone. At or after the daily cutoff, one additional calendar day is required. This does not count business hours or working days.

= Does this plugin force a pickup shipping method? =

No. Date selection applies independently of shipping methods. Configure local pickup separately in WooCommerce when needed.

== Changelog ==

= 2026.11.1-beta1 =
* Initial development version: product and checkout pickup dates, calendar rules, HPOS and Checkout Block integration.

== Upgrade Notice ==

= 2026.11.1-beta1 =
First prerelease. Test with your theme, checkout and payment methods before production use.
