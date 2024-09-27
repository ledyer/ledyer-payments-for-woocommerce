<?php
/**
 * Class Callback.
 *
 * Handles callbacks (also known as "notifications") from Ledyer.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Callback {

	public function __construct() {
		add_action( 'woocommerce_api_' . Gateway::ID, array( $this, 'callback_handler' ) );
		add_action( Gateway::ID . '_scheduled_callback', array( $this, 'handle_scheduled_callback' ) );
	}

	public function callback_handler() {
		$event_type = filter_input( INPUT_GET, 'eventType', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$order_id   = filter_input( INPUT_GET, 'orderId', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$reference  = filter_input( INPUT_GET, 'reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$store_id   = filter_input( INPUT_GET, 'storeId', FILTER_VALIDATE_INT );

		$context = array( 'from' => 'callback_handler' );
		Ledyer()->logger()->debug( 'Received callback.', array_merge( $context, filter_var_array( $_GET, FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) );

		if ( empty( $order_id ) ) {
			Ledyer()->logger()->error( 'Missing payment ID.', $context );
		}

		$order = $this->get_order_by_payment_id( $order_id );
		if ( empty( $order ) ) {
			Ledyer()->logger()->log( "Callback: Order '{$order_id}' not found.", 'error', $context );
			http_response_code( 404 );
			die;
		}

		$status = $this->schedule_callback( $order_id, $reference, $store_id, $event_type ) ? 200 : 500;
		http_response_code( $status );
		die;
	}

	public function handle_scheduled_callback( $payment_id, $reference, $merchant_id, $event_type ) {
		$context = array( 'from' => 'handle_scheduled_callback' );

		$order = $this->get_order_by_payment_id( $payment_id );
		if ( empty( $order ) ) {
			Ledyer()->logger()->error( "Order $payment_id not found.", $context );
			return;
		}

		if ( 'com.ledyer.order.create' === $event_type ) {
			// TODO
		}
	}

	/**
	 * Get order by payment ID.
	 *
	 * @param string $payment_id Payment ID.
	 * @return int|bool Order ID or false if not found.
	 */
	private function get_order_by_payment_id( $payment_id ) {
		$key    = '_' . Gateway::ID . '_payment_id';
		$orders = wc_get_orders(
			array(
				'meta_key'     => $key,
				'meta_value'   => $payment_id,
				'limit'        => '1',
				'orderby'      => 'date',
				'order'        => 'DESC',
				'meta_compare' => '=',
			)
		);

		$order = reset( $orders );
		if ( empty( $order ) || $payment_id !== $order->get_meta( $key ) ) {
			return false;
		}

		return $order->get_id() ?? false;
	}

	private function schedule_callback( $payment_id, $reference, $merchant_id, $event_type ) {
		$context           = array( 'from' => 'schedule_callback' );
		$hook              = Gateway::ID . '_scheduled_callback';
		$as_args           = array(
			'hook'   => $hook,
			'status' => \ActionScheduler_Store::STATUS_PENDING,
		);
		$scheduled_actions = as_get_scheduled_actions( $as_args, OBJECT );

		/**
		 * Loop all actions to check if this one has been scheduled already.
		 *
		 * @var \ActionScheduler_Action $action The action from the Action scheduler.
		 */
		foreach ( $scheduled_actions as $action ) {
			$action_args = $action->get_args();
			if ( $payment_id === $action_args['payment_id'] ) {
				Ledyer()->logger()->debug( "The order $payment_id is already scheduled for processing.", $context );
				return true;
			}
		}

		// If we get here, we should be good to create a new scheduled action, since none are currently scheduled for this order.
		$did_schedule = as_schedule_single_action(
			time() + 60,
			$hook,
			array(
				'merchant_id' => $merchant_id,
				'payment_id'  => $payment_id,
				'reference'   => $reference,
				'event_type'  => $event_type,
			)
		);

		Ledyer()->logger()->debug(
			"Successfully scheduled callback for order $payment_id.",
			'debug',
			array_merge(
				$context,
				array( 'id' => $did_schedule ),
			)
		);

		return $did_schedule !== 0;
	}
}
