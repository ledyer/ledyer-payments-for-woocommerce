<?php
/**
 * Replace the template checkout/payment-method.php with Ledyer's payment categories.
 */

use Krokedil\Ledyer\Payments\Gateway;

$order_id = absint( get_query_var( 'order-pay', 0 ) );
if ( ! empty( $order_id ) ) {
	$order = wc_get_order( $order_id );
}

$payment_categories = Ledyer()->session()->get_payment_categories();
if ( ! empty( $payment_categories ) && is_array( $payment_categories ) ) {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$gateway            = $available_gateways[ Gateway::ID ];
	$chosen_gateway     = $available_gateways[ array_key_first( $available_gateways ) ];

	foreach ( apply_filters( Gateway::ID . '_available_payment_categories', $payment_categories ) as $payment_category ) {
		$gateway = $available_gateways[ Gateway::ID ];

		// Refer to the note about gateway ID in the Plugin.php file, for the 'woocommerce_checkout_posted_data' hook.
		$gateway->id           = Gateway::ID . "_{$payment_category['type']}";
		$gateway->title        = $payment_category['name'];
		$gateway->description  = $payment_category['description'];
		$payment_category_icon = $payment_category['assets']['urls']['logo'] ?? null;

		// Make sure the first payment category is chosen by default.
		if ( false !== strpos( $chosen_gateway->id, Gateway::ID ) || $gateway->chosen ) {
			$gateway->chosen = false;
			if ( $gateway->title == $payment_categories[ array_key_first( $payment_categories ) ]['name'] ) {
				$gateway->chosen = true;
			}
		}

		// For "Linear Checkout for WooCommerce by Cartimize" to work, we cannot output any HTML.
		if ( did_action( 'cartimize_get_payment_methods_html' ) === 0 ) {
			wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
		}
	}
}
