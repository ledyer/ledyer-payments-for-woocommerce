<?php
/**
 * Class Gateway.
 *
 * Register the Ledyer Payments payment gateway.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gateway extends \WC_Payment_Gateway {

	public const ID = 'ledyer_payments';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = self::ID;
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

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);

		add_filter( 'wc_get_template', array( $this, 'payment_categories' ), 10, 3 );
	}

	/**
	 * Initialize settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = Settings::setting_fields();

		// Delete the access token whenever the settings are modified.
		add_action( 'update_option_woocommerce_' . self::ID . '_settings', array( __NAMESPACE__ . '\Settings', 'maybe_update_access_token' ) );
	}


	/**
	 * The payment gateway icon that will appear on the checkout page.
	 *
	 * @return string
	 */
	public function get_icon() {
		$image_url = 'https://developers.ledyer.com/img/logo-lightmode.svg';
		return "<img src='{$image_url}' style='max-width: 90%' alt='Ledyer Payments logo' />";
	}

	/**
	 * Whether the payment gateway is available.
	 *
	 * @filter Gateway::ID . '_is_available'
	 *
	 * @return boolean
	 */
	public function is_available() {
		return apply_filters( self::ID . '_is_available', $this->check_availability() );
	}

	/**
	 * Check if the gateway should be available.
	 *
	 * This function is extracted to create the 'Gateway::ID . _is_available' filter.
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
		$order = wc_get_order( $order_id );

		return array(
			'redirect' => $order->get_checkout_order_received_url(),
			'result'   => 'success',
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

		if ( 'checkout/payment-method.php' !== $template_name || self::ID !== $args['gateway']->id ) {
			return $located;
		}

		return untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/payment-categories.php';
	}
}
