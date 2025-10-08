<?php
/**
 * Replace the template checkout/payment-method.php with Ledyer's payment categories.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$order_id = absint( get_query_var( 'order-pay', 0 ) );
if ( ! empty( $order_id ) ) {
	$_order = wc_get_order( $order_id );
}

Ledyer_Payments()->session()->get_session( isset( $order ) && ! empty( $order ) ? $order : null );

$payment_categories = Ledyer_Payments()->session()->get_payment_categories();
if ( ! empty( $payment_categories ) && is_array( $payment_categories ) ) {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$gateway            = $available_gateways['ledyer_payments'];
	$chosen_gateway     = $available_gateways[ array_key_first( $available_gateways ) ];

	foreach ( apply_filters( 'ledyer_payments_available_payment_categories', $payment_categories ) as $payment_category ) {
		$category_id = "ledyer_payments_{$payment_category['type']}";

		$gateway              = $available_gateways['ledyer_payments'] ?? $available_gateways[ $category_id ];
		$gateway->id          = $category_id;
		$gateway->icon        = $payment_category['assets']['urls']['logo'] ?? null;
		$gateway->title       = $payment_category['name'];
		$gateway->description = $payment_category['description'];

		// Make sure the first payment category is chosen by default.
		if ( false !== strpos( $chosen_gateway->id, 'ledyer_payments' ) || $gateway->chosen ) {
			$gateway->chosen = false;
			if ( $gateway->title === $payment_categories[ array_key_first( $payment_categories ) ]['type'] ) {
				$gateway->chosen = true;
			}
		}

		// For "Linear Checkout for WooCommerce by Cartimize" to work, we cannot output any HTML.
		if ( did_action( 'cartimize_get_payment_methods_html' ) === 0 ) {
			wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
		}
	}
}
