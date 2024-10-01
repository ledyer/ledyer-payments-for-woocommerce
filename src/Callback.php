<?php
/**
 * Class Callback.
 *
 * Handles callbacks (also known as "notifications") from Ledyer.
 */

namespace Krokedil\Ledyer\Payments;

use Krokedil\Ledyer\Payments\Gateway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Callback {

	public const REST_API_NAMESPACE = 'krokedil/ledyer/payments/v1';
	public const REST_API_ENDPOINT  = '/callback';
	public const API_ENDPOINT       = 'wp-json/' . self::REST_API_NAMESPACE . self::REST_API_ENDPOINT;


	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( Gateway::ID . '_scheduled_callback', array( $this, 'handle_scheduled_callback' ) );
	}

	/**
	 * Register the REST API route(s).
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_API_NAMESPACE,
			self::REST_API_ENDPOINT,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'callback_handler' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function callback_handler( $request ) {
		$params = filter_var_array( $request->get_json_params(), FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$event_type = wc_get_var( $params['eventType'] );
		$payment_id = wc_get_var( $params['orderId'] );
		$reference  = wc_get_var( $params['reference'] );
		$store_id   = wc_get_var( $params['storeId'] );

		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
			'request'  => $params,
		);

		Ledyer()->logger()->debug( 'Received callback.', $context );

		if ( empty( $payment_id ) && empty( $reference ) ) {
			Ledyer()->logger()->error( 'Missing payment ID.', $context );
		}

		$order = $this->get_order_by_payment_id( $payment_id );
		if ( empty( $order ) ) {
			Ledyer()->logger()->error( "Callback: Order '{$payment_id}' not found.", $context );
			http_response_code( 404 );
			die;
		}

		$status = $this->schedule_callback( $payment_id, $event_type, $reference, $store_id ) ? 200 : 500;
		http_response_code( $status );
		die;
	}

	public function handle_scheduled_callback( $payment_id, $event_type, $reference, $store_id ) {
		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
		);

		$order = $this->get_order_by_payment_id( $payment_id );
		if ( empty( $order ) ) {
			Ledyer()->logger()->error( "Order $payment_id not found.", $context );
			return;
		}

		if ( 'com.ledyer.order.create' === $event_type ) {
			// TODO
		}
	}
	private function schedule_callback( $payment_id, $event_type, $reference, $store_id ) {
		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
		);

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
				'payment_id' => $payment_id,
				'event_type' => $event_type,
				'reference'  => $reference,
				'store_id'   => $store_id,
			)
		);

		Ledyer()->logger()->debug(
			"Successfully scheduled callback for order $payment_id.",
			array_merge(
				$context,
				array( 'id' => $did_schedule ),
			)
		);

		return $did_schedule !== 0;
	}

	/**
	 * Get order by payment ID or reference.
	 *
	 * For orders awaiting signatory, the order reference is used as the payment ID. Otherwise, the orderId from Ledyer.
	 *
	 * @param string $payment_id Payment ID or reference.
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
}
