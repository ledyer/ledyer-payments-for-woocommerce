<?php
namespace Ledyer\Payments\Requests\POST;

use Ledyer\Payments\Requests\POST;
use Krokedil\WooCommerce\Cart;

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
		$this->endpoint  = '/v1/payment-session';
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	public function get_body() {
		$config = array(
			'slug'         => 'lp',
			'price_format' => 'minor',
		);

		$cart = new Cart( WC()->cart, $config );

		foreach ( $cart->get_items() as $item ) {
			$order_lines[] = array(
				'description'    => $item->get_name(),
				'quantity'       => $item->get_quantity(),
				'reference'      => $item->get_sku(),
				'totalAmount'    => $item->get_total(),
				'totalVatAmount' => $item->get_total_tax(),
				'type'           => $item->get_type(),
				'unitPrice'      => $item->get_price(),
				'vat'            => $item->get_tax_rate(),
			);
		}

		return array(
			'country'    => WC()->customer->get_billing_country(),
			'currency'   => get_woocommerce_currency(),
			'orderLines' => $order_lines,
			'settings'   => array(
				'security' => array(
					'level' => absint( $this->settings['security_level'] ),
				),
			),
		);
	}
}
