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
			$this->id . '_supports',
			array(
				'products',
			)
		);
		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
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
		add_action( 'init', array( $this, 'maybe_confirm_order' ), 999 );

		// Process the checkout before the payment is processed.
		add_action( 'woocommerce_checkout_process', array( $this, 'process_checkout' ) );

		// Process the custom checkout fields that we inject to the checkout form (e.g., company number field).
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'process_custom_checkout_fields' ) );
	}

	/**
	 * Check if the gateway is the chosen payment method.
	 *
	 * Due to inconsistencies in the gateway name (e.g., ledyer_payments vs. ledyer_payments_invoice), we need to check for the presence of 'ledyer_payments'.
	 *
	 * @param int|null|\WC_Order $order_id The WooCommerce order or its id.
	 * @return bool
	 */
	private function is_chosen_gateway( $order_id = null ) {
		if ( $order_id instanceof \WC_Order ) {
			$chosen_gateway = $order_id->get_payment_method();
		} elseif ( ! empty( $order_id ) ) {
			$order          = wc_get_order( $order_id );
			$chosen_gateway = $order->get_payment_method();
		} else {
			$chosen_gateway = WC()->session->get( 'chosen_payment_method' );
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
					'required' => 'true',
				),
			)
		);
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
			$order->save();
		}
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
	 * @return void
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
			Ledyer_Payments()->logger()->error( '[CONFIRM]: Failed to get Ledyer order. Unrecoverable error, aborting.', $context );
			return;
		}

		// The orderId is not available when the purchase is awaiting signatory.
		$payment_id = wc_get_var( $ledyer_order['orderId'] );
		if ( 'authorized' === $ledyer_order['state'] ) {
			$order->payment_complete( $payment_id );
		} elseif ( 'awaitingSignatory' === $ledyer_order['state'] ) {
			$order->update_status( 'on-hold', __( 'Awaiting payment confirmation from Ledyer.', 'ledyer-payments-for-woocommerce' ) );
		} else {
			Ledyer_Payments()->logger()->warning( "[CONFIRM: Unknown order state: {$ledyer_order['state']}", $context );
		}

		$order->set_payment_method( $this->id );
		$order->set_transaction_id( $payment_id );

		// orderId not available if state is awaitingSignatory.
		isset( $ledyer_order['orderId'] ) && $order->update_meta_data( '_wc_ledyer_order_id', $ledyer_order['orderId'] );

		$env = wc_string_to_bool( Ledyer_Payments()->settings( 'test_mode' ) ?? 'no' ) ? 'sandbox' : 'production';
		$order->update_meta_data( '_wc_ledyer_environment', $env );
		$order->update_meta_data( '_wc_ledyer_session_id', $ledyer_order['id'] );
		$order->save();
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

		return $order ?? false;
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

		if ( $this->id !== $gateway ) {
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

		if ( ! empty( $order->get_date_paid() ) ) {
			// Check for if the session wasn't clear properly. This can happen if the order is successfully created, but the customer was not redirected to the checkout page.
			$session_id = Ledyer_Payments()->session()->get_id();
			if ( $order->get_meta( '_wc_ledyer_session_id' ) === $session_id ) {
				Ledyer_Payments()->logger()->debug( '[MAYBE_CONFIRM]: Order already paid, but session still remained. Session is now cleared.', $context );
				Ledyer_Payments()->session()->clear_session( $order );
			}

			Ledyer_Payments()->logger()->debug( '[MAYBE_CONFIRM]: Order already paid. Customer probably refreshed thankyou page.', $context );
			return;
		}

		$this->confirm_order( $order, $context );
		Ledyer_Payments()->session()->clear_session( $order );
	}
}
