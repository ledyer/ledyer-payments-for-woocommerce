<?php
/**
 * Class Assets.
 *
 * Assets management.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		$settings = get_option( 'woocommerce_' . Gateway::ID . '_settings' );
		if ( ! wc_string_to_bool( $settings['enabled'] ) ) {
			return;
		}

		if ( ! ( is_checkout() || is_order_received_page() ) ) {
			return;
		}

		$standard_woo_checkout_fields = array(
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_city',
			'billing_phone',
			'billing_email',
			'billing_state',
			'billing_country',
			'billing_company',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_postcode',
			'shipping_city',
			'shipping_state',
			'shipping_country',
			'shipping_company',
			'terms',
			'terms-field',
			'_wp_http_referer',
		);

		$src          = LP_PLUGIN_URL . '/assets/js/checkout.js';
		$dependencies = array( 'jquery' );
		wp_register_script( 'ledyer-payments-for-woocommerce', $src, $dependencies, LP_VERSION, false );

		$pay_for_order = is_wc_endpoint_url( 'order-pay' ) ? true : false;
		wp_localize_script(
			'ledyer-payments-for-woocommerce',
			'KLPParams',
			array(
				'changePaymentMethodNonce'  => wp_create_nonce( Gateway::ID . '_wc_change_payment_method' ),
				'changePaymentMethodUrl'    => \WC_AJAX::get_endpoint( Gateway::ID . '_wc_change_payment_method' ),
				'getOrderNonce'             => wp_create_nonce( Gateway::ID . '_get_order' ),
				'getOrderUrl'               => \WC_AJAX::get_endpoint( Gateway::ID . '_get_order' ),
				'logToFileNonce'            => wp_create_nonce( Gateway::ID . '_wc_log_js' ),
				'logToFileUrl'              => \WC_AJAX::get_endpoint( Gateway::ID . '_wc_log_js' ),
				'payForOrder'               => $pay_for_order,
				'standardWooCheckoutFields' => $standard_woo_checkout_fields,
				'submitOrder'               => \WC_AJAX::get_endpoint( 'checkout' ),
			)
		);

		wp_enqueue_script( 'ledyer-payments-for-woocommerce' );

		$env = wc_string_to_bool( $settings['test_mode'] ) ? 'sandbox' : 'live';
		wp_enqueue_script( 'ledyer-payments-bootstrap', "https://payments.$env.ledyer.com/bootstrap.js", array( 'ledyer-payments-for-woocommerce' ), LP_VERSION, true );
	}
}
