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

	private const READY_FOR_CAPTURE_EVENT = 'com.ledyer.order.ready_for_capture';
	private const MAX_RETRIES             = 3;


	/**
	 * Callback constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'ledyer_payments_scheduled_auth_create_callback', array( $this, 'handle_auth_create_callback' ) );
		add_action( 'ledyer_payments_scheduled_notification', array( $this, 'handle_notification' ), 10, 3 );
		add_action( 'ledyer_payments_scheduled_order_ready_for_capture_callback', array( $this, 'handle_notification' ), 10, 2 );
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

		$session_id      = wc_get_var( $params['sessionId'] );
		$event_type      = wc_get_var( $params['eventType'] );
		$ledyer_order_id = wc_get_var( $params['orderId'] );

		Ledyer_Payments()->logger()->debug( '[CALLBACK]: Received callback.', $context );

		$status = null;
		switch ( $event_type ) {
			case 'com.ledyer.authorization.create':
				if ( empty( $session_id ) ) {
					Ledyer_Payments()->logger()->error( '[CALLBACK]: Missing payment ID.', $context );
					return new \WP_Error( 'missing_session_id', 'Missing session ID.', array( 'status' => 404 ) );
				}

				$order = Ledyer_Payments()->gateway()->get_order_by_session_id( $session_id );
				if ( empty( $order ) ) {
					Ledyer_Payments()->logger()->error( "[CALLBACK]: Order '{$session_id}' not found.", $context );
					return new \WP_Error( 'order_not_found', 'Order not found.', array( 'status' => 404 ) );
				}

				// Only acknowledge after creation/sync succeeds, so Ledyer can retry failed deliveries.
				$result = Ledyer_Payments()->gateway()->confirm_order( $order, $context, true );
				if ( is_wp_error( $result ) ) {
					Ledyer_Payments()->logger()->error( '[CALLBACK]: Authorization processing failed: ' . $result->get_error_message(), $context );
					return new \WP_Error( 'authorization_processing_failed', __( 'Failed to process Ledyer authorization.', 'ledyer-payments-for-woocommerce' ), array( 'status' => 500 ) );
				}

				$status = 200;
				break;
			default:
				// Any other event is a trigger to fetch the payment status and update the order status accordingly.
				if ( empty( $ledyer_order_id ) ) {
					Ledyer_Payments()->logger()->debug( '[CALLBACK]: Event without Ledyer order ID, ignoring.', $context );
					return new \WP_REST_Response( array(), 200 );
				}

				$status = $this->schedule_notification( $ledyer_order_id, $event_type ) ? 200 : 500;
				break;
		}

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
	public function handle_auth_create_callback( $session_id ) {
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
	 * Handles a scheduled notification by updating the order status from the Ledyer payment status.
	 *
	 * @param string $ledyer_order_id The Ledyer order ID.
	 * @param string $event_type The Ledyer event type.
	 * @param int    $attempt The number of previous failed attempts.
	 * @return void
	 */
	public function handle_notification( $ledyer_order_id, $event_type, $attempt = 0 ) {
		$context = array(
			'filter'          => current_filter(),
			'function'        => __FUNCTION__,
			'ledyer_order_id' => $ledyer_order_id,
			'event_type'      => $event_type,
			'attempt'         => $attempt,
		);

		$order = Ledyer_Payments()->gateway()->get_order_by_ledyer_order_id( $ledyer_order_id );
		if ( empty( $order ) ) {
			Ledyer_Payments()->logger()->debug( '[CALLBACK]: Could not find woo order with ledyer id: ' . $ledyer_order_id, $context );
			return;
		}

		$payment_status = Ledyer_Payments()->api()->get_payment_status( $ledyer_order_id );
		if ( is_wp_error( $payment_status ) ) {
			Ledyer_Payments()->logger()->error( "[CALLBACK]: Failed to get Ledyer payment status: {$payment_status->get_error_message()}", $context );

			// Retry later, since Ledyer will not send the notification again.
			if ( $attempt < self::MAX_RETRIES ) {
				as_schedule_single_action(
					time() + ( $attempt + 1 ) * 5 * MINUTE_IN_SECONDS,
					'ledyer_payments_scheduled_notification',
					array( $ledyer_order_id, $event_type, $attempt + 1 )
				);
			}
			return;
		}

		if ( self::READY_FOR_CAPTURE_EVENT === $event_type ) {
			$this->process_ready_for_capture( $order, $payment_status, $ledyer_order_id );
			return;
		}

		Ledyer_Payments()->gateway()->process_order_status( $order, $payment_status, $ledyer_order_id );
	}

	/**
	 * Process the ready for capture event.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param array     $payment_status The Ledyer payment status response.
	 * @param string    $ledyer_order_id The Ledyer order ID.
	 * @return void
	 */
	private function process_ready_for_capture( $order, $payment_status, $ledyer_order_id ) {
		$order->update_meta_data( '_ledyer_ready_for_capture', true );

		$waiting_on_ready_for_capture = $order->get_meta( '_ledyer_waiting_on_ready_for_capture', true );
		if ( ! empty( $waiting_on_ready_for_capture ) || empty( $order->get_date_paid() ) ) {
			$order->delete_meta_data( '_ledyer_waiting_on_ready_for_capture' );
			Ledyer_Payments()->gateway()->process_order_status( $order, $payment_status, $ledyer_order_id );
		}

		$order->save();
	}

	/**
	 * Schedule a notification for later processing.
	 *
	 * @param string $ledyer_order_id The Ledyer order ID.
	 * @param string $event_type The Ledyer event type.
	 *
	 * @return bool True if the notification was scheduled, false otherwise.
	 */
	private function schedule_notification( $ledyer_order_id, $event_type ) {
		$context = array(
			'filter'          => current_filter(),
			'function'        => __FUNCTION__,
			'ledyer_order_id' => $ledyer_order_id,
			'event_type'      => $event_type,
		);

		$hook              = 'ledyer_payments_scheduled_notification';
		$as_args           = array(
			'hook'     => $hook,
			'status'   => \ActionScheduler_Store::STATUS_PENDING,
			'search'   => $ledyer_order_id,
			'per_page' => -1,
		);
		$scheduled_actions = as_get_scheduled_actions( $as_args, OBJECT );

		/**
		 * Loop all actions to check if this order is already scheduled for processing. All events except ready for capture are handled the same way (by fetching the current payment status), so at most one of each is needed per order.
		 *
		 * @var \ActionScheduler_Action $action The action from the Action scheduler.
		 */
		$is_ready_for_capture = self::READY_FOR_CAPTURE_EVENT === $event_type;
		foreach ( $scheduled_actions as $action ) {
			$action_args = $action->get_args();
			if ( $ledyer_order_id === $action_args[0] && ( self::READY_FOR_CAPTURE_EVENT === $action_args[1] ) === $is_ready_for_capture ) {
				Ledyer_Payments()->logger()->debug( '[CALLBACK]: The event is already scheduled for processing.', $context );
				return true;
			}
		}

		$did_schedule = as_schedule_single_action(
			time() + 60,
			$hook,
			array( $ledyer_order_id, $event_type )
		);

		$context['schedule_id'] = $did_schedule;
		Ledyer_Payments()->logger()->debug( '[CALLBACK]: Successfully scheduled notification.', $context );

		return 0 !== $did_schedule;
	}
}
