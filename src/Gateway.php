<?php
/**
 * Class Gateway.
 *
 * Register the Ledyer Payments payment gateway.
 */

namespace Ledyer\Payments;

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

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			)
		);
	}

	/**
	 * Initialize settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = Settings::setting_fields();

		// Delete the access token whenever the settings are modified.
		add_action( 'update_option_woocommerce_ledyer_payments_settings', array( '\Ledyer\Payments\Settings', 'maybe_update_access_token' ) );
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
	 * Whether the payment gateway should be enabled.
	 *
	 * @return boolean
	 */
	public function is_available() {
		return 'yes' === $this->enabled;
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id WooCommerced order id.
	 * @return array An associative array containing the success status and redirect URl.
	 */
	public function process_payment( $order_id ) {
		return array( 'result' => 'success' );
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
}
