=== Ledyer Payments for WooCommerce ===
Contributors: ledyerdevelopment, krokedil
Tags: woocommerce, ledyer, ecommerce, e-commerce
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 5.6.0
WC tested up to: 9.8.5
Stable tag: 1.0.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Adds support for B2B payments and invoicing via Ledyer in WooCommerce.

== Description ==

Ledyer Payments for WooCommerce adds support for B2B payments via Ledyer. It enables invoice payments for all customers, regardless of credit status. Ledyer handles credit checks, collections, and payment administration.

The plugin integrates directly with the Ledyer API and supports fallback options such as pre-invoicing when credit limits are exceeded. You can also configure flexible payment terms per customer.

Requires an active Ledyer merchant account.

== Installation ==

1. Upload the plugin to the /wp-content/plugins/ledyer-payments-for-woocommerce directory or install it via the WordPress plugin admin panel.
2. Activate the plugin through the “Plugins” menu in WordPress.
3. Go to **WooCommerce > Settings > Payments** and enable **Ledyer Payments**.
4. Enter your API credentials provided by Ledyer.
5. Save your settings and start accepting B2B payments.

== Frequently Asked Questions ==

= Do I need a Ledyer account? =
Yes. The plugin requires an active Ledyer merchant account to function.

= Is sandbox/test mode supported? =
Yes. The plugin supports Ledyer’s test environment using sandbox credentials.

== Screenshots ==

1. Ledyer Payments settings page in WooCommerce admin.
2. Ledyer as a payment option during checkout.

== Changelog ==

= 1.0.0 =
* Initial release.

== External Services ==

This plugin connects to the Ledyer Payments API to process payments and manage orders.

**Sandbox environment:**
- https://auth.sandbox.ledyer.com
- https://api.sandbox.ledyer.com
- https://checkout.sandbox.ledyer.com
- https://payments.sandbox.ledyer.com

**Live environment:**
- https://auth.live.ledyer.com
- https://api.live.ledyer.com
- https://checkout.live.ledyer.com
- https://payments.live.ledyer.com

Data is transmitted:
- when a customer selects Ledyer as a payment method,
- when an order is placed,
- during authentication,
- and when orders are updated or refunded.


The data includes order details, customer information, and payment amounts.

Privacy Policy: https://static.ledyer.com/docs/ALL/en-US/privacy_policy.pdf
Terms of Service: https://static.ledyer.com/docs/SE/en-US/payments_terms.pdf

== License ==

This plugin is licensed under the GNU General Public License v3. See license.txt for details.
