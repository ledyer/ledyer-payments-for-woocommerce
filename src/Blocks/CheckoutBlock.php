<?php
/**
 * Class CheckoutBlock.
 *
 * Integrates Ledyer Payments with the WooCommerce Checkout block.
 */

namespace Krokedil\Ledyer\Payments\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Krokedil\Ledyer\Payments\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CheckoutBlock.
 */
class CheckoutBlock extends AbstractPaymentMethodType {

	const SCRIPT_HANDLE = 'ledyer-payments-checkout-block';

	/**
	 * The payment method name. Must match the gateway ID.
	 *
	 * @var string
	 */
	protected $name = 'ledyer_payments';

	/**
	 * Initialize the payment method type.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_ledyer_payments_settings', array() );
	}

	/**
	 * Whether the payment method is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return wc_string_to_bool( $this->get_setting( 'enabled', 'no' ) );
	}

	/**
	 * Register the block script and return its handle.
	 *
	 * The Ledyer SDK is registered as a dependency so that it is loaded before the block script is executed.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path = LEDYER_PAYMENTS_PLUGIN_PATH . '/blocks/build/checkout.asset.php';
		if ( ! file_exists( $asset_path ) ) {
			return array();
		}

		$asset = require $asset_path;

		Assets::register_sdk();
		wp_register_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'blocks/build/checkout.js', LEDYER_PAYMENTS_MAIN_FILE ),
			array_merge( $asset['dependencies'], array( Assets::SDK_HANDLE ) ),
			$asset['version'],
			true
		);

		return array( self::SCRIPT_HANDLE );
	}

	/**
	 * The data passed to the block script, available through `getSetting( 'ledyer_payments_data' )`.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateway = WC()->payment_gateways() ? ( WC()->payment_gateways()->payment_gateways()[ $this->name ] ?? null ) : null;

		// The Ledyer session is not created here, since this data is also output on the cart block and in the editor. It is created (or updated) in Gateway::process_payment() when the order is placed.
		return array(
			'title'       => $this->get_setting( 'title', __( 'Ledyer Payments', 'ledyer-payments-for-woocommerce' ) ),
			'description' => $this->get_setting( 'redirect_description', '' ),
			'iconUrl'     => plugins_url( 'src/assets/img/ledyer-darkgray.svg', LEDYER_PAYMENTS_MAIN_FILE ),
			'features'    => $gateway ? array_values( $gateway->supports ) : array( 'products' ),
			'logToFile'   => array(
				'url'   => \WC_AJAX::get_endpoint( 'ledyer_payments_wc_log_js' ),
				'nonce' => wp_create_nonce( 'ledyer_payments_wc_log_js' ),
			),
			'createOrder' => array(
				'url' => \WC_AJAX::get_endpoint( 'ledyer_payments_create_order' ),
			),
			'pending'     => array(
				'url' => \WC_AJAX::get_endpoint( 'ledyer_payments_pending_payment' ),
			),
			'i18n'        => array(
				'companyNumber'        => __( 'Company number', 'ledyer-payments-for-woocommerce' ),
				'reference1'           => __( 'Customer reference 1 (optional)', 'ledyer-payments-for-woocommerce' ),
				'reference1Help'       => __( 'For example, reference code or cost center', 'ledyer-payments-for-woocommerce' ),
				'reference2'           => __( 'Customer reference 2 (optional)', 'ledyer-payments-for-woocommerce' ),
				'reference2Help'       => __( 'For example, purchase order number', 'ledyer-payments-for-woocommerce' ),
				'companyNumberMissing' => __( 'Please enter your company number.', 'ledyer-payments-for-woocommerce' ),
				'placeOrder'           => __( 'Pay with Ledyer', 'ledyer-payments-for-woocommerce' ),
				'notAuthorized'        => __( 'The payment was not completed. Please try again or choose another payment method.', 'ledyer-payments-for-woocommerce' ),
				'genericError'         => __( 'Something went wrong. Please try again or contact the store.', 'ledyer-payments-for-woocommerce' ),
			),
		);
	}
}
