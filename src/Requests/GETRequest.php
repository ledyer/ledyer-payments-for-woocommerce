<?php
namespace Krokedil\Ledyer\Payments\Requests;

/**
 * GET request class.
 */
abstract class GETRequest extends BaseRequest {

	/**
	 * The request method.
	 *
	 * @var string
	 */
	public $method = 'GET';

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
}
