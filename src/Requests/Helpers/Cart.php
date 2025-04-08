<?php
namespace Krokedil\Ledyer\Payments\Requests\Helpers;

use Krokedil\Ledyer\Payments\Callback;
use KrokedilLedyerPaymentsDeps\Krokedil\WooCommerce\Cart\Cart as CartBase;
use KrokedilLedyerPaymentsDeps\Krokedil\WooCommerce as KrokedilWC;

/**
 * Class Cart
 *
 * @package Krokedil\Ledyer\Payments\Requests\Helpers
 */
class Cart extends CartBase {

	/**
	 * Cart constructor.
	 */
	public function __construct() {
		$config = array(
			'slug'         => 'ledyer_payments',
			'price_format' => 'minor',
		);

		parent::__construct( WC()->cart, $config );
	}

	/**
	 * Get the Ledyer type mapping of the item.
	 *
	 * @param KrokedilWC\Cart\CartLineItem $item Item.
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
	 * Get the total amount of the item incl. tax.
	 *
	 * @param KrokedilWC\OrderLineData $item Item.
	 * @return float
	 */
	private function get_total_amount( $item ) {
		return $item->get_total_amount() + $item->get_total_tax_amount();
	}

	/**
	 * Get the order lines.
	 *
	 * @return array
	 */
	public function get_order_lines() {
		$order_lines = array();

		/**
		 * @var KrokedilWC\OrderLineData $item
		 */
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

	/**
	 * Get the locale.
	 *
	 * @return string
	 */
	public function get_locale() {
		$locale = get_locale();
		switch ( $locale ) {
			case 'fi':
				$locale = 'fi_fi';
				break;
			default:
				break;
		}

		return str_replace( '_', '-', $locale );
	}

	/**
	 * Get the country.
	 *
	 * @return string
	 */
	public function get_country() {
		/* The billing country selected on the checkout page is to prefer over the store's base location. It makes more sense that we check for available payment methods based on the customer's country. */
		if ( method_exists( 'WC_Customer', 'get_billing_country' ) && ! empty( WC()->customer ) ) {
			$country = WC()->customer->get_billing_country();
			if ( ! empty( $country ) ) {
				return apply_filters( 'ledyer_payments_country', $country );
			}
		}

		/* Ignores whatever country the customer selects on the checkout page, and always uses the store's base location. Only used as fallback. */
		$base_location = wc_get_base_location();
		$country       = $base_location['country'];
		return apply_filters( 'ledyer_payments_country', $country );
	}

	/**
	 * Get the confirmation URL.
	 *
	 * This is the URL the customer is redirected to after a successful payment.
	 *
	 * @return float
	 */
	public function get_confirmation_url() {
		$url = add_query_arg(
			array(
				'session_id' => '{session_id}',
				'order_id'   => '{order_id}',
			),
			wc_get_checkout_url()
		);

		return apply_filters( 'ledyer_payments_confirmation_url', $url );
	}

	/**
	 * Get the notification URL.
	 *
	 * This is the URL Ledyer will send the payment status to (i.e., the callback URL).
	 *
	 * @return float
	 */
	public function get_notification_url() {
		return apply_filters( 'ledyer_payments_notification_url', home_url( Callback::API_ENDPOINT ) );
	}
}
