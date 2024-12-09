<?php
namespace Krokedil\Ledyer\Payments\Requests\Helpers;

use KrokedilLedyerPaymentsDeps\Krokedil\WooCommerce\Order\Order as BaseOrder;
use KrokedilLedyerPaymentsDeps\Krokedil\WooCommerce as KrokedilWC;
/**
 * Class Order
 *
 * @package Krokedil\Ledyer\Payments\Requests\Helpers
 */
class Order extends BaseOrder {
	/**
	 * Order constructor.
	 *
	 * @param \WC_Order|int|string $order The WooCommerce order or numeric id.
	 */
	public function __construct( $order ) {
		$config = array(
			'slug'         => 'ledyer_payments',
			'price_format' => 'minor',
		);

		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order );
		}

		parent::__construct( $order, $config );
	}

	/**
	 * Get the Ledyer type mapping of the item.
	 *
	 * @param KrokedilWC\OrderLineData $item Item.
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
		return $this->order->get_billing_country();
	}

	/**
	 * Get the currency code.
	 *
	 * @return string The currency code.
	 */
	public function get_currency() {
		return $this->order->get_currency();
	}

	/**
	 * Get the order ID.
	 *
	 * @return string The order id.
	 */
	public function get_reference() {
		return $this->order->get_order_number();
	}

	/**
	 * Get the customer data, and format to match the Ledyer client SDK.
	 *
	 * @return array
	 */
	public function get_customer() {
		$customer_data = parent::get_customer();

		$customer = array(
			'companyId'       => $this->order->get_meta( '_billing_company_number' ),
			'email'           => $customer_data->get_billing_email(),
			'firstName'       => $customer_data->get_billing_first_name(),
			'lastName'        => $customer_data->get_billing_last_name(),
			'phone'           => $customer_data->get_billing_phone(),
			'reference1'      => $this->get_reference(),
			'reference2'      => '',
			'billingAddress'  => array(
				'attentionName' => $customer_data->get_billing_first_name(),
				'city'          => $customer_data->get_billing_city(),
				'companyName'   => $customer_data->get_billing_company(),
				'country'       => $customer_data->get_billing_country(),
				'postalCode'    => $customer_data->get_billing_postcode(),
				'streetAddress' => $customer_data->get_billing_address_1(),
			),
			'shippingAddress' => array(
				'attentionName' => $customer_data->get_shipping_first_name(),
				'city'          => $customer_data->get_shipping_city(),
				'companyName'   => $customer_data->get_shipping_company(),
				'country'       => $customer_data->get_shipping_country(),
				'postalCode'    => $customer_data->get_shipping_postcode(),
				'streetAddress' => $customer_data->get_shipping_address_1(),
				'contact'       => array(
					'email'     => $customer_data->get_billing_email(),
					'firstName' => $customer_data->get_billing_first_name(),
					'lastName'  => $customer_data->get_billing_last_name(),
					'phone'     => $customer_data->get_billing_phone(),
				),
			),
		);

		// Ledyer specifically requires that the company number is formatted with a dash.
		$company_id = $customer['companyId'];
		if ( ! empty( $company_id ) && false === strpos( $company_id, '-', 0 ) ) {
			$customer['companyId'] = substr_replace( $customer['companyId'], '-', -4, 0 );
		}

		return $customer;
	}
}
