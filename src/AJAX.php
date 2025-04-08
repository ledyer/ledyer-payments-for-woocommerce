<?php //phpcs:ignore -- PCR-4 compliant
/**
 * Class AJAX.
 *
 * AJAX endpoints.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AJAX
 */
class AJAX {

	/**
	 * AJAX constructor.
	 */
	public function __construct() {
		$ajax_events = array(
			'ledyer_payments_wc_log_js'       => true,
			'ledyer_payments_create_order'    => true,
			'ledyer_payments_pending_payment' => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
				add_action( 'wc_ajax_' . $ajax_event, array( $this, $ajax_event ) );
			}
		}
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function ledyer_payments_wc_log_js() {
		check_ajax_referer( 'ledyer_payments_wc_log_js', 'nonce' );

		// Get the content size of the request.
		$post_size = (int) $_SERVER['CONTENT_LENGTH'] ?? 0;

		// If the post data is too long, log an error message and return.
		if ( $post_size > 1024 ) {
			Ledyer_Payments()->logger()->log( '[AJAX]: Message too long and cannot be logged.', 'error' );
			wp_send_json_success(); // Return success to not stop anything in the frontend if this happens.
		}

		$posted_message = filter_input( INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$message        = '[AJAX]: ' . sanitize_text_field( $posted_message );
		$prefix         = sanitize_text_field( filter_input( INPUT_POST, 'reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$level          = sanitize_text_field( filter_input( INPUT_POST, 'level', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) ?? 'notice';

		if ( ! empty( $message ) ) {
			Ledyer_Payments()->logger()->log( $message, $level, array( 'prefix' => $prefix ) );
		}

		wp_send_json_success();
	}

	/**
	 * Acknowledges the Ledyer order.
	 *
	 * @return void
	 */
	public static function ledyer_payments_create_order() {
		check_ajax_referer( 'ledyer_payments_create_order', 'nonce' );

		$auth_token = filter_input( INPUT_POST, 'auth_token', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_key  = filter_input( INPUT_POST, 'order_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $auth_token ) || empty( $order_key ) ) {
			wp_send_json_error( 'Missing params. Received: ' . wp_json_encode( $auth_token, $order_key ) );
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );
		$context  = array(
			'function'  => __FUNCTION__,
			'order_id'  => $order_id,
			'order_key' => $order_key,
		);

		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			Ledyer_Payments()->logger()->error( '[AJAX]: Order not found', $context );
			wp_send_json_error( 'Order not found' );
		}

		$result = Ledyer_Payments()->api()->create_order( $order_id, $auth_token );
		if ( is_wp_error( $result ) ) {
			Ledyer_Payments()->logger()->error( "[AJAX]: Create order failed: {$result->get_error_message()}", $context );
			wp_send_json_error( $result );
		}

		$payment_id = $result['orderId'] ?? null;
		$order->update_meta_data( 'ledyer_payments_payment_id', $payment_id );
		$order->save();

		$redirect_to = add_query_arg(
			array(
				'gateway' => 'ledyer_payments',
				'key'     => $order_key,
			),
			$order->get_checkout_order_received_url()
		);

		$context = array(
			'function'   => __FUNCTION__,
			'order_id'   => $order_id,
			'order_key'  => $order_key,
			'payment_id' => $payment_id,
		);
		Ledyer_Payments()->logger()->debug( '[AJAX]: Successfully placed order with Ledyer, sending redirect URL to: ' . $redirect_to, $context );

		wp_send_json_success( array( 'location' => $redirect_to ) );
	}

	/**
	 * Handles payments awaiting signatory.
	 *
	 * @return void
	 */
	public static function ledyer_payments_pending_payment() {
		check_ajax_referer( 'ledyer_payments_pending_payment', 'nonce' );

		$order_key = filter_input( INPUT_POST, 'order_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( empty( $order_key ) ) {
			wp_send_json_error( 'Missing params. Received: ' . wp_json_encode( $order_key ) );
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );
		$context  = array(
			'function'  => __FUNCTION__,
			'order_id'  => $order_id,
			'order_key' => $order_key,
		);

		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			Ledyer_Payments()->logger()->error( '[AJAX]: Order not found', $context );
			wp_send_json_error( 'Order not found' );
		}

		$redirect_to = add_query_arg(
			array(
				'gateway' => 'ledyer_payments',
				'key'     => $order_key,
			),
			$order->get_checkout_order_received_url()
		);

		$context = array(
			'function'  => __FUNCTION__,
			'order_id'  => $order_id,
			'order_key' => $order_key,
		);
		Ledyer_Payments()->logger()->debug( '[AJAX]: Successfully placed order with Ledyer, sending redirect URL to: ' . $redirect_to, $context );

		wp_send_json_success( array( 'location' => $redirect_to ) );
	}
}
