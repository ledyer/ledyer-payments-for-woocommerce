<?php
namespace Krokedil\Ledyer\Payments\Requests\POST;

use Krokedil\Ledyer\Payments\Requests\POST;
use Krokedil\Ledyer\Payments\Requests\Helpers\Cart;

/**
 * Create checkout session request class.
 */
class CreateSession extends POST {

	/**
	 * CreateSession constructor.
	 *
	 * @return array Arguments that should be accessible from within the request.
	 */
	public function __construct() {
		parent::__construct();
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
			'reference'               => Ledyer()->session()->get_reference(),
			'settings'                => array(
				'security' => array(
					'level' => absint( $this->settings['security_level'] ),
				),
				'urls'     => array(
					'confirmation' => $cart->get_confirmation_url(),
					'notification' => $cart->get_notification_url(),
				),
			),
			'storeId'                 => $this->settings['store_id'],
			'totalOrderAmount'        => $cart->get_total(),
			'totalOrderAmountExclVat' => $cart->get_total() - $cart->get_total_tax(),
			'totalOrderVatAmount'     => $cart->get_total_tax(),
		);
	}
}
