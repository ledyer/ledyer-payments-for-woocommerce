<?php
namespace Ledyer\Payments\Requests\POST;

use Ledyer\Payments\Requests\POST;
use Krokedil\WooCommerce\Cart\Cart;

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
		$config = array(
			'slug'         => 'lp',
			'price_format' => 'minor',
		);

		$cart = new Cart( WC()->cart, $config );

		foreach ( $cart->get_line_items() as $item ) {
			$order_lines[] = array(
				'description'    => $item->get_name(),
				'quantity'       => $item->get_quantity(),
				'reference'      => $item->get_sku(),
				'totalAmount'    => $item->get_total_amount(),
				'totalVatAmount' => $item->get_total_tax_amount(),
				'type'           => $item->get_type(),
				'unitPrice'      => $item->get_unit_price(),
				'vat'            => $item->get_tax_rate(),
			);
		}

		foreach ( $cart->get_line_shipping() as $item ) {
			$order_lines[] = array(
				'description'    => $item->get_name(),
				'quantity'       => $item->get_quantity(),
				'reference'      => $item->get_sku(),
				'totalAmount'    => $item->get_total_amount(),
				'totalVatAmount' => $item->get_total_tax_amount(),
				'type'           => 'shippingFee',
				'unitPrice'      => $item->get_unit_price(),
				'vat'            => $item->get_tax_rate(),
			);
		}

		return array(
			'country'                 => WC()->customer->get_billing_country(),
			'currency'                => get_woocommerce_currency(),
			'locale'                  => str_replace( '_', '-', get_locale() ),
			'orderLines'              => $order_lines,
			// 'reference' => '',
			'settings'                => array(
				'security' => array(
					'level' => absint( $this->settings['security_level'] ),
				),
			),
			'storeId'                 => preg_replace( '/(https?:\/\/|www.|\/\s*$)/i', '', get_home_url() ),
			'source'                  => 'online',
			'totalOrderAmount'        => $cart->get_total(),
			'totalOrderAmountExclVat' => $cart->get_subtotal(),
			'totalOrderVatAmount'     => $cart->get_total_tax(),
		);
	}
}
