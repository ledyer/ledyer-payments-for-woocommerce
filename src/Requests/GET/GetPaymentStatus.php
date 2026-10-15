<?php
namespace Krokedil\Ledyer\Payments\Requests\GET;

use Krokedil\Ledyer\Payments\Requests\GETRequest;

/**
 * Get payment status request class.
 */
class GetPaymentStatus extends GETRequest {

	/**
	 * GetPaymentStatus constructor.
	 *
	 * @param string $ledyer_order_id The Ledyer order ID.
	 */
	public function __construct( $ledyer_order_id ) {
		$args = get_defined_vars();

		parent::__construct( $args );
		$this->log_title = 'Get payment status';
		$this->endpoint  = "/v1/orders/{$ledyer_order_id}/paymentstatus";
	}
}
