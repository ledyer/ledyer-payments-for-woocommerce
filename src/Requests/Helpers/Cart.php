<?php
namespace Ledyer\Payments\Requests\Helpers;

use Ledyer\Payments\Gateway;

class Cart extends \Krokedil\WooCommerce\Cart\Cart {

	private $settings = array();

	public function __construct() {
		$this->settings = get_option( 'woocommerce_' . Gateway::ID . '_settings', array() );

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

	public function get_order_lines() {
		$order_lines = array();

		foreach ( $this->get_line_items() as $item ) {
			$order_lines[] = array(
				'description'    => $item->get_name(),
				'quantity'       => $item->get_quantity(),
				'reference'      => $item->get_sku(),
				'totalAmount'    => $this->get_total_amount( $item ),
				'totalVatAmount' => $item->get_total_tax_amount(),
				'type'           => $this->get_type( $item ),
				'vat'            => $item->get_tax_rate(),
			);
		}

		foreach ( $this->get_line_shipping() as $item ) {
			$order_lines[] = array(
				'description'    => $item->get_name(),
				'quantity'       => $item->get_quantity(),
				'reference'      => $item->get_sku(),
				'totalAmount'    => $this->get_total_amount( $item ),
				'totalVatAmount' => $item->get_total_tax_amount(),
				'type'           => $this->get_type( $item ),
				'vat'            => $item->get_tax_rate(),
			);

		}

		return $order_lines;
	}

	public function get_country() {
		/* The billing country selected on the checkout page is to prefer over the store's base location. It makes more sense that we check for available payment methods based on the customer's country. */
		if ( method_exists( 'WC_Customer', 'get_billing_country' ) && ! empty( WC()->customer ) ) {
			$country = WC()->customer->get_billing_country();
			if ( ! empty( $country ) ) {
				return apply_filters( Gateway::ID . '_country', $country );
			}
		}

		/* Ignores whatever country the customer selects on the checkout page, and always uses the store's base location. Only used as fallback. */
		$base_location = wc_get_base_location();
		$country       = $base_location['country'];
		return apply_filters( Gateway::ID . '_country', $country );
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


	public function get_confirmation_url() {
		$url = add_query_arg(
			array(
				'session_id' => '{session_id}',
				'order_id'   => '{order_id}',
			),
			wc_get_checkout_url()
		);

		return apply_filters( Gateway::ID . '_confirmation_url', $url );
	}

	public function get_notification_url() {
		$url = add_query_arg( 'gateway', Gateway::ID, home_url() );
		return apply_filters( Gateway::ID . '_notification_url', $url );
	}
}
