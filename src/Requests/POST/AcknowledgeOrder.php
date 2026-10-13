<?php
namespace Krokedil\Ledyer\Payments\Requests\POST;

use Krokedil\Ledyer\Payments\Requests\POSTRequest;

/**
 * Acknowledge order request class.
 */
class AcknowledgeOrder extends POSTRequest {

	/**
	 * AcknowledgeOrder constructor.
	 *
	 * @param string $ledyer_order_id The Ledyer order ID.
	 */
	public function __construct( $ledyer_order_id ) {
		$args = get_defined_vars();

		parent::__construct( $args );
		$this->log_title = 'Acknowledge order';
		$this->endpoint  = "/v1/orders/{$ledyer_order_id}/acknowledge";
	}

	/**
	 * The args second parameter in wp_remote_request.
	 *
	 * @return array
	 */
	public function get_request_args() {
		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
		);
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	public function get_body() {
		return array();
	}
}
