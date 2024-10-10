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

		$session_id = wc_get_var( $params['sessionId'] );
		$event_type = wc_get_var( $params['eventType'] );

		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
			'request'  => $params,
		);
		Ledyer_Payments()->logger()->debug( 'Received callback.', $context );

		if ( 'com.ledyer.order.create' !== $event_type ) {
			Ledyer_Payments()->logger()->debug( 'Unsupported event type.', $context );
			return new \WP_REST_Response( array(), 200 );
		}

		if ( empty( $session_id ) ) {
			Ledyer_Payments()->logger()->error( 'Missing payment ID.', $context );
			return new \WP_REST_Response( array(), 400 );
			// return new \WP_Error( 'missing_session_id', 'Missing session ID.', array( 'status' => 404 ) );
		}

		$order = $this->get_order_by_session_id( $session_id );
		if ( empty( $order ) ) {
			Ledyer_Payments()->logger()->error( "Order '{$session_id}' not found.", $context );
			return new \WP_REST_Response( array(), 404 );
			// return new \WP_Error( 'order_not_found', 'Order not found.', array( 'status' => 404 ) );
		}

		$status = $this->schedule_callback( $session_id, $event_type ) ? 200 : 500;
		if ( $status >= 500 ) {
			return new \WP_REST_Response( array(), $status );
			// return new \WP_Error( 'scheduling_failed', __( 'Failed to schedule callback.', 'ledyer-payments-for-woocommerce' ), array( 'status' => $status ) );
		}
		return new \WP_REST_Response( array(), $status );
	}

	/**
	 * Handles a scheduled callback.
	 *
	 * @param \WC_Order $order The WC order.
	 * @param string    $session_id The session ID.
	 * @param string    $event_type The event type.
	 * @return void
	 */
	public function handle_scheduled_callback( $order, $session_id ) {
		$context = array(
			'filter'     => current_filter(),
			'function'   => __FUNCTION__,
			'session_id' => $session_id,
		);

		$order = $this->get_order_by_session_id( $session_id );
		if ( empty( $order ) ) {
			Ledyer_Payments()->logger()->error( 'Order not found.', $context );
			return;
		}
	}

	/**
	 * Schedule a callback for later processing.
	 *
	 * @param string $session_id The session ID.
	 * @param string $event_type The event type.
	 * @return bool True if the callback was scheduled, false otherwise.
	 */
	private function schedule_callback( $session_id, $event_type ) {
		$context = array(
			'filter'     => current_filter(),
			'function'   => __FUNCTION__,
			'session_id' => $session_id,
			'event_type' => $event_type,
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
			if ( $session_id === $action_args['session_id'] ) {
				Ledyer_Payments()->logger()->debug( 'The order is already scheduled for processing.', $context );
				return true;
			}
		}

		// If we get here, we should be good to create a new scheduled action, since none are currently scheduled for this order.
		$did_schedule = as_schedule_single_action(
			time() + 60,
			$hook,
			array(
				'session_id' => $session_id,
			)
		);

		$context['id'] = $did_schedule;
		Ledyer_Payments()->logger()->debug( 'Successfully scheduled callback.', $context );

		return 0 !== $did_schedule;
	}

	/**
	 * Get order by payment ID or reference.
	 *
	 * For orders awaiting signatory, the order reference is used as the payment ID. Otherwise, the orderId from Ledyer.
	 *
	 * @param string $session_id Payment ID or reference.
	 * @return int|bool Order ID or false if not found.
	 */
	private function get_order_by_session_id( $session_id ) {
		$key    = '_wc_ledyer_session_id';
		$orders = wc_get_orders(
			array(
				'meta_query' => array(
					array(
						'key'     => $key,
						'value'   => $session_id,
						'compare' => '=',
					),
				),
				'limit'      => '1',
				'orderby'    => 'date',
				'order'      => 'DESC',
			)
		);

		$order = reset( $orders );
		if ( empty( $order ) || $session_id !== $order->get_meta( $key ) ) {
			return false;
		}

		return $order->get_id() ?? false;
	}
}
