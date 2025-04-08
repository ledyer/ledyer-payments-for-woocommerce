<?php
/**
 * Class Session.
 *
 * Session management.
 */

namespace Krokedil\Ledyer\Payments;

use Krokedil\Ledyer\Payments\Requests\Helpers\Cart;
use Krokedil\Ledyer\Payments\Requests\Helpers\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Session.
 */
class Session {

	public const SESSION_KEY = '_ledyer_payments_session_data';

	/**
	 * The gateway session.
	 *
	 * @var array|null
	 */
	private $gateway_session = null;

	/**
	 * The session hash.
	 *
	 * @var string|null
	 */
	private $session_hash = null;

	/**
	 * The session country.
	 *
	 * @var string|null
	 */
	private $session_country = null;

	/**
	 * The session reference.
	 *
	 * @var string|null
	 */
	private $session_reference = null;

	/**
	 * Payment categories.
	 *
	 * When the  `gateway_session` is updated, the update request doesn't include the configuration data. Therefore, we have to store it separately.
	 *
	 * @var array
	 */
	private $payment_categories = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'get_session' ), 999999 );
	}

	/**
	 * Create or update an existing session if necessary.
	 *
	 * @param \WC_Order|numeric|null $order The order object or order ID. Pass `null` to retrieve session from WC_Session (default).
	 * @return array|\WP_Error|null The result from the API request, a WP_Error if an error occurred, or `null` if the gateway is either not available or if we're on a non-checkout page.
	 */
	public function get_session( $order = false ) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		if ( ! isset( $gateways['ledyer_payments'] ) ) {
			return;
		}

		if ( ( ! is_checkout() && ! is_checkout_pay_page() ) || is_order_received_page() ) {
			return;
		}

		// Resume existing session.
		$this->resume();

		// Check if we got an order.
		$order  = $this->get_order( $order );
		$helper = empty( $order ) ? new Cart() : new Order( $order );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// Update the session data if we have an existing one.
		$this->update_session( $order );

		// A country change warrants a new session. Clear the session so when we check if we have one, we get false, resulting in a new session.
		if ( $helper->get_country() !== $this->session_country ) {
			$this->reset();
		}

		if ( ! empty( $this->gateway_session ) && ! $this->needs_update( $order ) ) {
			return $this->gateway_session;
		}

		// The session has been set or modified, we must update the existing session or create one if it doesn't already exist.
		if ( $this->gateway_session ) {
			$result = Ledyer_Payments()->api()->update_session( $this->get_id() );
			return $this->process_result( $result, $order );
		}

		$result = Ledyer_Payments()->api()->create_session();
		return $this->process_result( $result, $order );
	}


	/**
	 * Get the session country.
	 *
	 * @param \WC_Order|null $order The WooCommerce order or pass `null` or to default retrieving the country from the session instead.
	 * @return string The country code.
	 */
	public function get_country( $order = null ) {
		if ( empty( $this->session_country ) ) {
			$helper = empty( $order ) ? new Cart() : new Order( $order );
			return $helper->get_country();
		}

		return $this->session_country;
	}

	/**
	 * Get the session ID.
	 *
	 * If the session doesn't exist, we'll try to create it. If that fails, `null` is returned.
	 *
	 * @return string|null The session ID.
	 */
	public function get_id() {
		return $this->gateway_session['sessionId'] ?? $this->gateway_session['id'] ?? null;
	}

	/**
	 * Get the payment categories.
	 *
	 * @return array
	 */
	public function get_payment_categories() {
		return $this->payment_categories;
	}

	/**
	 * Get the session reference.
	 *
	 * @return string
	 */
	public function get_reference() {
		if ( empty( $this->session_reference ) ) {
			$this->session_reference = wp_generate_uuid4();
			$this->wc_update_session();
		}

		return $this->session_reference;
	}

	/**
	 * Clears the session data stored to WC, and optionally, to the order too if WC_Order is passed.
	 *
	 * @param \WC_Order|null $order A WooCommerce order or null if only session should be cleared.
	 * @return void
	 */
	public function clear_session( $order = null ) {
		if ( isset( WC()->session ) ) {
			WC()->session->__unset( self::SESSION_KEY );
		}

		if ( ! empty( $order ) ) {
			$order->delete_meta_data( self::SESSION_KEY );
			$order->save();
		}
	}

	/**
	 * Resumes the session from the WooCommerce session, if available.
	 *
	 * @return bool Whether there was a session to resume.
	 */
	public function resume() {
		if ( ! empty( $this->gateway_session ) ) {
			return true;
		}

		$session_data = isset( WC()->session ) ? WC()->session->get( self::SESSION_KEY ) : null;
		$session      = ! empty( $session_data ) ? json_decode( $session_data, true ) : null;
		if ( ! empty( $session ) ) {
			$this->gateway_session    = $session['gateway_session'];
			$this->session_hash       = $session['session_hash'];
			$this->session_country    = $session['session_country'];
			$this->payment_categories = $session['payment_categories'];
			$this->session_reference  = $session['session_reference'];
		}

		return ! empty( $session );
	}

	/**
	 * Clear the internal session data.
	 *
	 * This should only be called when we need to update the session.
	 *
	 * @return void
	 */
	private function reset() {
		$this->gateway_session   = null;
		$this->session_hash      = null;
		$this->session_country   = null;
		$this->session_reference = null;

		// No need to clear the $payment_categories as it is overwritten when a new session is created.
	}

	/**
	 * Checks if the session needs to be updated.
	 *
	 * @param \WC_Order|numeric|null $order The order object or order ID. Pass `null` to retrieve session from WC_Session (default).
	 * @return bool True if the session needs to be updated, false otherwise.
	 */
	private function needs_update( $order = null ) {
		$session_hash       = $this->get_hash( $order );
		$needs_update       = $session_hash !== $this->session_hash;
		$this->session_hash = $session_hash;

		return $needs_update;
	}

	/**
	 * Retrieves the WooCommerce order object based on the provided order parameter.
	 *
	 * @param mixed $order The order object or order ID.
	 * @return \WC_Order|\WP_Error|false The WooCommerce order object if found, WP_Error if order not found, or false if the provided order is not valid.
	 */
	private function get_order( $order ) {
		if ( $order instanceof \WC_Order ) {
			return $order;
		}

		if ( ! is_numeric( $order ) ) {
			return false;
		}

		$order = wc_get_order( $order );
		return empty( $order ) ? new \WP_Error( 'ledyer_payments_order_not_found', __( 'Order not found', 'ledyer-payments-for-woocommerce' ) ) : $order;
	}

	/**
	 * Calculates a hash from the order data.
	 *
	 * @param \WC_Order|numeric|null $order The order object or order ID. Pass `null` to retrieve session from WC_Session (default).
	 * @return string The hash.
	 */
	private function get_hash( $order ) {
		if ( empty( $order ) ) {
			// The `get_totals` method can return non-numeric items which should be removed before using `array_sum`.
			$cart_totals = array_filter(
				WC()->cart->get_totals(),
				function ( $total ) {
					return is_numeric( $total );
				}
			);

			// Get values to use for the combined hash calculation.
			$total            = array_sum( $cart_totals );
			$billing_address  = WC()->customer->get_billing();
			$shipping_address = WC()->customer->get_shipping();
			$shipping_method  = WC()->session->get( 'chosen_shipping_methods' );

			// Calculate a hash from the values.
			$hash = md5( wp_json_encode( array( $total, $billing_address, $shipping_address, $shipping_method ) ) );
		} else {
			// Get values to use for the combined hash calculation.
			$total            = $order->get_total( 'edit' );
			$billing_address  = $order->get_address( 'billing' );
			$shipping_address = $order->get_address( 'shipping' );

			// Calculate a hash from the values.
			$hash = md5( wp_json_encode( array( $total, $billing_address, $shipping_address ) ) );
		}

		return $hash;
	}

	/**
	 * Processes the result from the API request.
	 *
	 * @param mixed                  $result The result from the API request.
	 * @param \WC_Order|numeric|null $order The order object or order ID. Pass `null` to retrieve session from WC_Session (default).
	 * @return \WP_Error|array The result from the API request or a WP_Error if an error occurred.
	 */
	private function process_result( $result, $order = null ) {
		if ( is_wp_error( $result ) ) {
			$this->process_error();
			return $result;
		}

		$helper = empty( $order ) ? new Cart() : new Order( $order );

		$this->gateway_session = ! empty( $result ) ? $result : $this->gateway_session;
		$this->session_hash    = $this->get_hash( $order );
		$this->session_country = $helper->get_country();

		// Generate a minimum of 23-characters unique reference.
		if ( empty( $this->session_reference ) ) {
			$this->session_reference = wp_generate_uuid4();
		}

		if ( isset( $this->gateway_session['configuration'] ) ) {
			$this->payment_categories = $this->gateway_session['configuration'];
		}

		// Persist the session to Woo.
		$this->wc_update_session( $order );

		return $result;
	}

	/**
	 * Processes an error from the API request.
	 *
	 * @return void
	 */
	private function process_error() {
		$session = Ledyer_Payments()->api()->get_session( $this->get_id() );
		if ( is_wp_error( $session ) ) {
			$this->clear_session();
			return;
		}

		if ( is_checkout() && ! is_order_received_page() ) {
			if ( 'authorized' === $session['state'] ) {
				$order = Ledyer_Payments()->gateway()->get_order_by_session_id( $this->get_id() );
				$key   = $order->get_order_key();
				if ( empty( $order ) ) {
					$key      = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
					$order_id = wc_get_order_id_by_order_key( $key );
					$order    = wc_get_order( $order_id );
				}

				$redirect_to = add_query_arg(
					array(
						'gateway' => 'ledyer_payments',
						'key'     => $key,
					),
					$order->get_checkout_order_received_url()
				);

				$did_redirect = wp_safe_redirect( $redirect_to );
				if ( $did_redirect ) {
					function_exists( 'wc_clear_notices' ) && wc_clear_notices();
				}
				exit;
			}

			if ( 'expired' === $session['state'] ) {
				$this->clear_session();

				if ( isset( WC()->session ) ) {
					WC()->session->reload_checkout = true;
				}
			}
		}
	}

	/**
	 * Updates the session data in WooCommerce.
	 *
	 * @param \WC_Order|numeric|null $order The order object or order ID. Pass `null` to retrieve session from WC_Session (default).
	 * @return void
	 */
	private function wc_update_session( $order = null ) {
		$session_data = wp_json_encode(
			array(
				'gateway_session'    => $this->gateway_session,
				'session_hash'       => $this->session_hash,
				'session_country'    => $this->session_country,
				'payment_categories' => $this->payment_categories,
				'session_reference'  => $this->session_reference,
			)
		);

		if ( empty( $order ) ) {
			WC()->session->set( self::SESSION_KEY, $session_data );
		} else {
			$order->update_meta_data( self::SESSION_KEY, $session_data );
			$order->save();
		}
	}

	/**
	 * Updates the session data from the WooCommerce session or order meta.
	 *
	 * @param \WC_Order|numeric|null $order The order object or order ID. Pass `null` to retrieve session from WC_Session.
	 * @return \WP_Error|bool True if session data was set, false if no session data was found, or WP_Error if an error occurred.
	 */
	private function update_session( $order = null ) {
		$order = $this->get_order( $order );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$session_data = empty( $order ) ? WC()->session->get( self::SESSION_KEY ) : $order->get_meta( self::SESSION_KEY );
		if ( empty( $session_data ) ) {
			return false;
		}

		$decoded_data = json_decode( $session_data, true );
		if ( empty( $decoded_data ) || ! is_array( $decoded_data ) ) {
			return false;
		}

		$this->gateway_session    = $decoded_data['gateway_session'];
		$this->session_hash       = $decoded_data['session_hash'];
		$this->session_country    = $decoded_data['session_country'];
		$this->payment_categories = $decoded_data['payment_categories'];
		$this->session_reference  = $decoded_data['session_reference'];
		return true;
	}
}
