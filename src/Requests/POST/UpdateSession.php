<?php
namespace Krokedil\Ledyer\Payments\Requests\POST;

use Krokedil\Ledyer\Payments\Requests\POST;
use Krokedil\Ledyer\Payments\Requests\Helpers\Cart;

/**
 * Update checkout session request class.
 */
class UpdateSession extends POST {

	/**
	 * UpdateSession constructor.
	 *
	 * @return array Arguments that should be accessible from within the request.
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
			'locale'                  => str_replace( '_', '-', get_locale() ),
			'orderLines'              => $cart->get_order_lines(),
			'reference'               => Ledyer()->session()->get_reference(),
			'settings'                => array(
				'security' => array(
					'level' => absint( $this->settings['security_level'] ),
				),
			),
			'totalOrderAmount'        => $cart->get_total(),
			'totalOrderAmountExclVat' => $cart->get_total() - $cart->get_total_tax(),
			'totalOrderVatAmount'     => $cart->get_total_tax(),
		);
	}
}
