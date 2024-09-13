<?php
namespace Ledyer\Payments\Requests\Helpers;

use Ledyer\Payments\Gateway;

class Cart extends \Krokedil\WooCommerce\Cart\Cart {

	public function __construct() {
		// TODO: Move config to the plugin's main file.
		$config = array(
			'slug'         => 'lp',
			'price_format' => 'minor',
		);

		parent::__construct( WC()->cart, $config );
	}

	/**
	 * Get the Ledyer type mapping of the item.
	 *
	 * @param \Krokedil\WooCommerce\OrderLineData $item Item.
	 * @return string
	 */
	private function get_type( $item ) {
		$type = $item->get_type();
		switch ( $type ) {
			case 'simple':
			case 'variation':
				return 'physical';
			case 'shipping':
				return 'shippingFee';
			default:
				return $type;
		}
	}

	/**
	 * Get the total amount of the item.
	 *
	 * @param \Krokedil\WooCommerce\OrderLineData $item Item.
	 * @return float
	 */
	private function get_total_amount( $item ) {
		return $item->get_total_amount() + $item->get_total_tax_amount();
	}

	private function get_total_discount_amount( $item ) {
		return $item->get_total_discount_amount() + $item->get_total_discount_tax_amount();
	}

	public function get_order_lines() {
		$order_lines = array();

		foreach ( $this->get_line_items() as $item ) {
			$order_lines[] = array(
				'description'        => $item->get_name(),
				'quantity'           => $item->get_quantity(),
				'reference'          => $item->get_sku(),
				'totalAmount'        => $this->get_total_amount( $item ),
				'totalVatAmount'     => $item->get_total_tax_amount(),
				'unitDiscountAmount' => $this->get_total_discount_amount( $item ),
				'type'               => $this->get_type( $item ),
				'unitPrice'          => $item->get_unit_price(),
				'vat'                => $item->get_tax_rate(),
			);
		}

		foreach ( $this->get_line_shipping() as $item ) {
			$order_lines[] = array(
				'description'        => $item->get_name(),
				'quantity'           => $item->get_quantity(),
				'reference'          => $item->get_sku(),
				'totalAmount'        => $this->get_total_amount( $item ),
				'totalVatAmount'     => $item->get_total_tax_amount(),
				'unitDiscountAmount' => $this->get_total_discount_amount( $item ),
				'type'               => $this->get_type( $item ),
				'unitPrice'          => $item->get_unit_price(),
				'vat'                => $item->get_tax_rate(),
			);

		}

		return $order_lines;
	}



	/**
	 * Get or create the cart reference if it doesn't already exist.
	 *
	 * @return string A 23-character unique reference.
	 */
	public function get_reference() {
		$key = Gateway::ID . '_cart_reference';

		$reference = WC()->session->get( $key );
		if ( empty( $reference ) ) {
			$reference = uniqid( '', true );
			WC()->session->set( $key, $reference );
		}

		return $reference;
	}
}
