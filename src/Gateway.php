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
				'refunds',
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
		add_action( 'update_option_woocommerce_ledyer_payments_settings', array( __NAMESPACE__ . '\Settings', 'maybe_update_access_token' ) );
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
				'placeholder'       => __( 'Enter your organization number', 'ledyer-payments-for-woocommerce' ),
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
			Ledyer_Payments()->logger()->error( 'Failed to get Ledyer order. Unrecoverable error, aborting.', $context );
			return;
		}

		// The orderId is not available when the purchase is awaiting signatory.
		$payment_id = wc_get_var( $ledyer_order['orderId'] );
		if ( 'authorized' === $ledyer_order['state'] ) {
			$order->payment_complete( $payment_id );
		} elseif ( 'awaitingSignatory' === $ledyer_order['state'] ) {
			$order->update_status( 'on-hold', __( 'Awaiting payment confirmation from Ledyer.', 'ledyer-payments-for-woocommerce' ) );
		} else {
			Ledyer_Payments()->logger()->warning( "Unknown order state: {$ledyer_order['state']}", $context );
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
	 * Processes the payment on the thankyou page.
	 *
	 * @hook woocommerce_thankyou
	 *
	 * @param int $order_id The WC order id.
	 * @return void
	 */
	public function redirect_from_checkout( $order_id ) {
		$key     = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$gateway = filter_input( INPUT_GET, 'gateway', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( $this->id !== $gateway ) {
			return;
		}

		$context = array(
			'filter'   => current_filter(),
			'function' => __FUNCTION__,
			'order_id' => $order_id,
			'key'      => $key,
		);
		Ledyer_Payments()->logger()->debug( 'Customer refreshed or redirected to thankyou page.', $context );

		$order = wc_get_order( $order_id );
		if ( ! hash_equals( $order->get_order_key(), $key ) ) {
			Ledyer_Payments()->logger()->error( 'Order key mismatch.', $context );
			return;
		}

		if ( ! empty( $order->get_date_paid() ) ) {
			Ledyer_Payments()->logger()->debug( 'Order already paid. Customer probably refreshed thankyou page.', $context );
			return;
		}

		$this->confirm_order( $order, $context );
		Ledyer_Payments()->session()->clear_session( $order );
	}
}
