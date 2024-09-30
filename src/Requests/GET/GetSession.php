<?php
namespace Krokedil\Ledyer\Payments\Requests\GET;

use Krokedil\Ledyer\Payments\Requests\GET;
use Krokedil\Ledyer\Payments\Requests\Helpers\Cart;

/**
 * Create order request class.
 *
 * Authorizes a checkout payment. This happens when the customer has completed the payment while still on the checkout page.
 */
class GetSession extends GET {

	/**
	 * CreateSession constructor.
	 *
	 * @return array Arguments that should be accessible from within the request.
	 */
	public function __construct( $session_id ) {
		$args = get_defined_vars();

		parent::__construct( $args );
		$this->log_title = 'Get session';
		$this->endpoint  = "/v1/payment-sessions/{$session_id}";
	}
}
