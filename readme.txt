=== Patsch9 Pickup- and Orderdate Manager ===
Contributors: patsch9
Tags: woocommerce, pickup, order date, collection
Requires at least: 6.9.5
Tested up to: 7.1
Requires PHP: 8.2
Requires Plugins: woocommerce
Stable tag: 2026.11.1-beta3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage pickup and order dates in WooCommerce, with per-product and checkout scheduling, collection management and payment at pickup.

== Description ==

Patsch9 Pickup- and Orderdate Manager adds product-specific pickup dates and a pickup workflow to WooCommerce. Configure a date on the product page or choose a common date at checkout, manage open pickups and notify customers.

* Availability start and end dates.
* Recurring weekdays, specific calendar dates, or both.
* Global and product-specific blackout dates.
* Preparation time in calendar days and a daily order cutoff.
* A bounded advance booking window.
* Global or product-specific pickup time windows with weekday overrides.
* Shared overlapping windows at checkout, with started same-day windows excluded.
* Calendar with unavailable days disabled and an alternative date list.
* Current rules are validated on the server when an order is placed.
* Pickup dates appear in the cart, orders, administration and emails.
* Classic checkout, Checkout Blocks and HPOS support.

Supports simple and variable products. Variations inherit the parent product rules. Requires WooCommerce 10.9.4 or newer.

Use classic product pages for product-level date selection. Product Add-to-Cart Blocks, the new product editor and express checkout integrations do not include a dedicated product date selector. Purchase requests without a required date are rejected.

No appointment capacity, automatic public holidays or shipping method are included. Date selection applies to enabled products independently of the shipping method; configure pickup shipping separately in WooCommerce.

No external services, telemetry or CDN assets. Dates are stored locally with WooCommerce orders and follow their retention and privacy processes. Uninstallation preserves configuration and historical order dates.

WooCommerce is a trademark of Automattic Inc. This independent extension is not affiliated with, endorsed by or supported by Automattic.

German setup and development documentation is available in the repository README.md.

Absolute Bestellfrist: Einfache Artikel und einzelne Produktvarianten können unabhängig von Versand- und Abholart mit einem optionalen Datum samt Uhrzeit ("Bestellbar bis") versehen werden. Ab Fristablauf verhindert eine serverseitige Prüfung das Hinzufügen und den Checkout, auch bei schon gefülltem Warenkorb.

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

= 2026.11.1 =
* Add product- and checkout-level pickup dates with weekday, specific-date, blackout, preparation-time and booking-horizon rules.
* Add global and product-specific pickup time windows, weekday overrides, overlapping-window intersection and server-side date/time validation.
* Add the pickup manager with open pickups, per-line rescheduling, protected customer confirmation links and WooCommerce pickup-location snapshots.
* Add the WooCommerce order statuses Ready for pickup and Collected with matching customer emails.
* Add payment at pickup for classic checkout and Checkout Blocks. Availability can be allowed or denied per product and inherited from product categories; mixed carts require every item to be allowed.
* Harden status transitions for HPOS, bulk and programmatic saves; rescheduling confirmation pages are explicitly non-cacheable.
* Align payment-at-pickup visibility with pickup-location eligibility and reject stale time-window values after rule changes.
* Deduplicate variation rules by parent product and localize Checkout Block time-window labels consistently.
* Add integration and regression coverage for classic order storage, HPOS, Store API, payment eligibility, status guards and customer confirmation flows.

= 2026.11.1-beta3 =
* Vor Ort zahlen für klassischen Checkout und Checkout Block.
* Abholmanager mit offenen Abholungen, Umplanung und Kundenbestätigung.
* WooCommerce-Status Bereit zur Abholung / Abgeholt mit E-Mails im Shopdesign.
* Übernahme der WooCommerce-Abholorte und historischer Adressdaten.
* Integrationstests für beide Bestellspeicherarten.

= 2026.11.1-beta2 =
* Add optional pickup time windows with global defaults, per-product and weekday overrides.
* Intersect checkout windows and validate date/time selections server-side.
* Store windows in cart lines, orders and emails; preserve date-only configurations.

= 2026.11.1-beta1 =
* Initial development version: product and checkout pickup dates, calendar rules, HPOS and Checkout Block integration.

== Upgrade Notice ==

= 2026.11.1 =
Payment at pickup is opt-in per product or category and remains disabled unless all cart items are eligible. Activate the gateway under WooCommerce payments if required and test pickup statuses, emails and checkout on staging.

= 2026.11.1-beta3 =
Activate payment at collection under WooCommerce payments if required. Existing orders are indexed in background batches. Test status emails and checkout on staging.

= 2026.11.1-beta2 =
Existing products inherit global windows when configured. An empty global schedule preserves date-only selection. Test with your checkout and payment methods.

= 2026.11.1-beta1 =
First prerelease. Test with your theme, checkout and payment methods before production use.
