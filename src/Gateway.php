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

/**
 * Class Gateway.
 */
class Gateway extends \WC_Payment_Gateway {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = 'ledyer_payments';
		$this->method_title       = __( 'Ledyer Payments', 'ledyer-payments-for-woocommerce' );
		$this->method_description = __( 'Ledyer Payments', 'ledyer-payments-for-woocommerce' );
		$this->supports           = apply_filters(
			'ledyer_payments_supports',
			array(
				'products',
			)
		);
		$this->init_form_fields();
		$this->init_settings();

		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );
		$this->has_fields  = true;

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		add_filter( 'wc_get_template', array( $this, 'payment_categories' ), 10, 3 );
		add_action( 'template_redirect', array( $this, 'maybe_confirm_order' ), 9999 );

		// Process the checkout before the payment is processed.
		add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout' ) );

		// Process the custom checkout fields that we inject to the checkout form (e.g., company number field).
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'process_custom_checkout_fields' ) );

		// Add the custom fields to the admin billing fields in the edit view.
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'additional_admin_billing_fields' ), 10, 3 );

		// Process admin order updates.
		add_action( 'woocommerce_saved_order_items', array( $this, 'update_additional_admin_billing_fields' ) );
	}

	/**
	 * Check if the gateway is the chosen payment method.
	 *
	 * Due to inconsistencies in the gateway name (e.g., ledyer_payments vs. ledyer_payments_invoice), we need to check for the presence of 'ledyer_payments'.
	 *
	 * @param int|null|\WC_Order $order_id The WooCommerce order or its id. If `null`, the current session is used.
	 * @return bool
	 */
	private function is_chosen_gateway( $order_id = null ) {
		if ( $order_id instanceof \WC_Order ) {
			$chosen_gateway = $order_id->get_payment_method();
		} elseif ( ! empty( $order_id ) ) {
			$order          = wc_get_order( $order_id );
			$chosen_gateway = empty( $order ) ? '' : $order->get_payment_method();
		} else {
			$chosen_gateway = ! isset( WC()->session ) ? '' : WC()->session->get( 'chosen_payment_method' );
		}

		return strpos( $chosen_gateway, 'ledyer_payments' ) !== false;
	}

	/**
	 * Initialize settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = Settings::setting_fields();

		// Delete the access token whenever the settings are modified.
		add_action( 'update_option_woocommerce_ledyer_payments_settings', array( Settings::class, 'maybe_update_access_token' ) );
	}

	/**
	 * Summary of payment_fields
	 *
	 * @return void
	 */
	public function payment_fields() {
		parent::payment_fields();

		woocommerce_form_field(
			'billing_company_number',
			array(
				'type'              => 'text',
				'class'             => array(
					'form-row-wide',
				),
				'label'             => __( 'Company number', 'ledyer-payments-for-woocommerce' ),
				'required'          => true,
				'placeholder'       => __( 'Company number', 'ledyer-payments-for-woocommerce' ),
				'custom_attributes' => array(
					'pattern' => '^[0-9]{6}-[0-9]{4}$',
				),
			)
		);

		woocommerce_form_field(
			'ledyer_customer_reference_1',
			array(
				'type'        => 'text',
				'class'       => array(
					'form-row-wide',
				),
				'label'       => __( 'Reference 1', 'ledyer-payments-for-woocommerce' ),
				'required'    => false,
				'placeholder' => __( 'For example, reference code or cost center', 'ledyer-payments-for-woocommerce' ),
			)
		);

		woocommerce_form_field(
			'ledyer_customer_reference_2',
			array(
				'type'        => 'text',
				'class'       => array(
					'form-row-wide',
				),
				'label'       => __( 'Reference 2', 'ledyer-payments-for-woocommerce' ),
				'required'    => false,
				'placeholder' => __( 'For example, purchase order number', 'ledyer-payments-for-woocommerce' ),
			)
		);
	}

	/**
	 * Add additional billing fields to the admin order page.
	 *
	 * @param array             $billing_fields The billing fields.
	 * @param \WC_Order|boolean $order The order to show the fields for.
	 * @param string            $context The context to show the fields for.
	 * @return array
	 */
	public function additional_admin_billing_fields( $billing_fields, $order, $context = 'edit' ) {
		if ( ! $this->is_chosen_gateway( $order ) ) {
			return $billing_fields;
		}

		// The value can be explicitly set by adding a 'value' property. However, if not set, WC will attempt to prefix the field name with 'billing_', and retrieve it from a metadata field by that key. E.g., 'company_number' => $order->get_meta('_billing_company_number').
		$additional_fields = array(
			'company_number'              => array(
				'label' => __( 'Company number', 'ledyer-payments-for-woocommerce' ),
				'type'  => 'text',
				'show'  => true,
			),
			'ledyer_customer_reference_1' => array(
				'id'    => '_ledyer_customer_reference_1',
				'label' => __( 'Customer reference 1', 'ledyer-payments-for-woocommerce' ),
				'type'  => 'text',
				'show'  => true,
				'value' => $order->get_meta( '_ledyer_customer_reference_1' ),
			),
			'ledyer_customer_reference_2' => array(
				'id'    => '_ledyer_customer_reference_2',
				'label' => __( 'Customer reference 2', 'ledyer-payments-for-woocommerce' ),
				'type'  => 'text',
				'show'  => true,
				'value' => $order->get_meta( '_ledyer_customer_reference_2' ),
			),
		);

		// Inserts the additional fields after the 'company' field.
		$index = array_search(
			'company',
			array_keys( $billing_fields ),
			true
		);

		// Increment $index by 1 to include the first element too (since $index starts from 0, but length starts from 1).
		$before = array_slice( $billing_fields, 0, $index + 1 );
		$after  = array_slice( $billing_fields, $index );
		return $before + $additional_fields + $after;
	}

	/**
	 * Process the admin order update.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return void
	 */
	public function update_additional_admin_billing_fields( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $this->is_chosen_gateway( $order ) ) {
			return;
		}

		// Notice that the field name is prefixed with 'billing_' unless an 'id' is explicitly set which it is in our case. See additional_admin_billing_fields() in this file.
		$customer_reference_1 = filter_input( INPUT_POST, '_ledyer_customer_reference_1', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( isset( $customer_reference_1 ) ) {
			$order->update_meta_data( '_ledyer_customer_reference_1', sanitize_text_field( $customer_reference_1 ) );
		}

		$customer_reference_2 = filter_input( INPUT_POST, '_ledyer_customer_reference_2', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( isset( $customer_reference_2 ) ) {
			$order->update_meta_data( '_ledyer_customer_reference_2', sanitize_text_field( $customer_reference_2 ) );
		}

		// No need to call save(). This is already handled by the hook.
	}

	/**
	 * The payment gateway icon that will appear on the checkout page.
	 *
	 * @return string
	 */
	public function get_icon() {
		$image_path = plugin_dir_url( __FILE__ ) . 'assets/img/ledyer-darkgray.svg';
		return "<img src='{$image_path}' style='max-width:120px;max-height:25px' alt='Ledyer Payments logo' />"; // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
	}

	/**
	 * Whether the payment gateway is available.
	 *
	 * @filter ledyer_payments_is_available
	 *
	 * @return boolean
	 */
	public function is_available() {
		return apply_filters( 'ledyer_payments_is_available', $this->check_availability() );
	}

	/**
	 * Check if the gateway should be available.
	 *
	 * This function is extracted to create the 'ledyer_payments_is_available' filter.
	 *
	 * @return bool
	 */
	private function check_availability() {
		return wc_string_to_bool( $this->enabled );
	}

	/**
	 * Process the checkout before the payment is processed.
	 *
	 * @hook woocommerce_checkout_process
	 * @return void
	 */
	public function process_checkout() {
		if ( ! $this->is_chosen_gateway() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['billing_company_number'] ) && empty( $_POST['billing_company_number'] ) ) {
			wc_add_notice( __( 'Please enter your company number.', 'ledyer-payments-for-woocommerce' ), 'error' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_POST['billing_company'] ) && empty( $_POST['billing_company'] ) ) {
			wc_add_notice( __( 'Please enter your company name.', 'ledyer-payments-for-woocommerce' ), 'error' );
		}
	}

	/**
	 * Process the custom checkout fields that we inject to the checkout form (e.g., company number field).
	 *
	 * @hook woocommerce_checkout_update_order_meta
	 * @param int $order_id The WooCommerce order id.
	 * @return void
	 */
	public function process_custom_checkout_fields( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $this->is_chosen_gateway( $order ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$company_number = filter_input( INPUT_POST, 'billing_company_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $company_number ) ) {
			$order->update_meta_data( '_billing_company_number', sanitize_text_field( $company_number ) );
		}

		$reference_1 = filter_input( INPUT_POST, 'ledyer_customer_reference_1', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$reference_2 = filter_input( INPUT_POST, 'ledyer_customer_reference_2', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $reference_1 ) ) {
			$order->update_meta_data( '_ledyer_customer_reference_1', sanitize_text_field( $reference_1 ) );
		}
		if ( ! empty( $reference_2 ) ) {
			$order->update_meta_data( '_ledyer_customer_reference_2', sanitize_text_field( $reference_2 ) );
		}

		$order->save();
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
		$order->update_meta_data( '_wc_ledyer_reference', Ledyer_Payments()->session()->get_reference() );
		$order->update_meta_data( '_wc_ledyer_session_id', Ledyer_Payments()->session()->get_id() );
		$order->save();

		// Update the nonce only if WordPress determines it necessary, such as when a guest becomes signed in.
		$nonce = array(
			'changePaymentMethodNonce' => wp_create_nonce( 'ledyer_payments_change_payment_method' ),
			'logToFileNonce'           => wp_create_nonce( 'ledyer_payments_wc_log_js' ),
			'createOrderNonce'         => wp_create_nonce( 'ledyer_payments_create_order' ),
			'pendingPaymentNonce'      => wp_create_nonce( 'ledyer_payments_pending_payment' ),
		);

		return array(
			'order_key' => $order->get_order_key(),
			'customer'  => $customer,
			'redirect'  => $order->get_checkout_order_received_url(),
			'nonce'     => $nonce,
			'result'    => 'success',
		);
	}

	/**
	 * This plugin doesn't handle order management, but it allows the Ledyer Order Management plugin to process refunds
	 * and then return true or false whether it was successful.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param float  $amount The amount to refund.
	 * @param string $reason The reason for the refund.
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		return apply_filters( 'ledyer_payments_process_refund', false, $order_id, $amount, $reason );
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

		if ( ( 'checkout/payment-method.php' !== $template_name ) || ( 'ledyer_payments' !== $args['gateway']->id ) ) {
			return $located;
		}

		return untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/payment-categories.php';
	}

	/**
	 * Confirm the order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param array     $context The logging context. Optional.
	 * @return true|\WP_Error
	 */
	public function confirm_order( $order, $context = array() ) {
		if ( empty( $context ) ) {
			$context = array(
				'filter'   => current_filter(),
				'function' => __FUNCTION__,
			);
		}

		$session_id   = $order->get_meta( '_wc_ledyer_session_id' );
		$ledyer_order = Ledyer_Payments()->api()->get_session( $session_id );
		if ( is_wp_error( $ledyer_order ) ) {
			$context['sessionId'] = $session_id;
			Ledyer_Payments()->logger()->error( '[CONFIRM]: Failed to get Ledyer session.', $context );
			return $ledyer_order;
		}

		// The checkout normally creates the Ledyer order. If that failed, create it here. Ledyer returns the same order if it already exists for the token.
		if ( 'authorized' === ( $ledyer_order['state'] ?? '' ) && empty( $ledyer_order['orderId'] ) ) {
			// Use the authorization from Ledyer instead of trusting the public webhook payload.
			$result = empty( $ledyer_order['authorizationToken'] )
				? new \WP_Error( 'missing_authorization', 'Ledyer session has no authorization token.' )
				: Ledyer_Payments()->api()->create_order( $order->get_id(), $ledyer_order['authorizationToken'] );
			if ( ! is_wp_error( $result ) && empty( $result['orderId'] ) ) {
				$result = new \WP_Error( 'missing_order_id', 'Ledyer order creation returned no order ID.' );
			}

			if ( is_wp_error( $result ) ) {
				Ledyer_Payments()->logger()->error( "[CONFIRM]: Failed to create Ledyer order: {$result->get_error_message()}", $context );

				return $result;
			}

			$ledyer_order['orderId'] = $result['orderId'];
			$order->update_meta_data( 'ledyer_payments_payment_id', $result['orderId'] );
		}

		// The orderId is not available when the purchase is awaiting signatory.
		$payment_id = $ledyer_order['orderId'] ?? '';

		$order->set_payment_method( $this->id );
		$order->set_transaction_id( $payment_id );

		$env = wc_string_to_bool( Ledyer_Payments()->settings( 'test_mode' ) ?? 'no' ) ? 'sandbox' : 'production';
		$order->update_meta_data( '_wc_ledyer_environment', $env );
		$order->update_meta_data( '_wc_ledyer_session_id', $ledyer_order['id'] );

		if ( 'authorized' === $ledyer_order['state'] ) {
			$order->update_meta_data( '_wc_ledyer_order_id', $payment_id );
			$order->save();

			// The order status is set based on the payment status, the same way as in Ledyer Checkout.
			$acknowledged   = false;
			$payment_status = Ledyer_Payments()->api()->get_payment_status( $payment_id );
			if ( is_wp_error( $payment_status ) ) {
				$context['paymentId'] = $payment_id;
				Ledyer_Payments()->logger()->error( '[CONFIRM]: Failed to get Ledyer payment status. The order status will be updated by callback.', $context );

				if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
					$order->update_status( 'on-hold', __( 'Awaiting payment confirmation from Ledyer.', 'ledyer-payments-for-woocommerce' ) );
				}
				$order->save();
				return $payment_status;
			} else {
				$acknowledged = $this->process_order_status( $order, $payment_status, $payment_id );
			}

			if ( ! $acknowledged ) {
				$this->acknowledge_order( $payment_id, $context );
			}
		} elseif ( 'awaitingSignatory' === $ledyer_order['state'] ) {
			$order->update_status( 'on-hold', __( 'Awaiting payment confirmation from Ledyer.', 'ledyer-payments-for-woocommerce' ) );
		} else {
			Ledyer_Payments()->logger()->warning( "[CONFIRM]: Unknown order state: {$ledyer_order['state']}", $context );
		}

		$order->save();
		return true;
	}

	/**
	 * Update the order status based on the Ledyer payment status.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param array     $payment_status The Ledyer payment status response.
	 * @param string    $ledyer_order_id The Ledyer order ID.
	 * @return bool Whether the order was acknowledged.
	 */
	public function process_order_status( $order, $payment_status, $ledyer_order_id ) {
		$context = array(
			'filter'          => current_filter(),
			'function'        => __FUNCTION__,
			'order_id'        => $order->get_id(),
			'ledyer_order_id' => $ledyer_order_id,
			'payment_status'  => $payment_status['status'] ?? '',
		);

		$note      = sanitize_text_field( $payment_status['note'] ?? '' );
		$ack_order = false;
		switch ( $payment_status['status'] ?? '' ) {
			case PaymentStatus::ORDER_PENDING:
				if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
					// translators: %1$s: Ledyer order ID, %2$s: Ledyer note.
					$order->update_status( 'on-hold', sprintf( __( 'New session created in Ledyer with Payment ID %1$s. %2$s', 'ledyer-payments-for-woocommerce' ), $ledyer_order_id, $note ) );
				}
				break;
			case PaymentStatus::PAYMENT_PENDING:
				if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
					// translators: %1$s: Ledyer order ID, %2$s: Ledyer note.
					$order->update_status( 'on-hold', sprintf( __( 'New pending payment created in Ledyer with Payment ID %1$s. %2$s', 'ledyer-payments-for-woocommerce' ), $ledyer_order_id, $note ) );
					$ack_order = true;
				}
				break;
			case PaymentStatus::PAYMENT_CONFIRMED:
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					// translators: %1$s: Ledyer order ID, %2$s: Ledyer note.
					$order->add_order_note( sprintf( __( 'Payment successfully confirmed in Ledyer with Payment ID %1$s. %2$s', 'ledyer-payments-for-woocommerce' ), $ledyer_order_id, $note ) );
					$ack_order = true;

					// If the order does not need payment, we need to ensure the "ready-for-capture" event has been triggered.
					$ready_for_capture = $order->get_meta( '_ledyer_ready_for_capture', true );
					if ( ! $order->needs_payment() && empty( $ready_for_capture ) ) {
						$order->update_meta_data( '_ledyer_waiting_on_ready_for_capture', true );
						$order->save();
						Ledyer_Payments()->logger()->debug( '[ORDER STATUS]: Order is paid but waiting for the ready for capture event.', $context );
						break;
					}

					$order->payment_complete( $ledyer_order_id );
				}
				break;
			case PaymentStatus::ORDER_CAPTURED:
				/**
				 * Filters the order status to set when the Ledyer order has been captured.
				 *
				 * @param string $new_status The new order status. Default 'completed'.
				 * @param array  $payment_status The Ledyer payment status response.
				 */
				$new_status = apply_filters( 'ledyer_payments_captured_update_status', 'completed', $payment_status );
				$order->update_status( $new_status );
				break;
			case PaymentStatus::ORDER_REFUNDED:
				$order->update_status( 'refunded' );
				break;
			case PaymentStatus::ORDER_CANCELLED:
				$order->update_status( 'cancelled' );
				break;
			default:
				Ledyer_Payments()->logger()->debug( '[ORDER STATUS]: No status change for payment status.', $context );
				break;
		}

		if ( $ack_order ) {
			$this->acknowledge_order( $ledyer_order_id, $context );
		}

		return $ack_order;
	}

	/**
	 * Acknowledge the order in Ledyer.
	 *
	 * @param string $ledyer_order_id The Ledyer order ID.
	 * @param array  $context The logging context.
	 * @return void
	 */
	private function acknowledge_order( $ledyer_order_id, $context ) {
		$response = Ledyer_Payments()->api()->acknowledge_order( $ledyer_order_id );
		if ( is_wp_error( $response ) ) {
			Ledyer_Payments()->logger()->error( "[ACKNOWLEDGE]: Failed to acknowledge order: {$response->get_error_message()}", $context );
		}
	}

	/**
	 * Get order by payment ID or reference.
	 *
	 * For orders awaiting signatory, the order reference is used as the payment ID. Otherwise, the orderId from Ledyer.
	 *
	 * @param string $session_id Payment ID or reference.
	 * @return \WC_Order|bool The WC_Order or false if not found.
	 */
	public function get_order_by_session_id( $session_id ) {
		$key    = '_wc_ledyer_session_id';
		$args   = array(
			'meta_key'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => $session_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare' => '=',
			'order'        => 'DESC',
			'orderby'      => 'date',
			'limit'        => 1,
		);
		$orders = wc_get_orders( $args );

		$order = reset( $orders );
		if ( empty( $order ) || $session_id !== $order->get_meta( $key ) ) {
			return false;
		}

		return $order ?? false;
	}

	/**
	 * Get order by Ledyer order ID.
	 *
	 * @param string $ledyer_order_id The Ledyer order ID.
	 * @return \WC_Order|bool The WC_Order or false if not found.
	 */
	public function get_order_by_ledyer_order_id( $ledyer_order_id ) {
		$key    = '_wc_ledyer_order_id';
		$args   = array(
			'meta_key'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'   => $ledyer_order_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_compare' => '=',
			'order'        => 'DESC',
			'orderby'      => 'date',
			'limit'        => 1,
			'date_created' => '>' . ( time() - MONTH_IN_SECONDS ),
		);
		$orders = wc_get_orders( $args );

		$order = reset( $orders );
		if ( empty( $order ) || $ledyer_order_id !== $order->get_meta( $key ) ) {
			return false;
		}

		return $order;
	}

	/**
	 * Processes the order confirmation if the required parameters are set.
	 *
	 * Since the `woocommerce_thankyou` hook might be omitted by certain themes, we've opted to use the init hook instead.
	 *
	 * @hook init
	 *
	 * @return void
	 */
	public function maybe_confirm_order() {
		$key     = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$gateway = filter_input( INPUT_GET, 'gateway', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $key ) || $this->id !== $gateway ) {
			return;
		}

		$order_id = wc_get_order_id_by_order_key( $key );

		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
			'order_id' => $order_id,
			'key'      => $key,
		);
		Ledyer_Payments()->logger()->debug( '[MAYBE_CONFIRM]: Customer refreshed or redirected to thankyou page.', $context );

		$order = wc_get_order( $order_id );
		if ( ! hash_equals( $order->get_order_key(), $key ) ) {
			Ledyer_Payments()->logger()->error( '[MAYBE_CONFIRM]: Order key mismatch.', $context );
			return;
		}

		// On-hold orders are moved forward by callbacks only, the same way as in Ledyer Checkout.
		if ( ! empty( $order->get_date_paid() ) || $order->has_status( 'on-hold' ) ) {
			// Check for if the session wasn't cleared properly. This can happen if the order is successfully created, but the customer was not redirected to the checkout page.
			$session_id = Ledyer_Payments()->session()->get_id();
			if ( $order->get_meta( '_wc_ledyer_session_id' ) === $session_id ) {
				Ledyer_Payments()->logger()->debug( '[MAYBE_CONFIRM]: Order already confirmed, but session still remained. Session is now cleared.', $context );
				Ledyer_Payments()->session()->clear_session( $order );
			}

			Ledyer_Payments()->logger()->debug( '[MAYBE_CONFIRM]: Order already confirmed. Customer probably refreshed thankyou page.', $context );
			return;
		}

		$this->confirm_order( $order, $context );
		Ledyer_Payments()->session()->clear_session( $order );
	}
}
