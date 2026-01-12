<?php
/**
 * Replace the template checkout/payment-method.php with Ledyer's payment categories.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ledyer_payments_wc_order_id = absint( get_query_var( 'order-pay', 0 ) );
if ( ! empty( $ledyer_payments_wc_order_id ) ) {
	$ledyer_payments_wc_order = wc_get_order( $ledyer_payments_wc_order_id );
}

Ledyer_Payments()->session()->get_session( isset( $ledyer_payments_wc_order ) && ! empty( $ledyer_payments_wc_order ) ? $ledyer_payments_wc_order : null );

$ledyer_payments_payment_categories = Ledyer_Payments()->session()->get_payment_categories();
if ( ! empty( $ledyer_payments_payment_categories ) && is_array( $ledyer_payments_payment_categories ) ) {
	$ledyer_payments_wc_available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	$ledyer_payments_wc_gateway            = $ledyer_payments_wc_available_gateways['ledyer_payments'];
	$ledyer_payments_wc_chosen_gateway     = $ledyer_payments_wc_available_gateways[ array_key_first( $ledyer_payments_wc_available_gateways ) ];

	foreach ( apply_filters( 'ledyer_payments_available_payment_categories', $ledyer_payments_payment_categories ) as $ledyer_payments_payment_category ) {
		$ledyer_payments_category_id = "ledyer_payments_{$ledyer_payments_payment_category['type']}";

		$ledyer_payments_wc_gateway              = $ledyer_payments_wc_available_gateways['ledyer_payments'] ?? $ledyer_payments_wc_available_gateways[ $ledyer_payments_category_id ];
		$ledyer_payments_wc_gateway->id          = $ledyer_payments_category_id;
		$ledyer_payments_wc_gateway->icon        = $ledyer_payments_payment_category['assets']['urls']['logo'] ?? null;
		$ledyer_payments_wc_gateway->title       = $ledyer_payments_payment_category['name'];
		$ledyer_payments_wc_gateway->description = $ledyer_payments_payment_category['description'];

		// Make sure the first payment category is chosen by default.
		if ( false !== strpos( $ledyer_payments_wc_chosen_gateway->id, 'ledyer_payments' ) || $ledyer_payments_wc_gateway->chosen ) {
			$ledyer_payments_wc_gateway->chosen = false;
			if ( $ledyer_payments_wc_gateway->title === $ledyer_payments_payment_categories[ array_key_first( $ledyer_payments_payment_categories ) ]['type'] ) {
				$ledyer_payments_wc_gateway->chosen = true;
			}
		}

		// For "Linear Checkout for WooCommerce by Cartimize" to work, we cannot output any HTML.
		if ( did_action( 'cartimize_get_payment_methods_html' ) === 0 ) {
			wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $ledyer_payments_wc_gateway ) );
		}
	}
}
