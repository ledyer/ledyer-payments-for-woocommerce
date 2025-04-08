<?php
/**
 * Class API.
 *
 * API gateway.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class API.
 */
class API {

	/**
	 * Create a new Ledyer session.
	 *
	 * @return \WP_Error|array
	 */
	public function create_session() {
		$request  = new Requests\POST\CreateSession();
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Update a Ledyer session.
	 *
	 * @param string $session_id The session ID.
	 *
	 * @return \WP_Error|array
	 */
	public function update_session( $session_id ) {
		$request  = new Requests\POST\UpdateSession( $session_id );
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Create an order in Ledyer.
	 *
	 * This can be considered acknowledging an order.
	 *
	 * @param int    $order_id   The WC order ID.
	 * @param string $auth_token The Ledyer auth token.
	 *
	 * @return \WP_Error|array
	 */
	public function create_order( $order_id, $auth_token ) {
		$request  = new Requests\POST\CreateOrder( $order_id, $auth_token );
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Get a session from Ledyer.
	 *
	 * @param string $session_id The session ID.
	 *
	 * @return \WP_Error|array
	 */
	public function get_session( $session_id ) {
		$request  = new Requests\GET\GetSession( $session_id );
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}


	/**
	 * Checks for API errors, and determines whether these should be printed to the customer.
	 *
	 * @param array $response The API response.
	 * @return array The API response.
	 */
	public function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			if ( ! is_admin() ) {
				$this->print_error( $response );
			}
		}

		return $response;
	}

	/**
	 * Prints error message as notices.
	 *
	 * Sometimes an error message cannot be printed (e.g., in a cronjob environment) where there is
	 * no front end to display the error message, or otherwise irrelevant for human consumption. For that reason, we have to check if the print functions are undefined.
	 *
	 * @param \WP_Error $wp_error The error object.
	 * @return void
	 */
	private function print_error( $wp_error ) {
		if ( is_ajax() && function_exists( 'wc_add_notice' ) ) {
			$print = 'wc_add_notice';
		} elseif ( function_exists( 'wc_print_notice' ) ) {
			$print = 'wc_print_notice';
		}

		if ( isset( $print ) ) {
			foreach ( $wp_error->get_error_messages() as $error ) {
				$message = $error;
				if ( is_array( $error ) ) {
					$error = array_filter( $error );
					foreach ( $error as $message ) {
						$print( $message, 'error' );
					}
				} else {
					$print( $message, 'error' );
				}
			}
		}
	}
}
