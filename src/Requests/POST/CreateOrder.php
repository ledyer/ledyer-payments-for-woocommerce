<?php
namespace Krokedil\Ledyer\Payments\Requests\POST;

use Krokedil\Ledyer\Payments\Requests\POST;
use Krokedil\Ledyer\Payments\Requests\Helpers\Order;

/**
 * Create order request class.
 *
 * Acknowledges an order.
 */
class CreateOrder extends POSTRequest {

	/**
	 * CreateSession constructor.
	 *
	 * @param int    $order_id   The WC order ID.
	 * @param string $auth_token The Ledyer auth token.
	 */
	public function __construct( $order_id, $auth_token ) {
		$args = get_defined_vars();

		parent::__construct( $args );
		$this->log_title = 'Create order';
		$this->endpoint  = "/v1/authorization-tokens/{$auth_token}/order";
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	public function get_body() {
		$order = new Order( $this->arguments['order_id'] );

		return array(
			'country'                 => WC()->customer->get_billing_country(),
			'currency'                => $order->get_currency(),
			'customer'                => $order->get_customer(),
			'locale'                  => str_replace( '_', '-', get_locale() ),
			'orderLines'              => $order->get_order_lines(),
			'reference'               => $order->get_reference(),
			'storeId'                 => Ledyer_Payments()->settings( 'store_id' ),
			'totalOrderAmount'        => $order->get_total(),
			'totalOrderAmountExclVat' => $order->get_total() - $order->get_total_tax(),
			'totalOrderVatAmount'     => $order->get_total_tax(),
		);
	}
}
