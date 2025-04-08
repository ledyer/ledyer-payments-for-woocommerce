<?php
/**
 * Class Assets.
 *
 * Assets management.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets.
 */
class Assets {

	const SDK_HANDLE      = 'ledyer-payments-bootstrap';
	const CHECKOUT_HANDLE = 'ledyer-payments-for-woocommerce';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// The client SDK requires that <script> tag ID is set to 'ledyer-payments'.
		add_action( 'script_loader_tag', array( $this, 'script_loader_tag' ), 10, 2 );
	}

	/**
	 * Enqueues the scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! wc_string_to_bool( Ledyer_Payments()->settings( 'enabled' ) ) ) {
			return;
		}

		// Since order received is simply considered a checkout page but with additional query variables, we must explicitly exclude it to prevent a new session from being created.
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		// The reference is stored in the session. Create the session if necessary.
		Ledyer_Payments()->session()->get_session();
		$reference  = Ledyer_Payments()->session()->get_reference();
		$session_id = Ledyer_Payments()->session()->get_id();

		$standard_woo_checkout_fields = array(
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_city',
			'billing_phone',
			'billing_email',
			'billing_state',
			'billing_country',
			'billing_company',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_postcode',
			'shipping_city',
			'shipping_state',
			'shipping_country',
			'shipping_company',
			'terms',
			'terms-field',
			'_wp_http_referer',
		);

		$src          = plugins_url( 'src/assets/js/ledyer-payments.js', LEDYER_PAYMENTS_MAIN_FILE );
		$dependencies = array( 'jquery' );
		wp_register_script( self::CHECKOUT_HANDLE, $src, $dependencies, LEDYER_PAYMENTS_VERSION, false );

		$pay_for_order = is_wc_endpoint_url( 'order-pay' );
		wp_localize_script(
			self::CHECKOUT_HANDLE,
			'LedyerPaymentsParams',
			array(
				'sessionId'                 => $session_id,
				'changePaymentMethodNonce'  => wp_create_nonce( 'ledyer_payments_change_payment_method' ),
				'changePaymentMethodUrl'    => \WC_AJAX::get_endpoint( 'ledyer_payments_change_payment_method' ),
				'logToFileNonce'            => wp_create_nonce( 'ledyer_payments_wc_log_js' ),
				'logToFileUrl'              => \WC_AJAX::get_endpoint( 'ledyer_payments_wc_log_js' ),
				'createOrderNonce'          => wp_create_nonce( 'ledyer_payments_create_order' ),
				'createOrderUrl'            => \WC_AJAX::get_endpoint( 'ledyer_payments_create_order' ),
				'pendingPaymentNonce'       => wp_create_nonce( 'ledyer_payments_pending_payment' ),
				'pendingPaymentUrl'         => \WC_AJAX::get_endpoint( 'ledyer_payments_pending_payment' ),
				'payForOrder'               => $pay_for_order,
				'standardWooCheckoutFields' => $standard_woo_checkout_fields,
				'submitOrderUrl'            => \WC_AJAX::get_endpoint( 'checkout' ),
				'gatewayId'                 => 'ledyer_payments',
				'reference'                 => $reference,
				'companyNumberPlacement'    => Ledyer_Payments()->settings( 'company_number_placement' ),
				'i18n'                      => array(
					'companyNumberMissing' => __( 'Please enter a company number.', 'ledyer-payments-for-woocommerce' ),
					'genericError'         => __( 'Something went wrong. Please try again or contact the store.', 'ledyer-payments-for-woocommerce' ),
				),
			)
		);

		wp_enqueue_script( self::CHECKOUT_HANDLE );

		$env = wc_string_to_bool( Ledyer_Payments()->settings( 'test_mode' ) ) ? 'sandbox' : 'live';
		wp_enqueue_script( self::SDK_HANDLE, "https://payments.$env.ledyer.com/bootstrap.js", array( self::CHECKOUT_HANDLE ), LEDYER_PAYMENTS_VERSION, true );
	}

	/**
	 * Modifies the script loader tag for a specific handle.
	 *
	 * This method is responsible for modifying the script loader tag for a specific handle.
	 * It checks if the handle matches the SDK handle and if so, it replaces the ID attribute
	 * in the tag with a new value.
	 *
	 * @param string $tag    The original script loader tag.
	 * @param string $handle The handle of the script being loaded.
	 *
	 * @return string The modified script loader tag.
	 */
	public function script_loader_tag( $tag, $handle ) {
		if ( self::SDK_HANDLE !== $handle ) {
			return $tag;
		}

		$pattern     = '/id="([^"]*)"/';
		$replacement = 'id="ledyer-payments"';
		return preg_replace( $pattern, $replacement, $tag );
	}
}
