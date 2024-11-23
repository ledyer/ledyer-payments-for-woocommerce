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
		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
		);

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			Ledyer_Payments()->logger()->debug( '[CALLBACK]: Received callback without parameters.', $context );
			return new \WP_Error( 'missing_params', 'Missing parameters.', array( 'status' => 400 ) );
		}

		$params             = filter_var_array( $params, FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$context['request'] = $params;

		$session_id = wc_get_var( $params['sessionId'] );
		$event_type = wc_get_var( $params['eventType'] );

		Ledyer_Payments()->logger()->debug( '[CALLBACK]: Received callback.', $context );

		if ( 'com.ledyer.authorization.create' !== $event_type ) {
			Ledyer_Payments()->logger()->debug( '[CALLBACK]: Unsupported event type.', $context );
			return new \WP_REST_Response( array(), 200 );
		}

		if ( empty( $session_id ) ) {
			Ledyer_Payments()->logger()->error( '[CALLBACK]: Missing payment ID.', $context );
			return new \WP_Error( 'missing_session_id', 'Missing session ID.', array( 'status' => 404 ) );
		}

		$order = Ledyer_Payments()->gateway()->get_order_by_session_id( $session_id );
		if ( empty( $order ) ) {
			Ledyer_Payments()->logger()->error( "[CALLBACK]: Order '{$session_id}' not found.", $context );
			return new \WP_Error( 'order_not_found', 'Order not found.', array( 'status' => 404 ) );
		}

		$status = $this->schedule_callback( $session_id ) ? 200 : 500;
		if ( $status >= 500 ) {
			return new \WP_Error( 'scheduling_failed', __( 'Failed to schedule callback.', 'ledyer-payments-for-woocommerce' ), array( 'status' => $status ) );
		}
		return new \WP_REST_Response( array(), $status );
	}

	/**
	 * Handles a scheduled callback.
	 *
	 * @param string $session_id The session ID.
	 * @return void
	 */
	public function handle_scheduled_callback( $session_id ) {
		$context = array(
			'filter'     => current_filter(),
			'function'   => __FUNCTION__,
			'session_id' => $session_id,
		);

		$order = Ledyer_Payments()->gateway()->get_order_by_session_id( $session_id );
		if ( empty( $order ) ) {
			Ledyer_Payments()->logger()->error( '[CALLBACK]: Order not found.', $context );
			return;
		}

		Ledyer_Payments()->gateway()->confirm_order( $order, $context );
	}

	/**
	 * Schedule a callback for later processing.
	 *
	 * @param string $session_id The session ID.
	 * @return bool True if the callback was scheduled, false otherwise.
	 */
	private function schedule_callback( $session_id ) {
		$context = array(
			'filter'     => current_filter(),
			'function'   => __FUNCTION__,
			'session_id' => $session_id,
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
				Ledyer_Payments()->logger()->debug( '[CALLBACK]: The order is already scheduled for processing.', $context );
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

		$context['schedule_id'] = $did_schedule;
		Ledyer_Payments()->logger()->debug( '[CALLBACK]: Successfully scheduled callback.', $context );

		return 0 !== $did_schedule;
	}
}
