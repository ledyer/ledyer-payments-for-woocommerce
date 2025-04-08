<?php
namespace Krokedil\Ledyer\Payments\Requests\POST;

use Krokedil\Ledyer\Payments\Requests\POSTRequest;
use Krokedil\Ledyer\Payments\Requests\Helpers\Cart;

/**
 * Create checkout session request class.
 */
class CreateSession extends POSTRequest {

	/**
	 * CreateSession constructor.
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
			'locale'                  => $cart->get_locale(),
			'orderLines'              => $cart->get_order_lines(),
			'reference'               => Ledyer_Payments()->session()->get_reference(),
			'settings'                => array(
				'security' => array(
					'level' => absint( Ledyer_Payments()->settings( 'security_level' ) ),
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
