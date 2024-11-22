<?php
namespace Krokedil\Ledyer\Payments\Requests\GET;

use Krokedil\Ledyer\Payments\Requests\GET;

/**
 * Create order request class.
 *
 * Authorizes a checkout payment. This happens when the customer has completed the payment while still on the checkout page.
 */
class GetSession extends GETRequest {

	/**
	 * CreateSession constructor.
	 *
	 * @param string $session_id The Ledyer session ID.
	 */
	public function __construct( $session_id ) {
		$args = get_defined_vars();

		parent::__construct( $args );
		$this->log_title = 'Get session';
		$this->endpoint  = "/v1/payment-sessions/{$session_id}";
	}
}
