<?php
namespace Ledyer\Payments\Requests\POST;

use Ledyer\Payments\Requests\POST;
use Ledyer\Payments\Requests\Helpers\Cart;

/**
 * Create session request class.
 */
class CreateSession extends POST {

	/**
	 * CreateSession constructor.
	 *
	 * @return array Arguments that should be accessible from within the request.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->log_title = 'Create session';
		$this->endpoint  = '/v1/payment-sessions';
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
			'reference'               => $cart->get_reference(),
			'settings'                => array(
				'security' => array(
					'level' => absint( $this->settings['security_level'] ),
				),
				'urls'     => array(
					'confirmation' => $cart->get_confirmation_url(),
					'notification' => $cart->get_notification_url(),
				),
			),
			'totalOrderAmount'        => $cart->get_total(),
			'totalOrderAmountExclVat' => $cart->get_subtotal(),
			'totalOrderVatAmount'     => $cart->get_total_tax(),
		);
	}
}
