<?php
namespace Krokedil\Ledyer\Payments\Requests\POST;

use Krokedil\Ledyer\Payments\Requests\POSTRequest;
use Krokedil\Ledyer\Payments\Requests\Helpers\Cart;

/**
 * Update checkout session request class.
 */
class UpdateSession extends POSTRequest {

	/**
	 * UpdateSession constructor.
	 *
	 * @param string $session_id The Ledyer session ID.
	 */
	public function __construct( $session_id ) {
		parent::__construct();
		$this->log_title = 'Update session';
		$this->endpoint  = "/v1/payment-sessions/$session_id";
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	public function get_body() {
		$cart = new Cart();

		return array(
			'country'                 => WC()->customer->get_billing_country(),
			'currency'                => get_woocommerce_currency(),
			'locale'                  => $cart->get_locale(),
			'orderLines'              => $cart->get_order_lines(),
			'reference'               => Ledyer_Payments()->session()->get_reference(),
			'settings'                => array(
				'security' => array(
					'level' => absint( Ledyer_Payments()->settings( 'security_level' ) ),
				),
			),
			'totalOrderAmount'        => $cart->get_total(),
			'totalOrderAmountExclVat' => $cart->get_total() - $cart->get_total_tax(),
			'totalOrderVatAmount'     => $cart->get_total_tax(),
		);
	}
}
