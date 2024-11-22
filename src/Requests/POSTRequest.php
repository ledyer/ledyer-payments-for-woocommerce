<?php
namespace Krokedil\Ledyer\Payments\Requests;

/**
 * POST request class.
 */
abstract class POSTRequest extends BaseRequest {

	/**
	 * POST constructor.
	 *
	 * @param array $args Arguments that should be accessible from within the request.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( $args );
		$this->method = 'POST';
	}

	/**
	 * The args second parameter in wp_remote_request.
	 *
	 * @return array
	 */
	public function get_request_args() {
		$body = wp_json_encode( apply_filters( "{$this->config['slug']}_request_args", $this->get_body() ) );

		return array(
			'headers'    => $this->get_request_headers(),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'body'       => $body,
		);
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	abstract protected function get_body();
}
