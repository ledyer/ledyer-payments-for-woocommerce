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

/**
 * Class Callback.
 */
class Callback {

	public const REST_API_NAMESPACE = 'krokedil/ledyer/payments/v1';
	public const REST_API_ENDPOINT  = '/callback';
	public const API_ENDPOINT       = 'wp-json/' . self::REST_API_NAMESPACE . self::REST_API_ENDPOINT;


	/**
	 * Callback constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'ledyer_payments_scheduled_callback', array( $this, 'handle_scheduled_callback' ) );
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

	/**
	 * Handles a callback ("notification") from Ledyer.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
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

		// TODO: Add support for com.ledyer.authorization.pending.
		if ( 'com.ledyer.order.create' !== $event_type ) {
			Ledyer()->logger()->debug( 'Unsupported event type.', $context );
			return new \WP_REST_Response( array(), 200 );
		}

		if ( empty( $payment_id ) ) {
			Ledyer()->logger()->error( 'Missing payment ID.', $context );
			return new \WP_Error( 'missing-payment-id', 'Missing payment ID.', array( 'status' => 404 ) );
		}

		$order = $this->get_order_by_payment_id( $payment_id );
		if ( empty( $order ) ) {
			Ledyer()->logger()->error( "Order '{$payment_id}' not found.", $context );
			return new \WP_Error( 'order-not-found', 'Order not found.', array( 'status' => 404 ) );
		}

		$status = $this->schedule_callback( $payment_id, $event_type, $reference, $store_id ) ? 200 : 500;
		if ( $status >= 500 ) {
			return new \WP_Error( 'scheduling-failed', __( 'Failed to schedule callback.', 'ledyer-payments-for-woocommerce' ), array( 'status' => $status ) );
		}
		return new \WP_REST_Response( array(), $status );
	}

	/**
	 * Handles a scheduled callback.
	 *
	 * @param string $payment_id The payment ID.
	 * @param string $event_type The event type.
	 * @param string $reference The reference.
	 * @param string $store_id The store ID.
	 * @return void
	 */
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

	/**
	 * Schedule a callback for later processing.
	 *
	 * @param string $payment_id The payment ID.
	 * @param string $event_type The event type.
	 * @param string $reference The reference.
	 * @param string $store_id The store ID.
	 * @return bool True if the callback was scheduled, false otherwise.
	 */
	private function schedule_callback( $payment_id, $event_type, $reference, $store_id ) {
		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
		);

		$hook              = 'ledyer_payments_scheduled_callback';
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

		return 0 !== $did_schedule;
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
		$key    = '_ledyer_payments_payment_id';
		$orders = wc_get_orders(
			array(
				'meta_query' => array(
					array(
						'key'     => $key,
						'value'   => $payment_id,
						'compare' => '=',
					),
				),
				'limit'      => '1',
				'orderby'    => 'date',
				'order'      => 'DESC',
			)
		);

		$order = reset( $orders );
		if ( empty( $order ) || $payment_id !== $order->get_meta( $key ) ) {
			return false;
		}

		return $order->get_id() ?? false;
	}
}
