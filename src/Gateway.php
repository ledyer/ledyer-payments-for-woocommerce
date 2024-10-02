<?php
/**
 * Class Gateway.
 *
 * Register the Ledyer Payments payment gateway.
 */

namespace Krokedil\Ledyer\Payments;

use Krokedil\Ledyer\Payments\Requests\Helpers\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gateway extends \WC_Payment_Gateway {

	public const ID = 'ledyer_payments';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = self::ID;
		$this->method_title       = __( 'Ledyer Payments', 'ledyer-payments-for-woocommerce' );
		$this->method_description = __( 'Ledyer Payments', 'ledyer-payments-for-woocommerce' );
		$this->supports           = apply_filters(
			$this->id . '_supports',
			array(
				'products',
				'refunds',
			)
		);
		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		add_filter( 'wc_get_template', array( $this, 'payment_categories' ), 10, 3 );
		add_action( 'woocommerce_thankyou', array( $this, 'redirect_from_checkout' ) );
	}

	/**
	 * Initialize settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = Settings::setting_fields();

		// Delete the access token whenever the settings are modified.
		add_action( 'update_option_woocommerce_' . self::ID . '_settings', array( __NAMESPACE__ . '\Settings', 'maybe_update_access_token' ) );
	}


	/**
	 * The payment gateway icon that will appear on the checkout page.
	 *
	 * @return string
	 */
	public function get_icon() {
		$image_path = plugin_dir_url( __FILE__ ) . 'assets/img/ledyer-darkgray.svg';
		return "<img src='{$image_path}' style='max-width: 90%' alt='Ledyer Payments logo' />";
	}

	/**
	 * Whether the payment gateway is available.
	 *
	 * @filter Gateway::ID . '_is_available'
	 *
	 * @return boolean
	 */
	public function is_available() {
		return apply_filters( self::ID . '_is_available', $this->check_availability() );
	}

	/**
	 * Check if the gateway should be available.
	 *
	 * This function is extracted to create the 'Gateway::ID . _is_available' filter.
	 *
	 * @return bool
	 */
	private function check_availability() {
		return wc_string_to_bool( $this->enabled );
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id WooCommerced order id.
	 * @return array An associative array containing the success status and redirect URl.
	 */
	public function process_payment( $order_id ) {
		$helper   = new Order( wc_get_order( $order_id ) );
		$customer = $helper->get_customer();

		$order = $helper->order;
		$order->update_meta_data( '_' . self::ID . '_session_reference', Ledyer()->session()->get_reference() );
		$order->save();

		return array(
			'order_key' => $order->get_order_key(),
			'customer'  => $customer,
			'redirect'  => $order->get_checkout_order_received_url(),
			'result'    => 'success',
		);
	}

	/**
	 * Process the refund request.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param float  $amount The amount to refund.
	 * @param string $reason The reason for the refund.
	 * @return array|\WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return array();
	}

	/**
	 * Display the payment categories under the gateway on the checkout page.
	 *
	 * @param string $located Target template file location.
	 * @param string $template_name The name of the template.
	 * @param array  $args Arguments for the template.
	 * @return string
	 */
	public function payment_categories( $located, $template_name, $args ) {
		if ( ! is_checkout() ) {
			return $located;
		}

		if ( 'checkout/payment-method.php' !== $template_name || self::ID !== $args['gateway']->id ) {
			return $located;
		}

		return untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/payment-categories.php';
	}

	public function redirect_from_checkout( $order_id ) {
		$key     = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$gateway = filter_input( INPUT_GET, 'gateway', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( self::ID !== $gateway ) {
			return;
		}

		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
			'order_id' => $order_id,
			'key'      => $key,
		);
		Ledyer()->logger()->debug( 'Customer refreshed or redirected to thankyou page.', $context );

		$order = wc_get_order( $order_id );
		if ( ! hash_equals( $order->get_order_key(), $key ) ) {
			Ledyer()->logger()->error( 'Order key mismatch.', $context );
			return;
		}

		if ( ! empty( $order->get_date_paid() ) ) {
			Ledyer()->logger()->debug( 'Order already paid.', $context );
			return;
		}

		$session_id   = Ledyer()->session()->get_id();
		$ledyer_order = Ledyer()->api()->get_session( $session_id );
		if ( is_wp_error( $ledyer_order ) ) {
			Ledyer()->logger()->error( 'Failed to get Ledyer order. Unrecoverable error, aborting.', $context );
			return;
		}

		$payment_id = $ledyer_order['orderId'];
		if ( 'authorized' === $ledyer_order['state'] ) {
			$order->payment_complete( $payment_id );
		} elseif ( 'awaitingSignatory' === $ledyer_order['state'] ) {
			$order->update_status( 'on-hold', __( 'Awaiting payment confirmation from Ledyer.', 'ledyer-payments-for-woocommerce' ) );
		} else {
			Ledyer()->logger()->warning( "Unknown order state: {$ledyer_order['state']}", $context );
		}

		$order->set_payment_method( self::ID );
		$order->set_transaction_id( $payment_id );

		// orderId not available if state is awaitingSignatory.
		isset( $ledyer_order['orderId'] ) && $order->update_meta_data( '_wc_ledyer_order_id', $ledyer_order['orderId'] );

		$env = wc_string_to_bool( Ledyer()->settings( 'test_mode' ) ?? 'no' ) ? 'sandbox' : 'production';
		$order->update_meta_data( '_wc_ledyer_environment', $env );
		$order->update_meta_data( '_wc_ledyer_session_id', $ledyer_order['id'] );
		$order->save();

		Ledyer()->session()->clear_session( $order );
	}
}
