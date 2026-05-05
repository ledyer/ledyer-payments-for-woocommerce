<?php
namespace Krokedil\Ledyer\Payments\Requests\POST;

use Krokedil\Ledyer\Payments\Requests\POSTRequest;
use Krokedil\Ledyer\Payments\Requests\Helpers\Order;


/**
 *
 * Create access token request class.
 */
class AccessToken extends POSTRequest {

	/**
	 * AccessToken constructor.
	 */
	public function __construct() {
		$env  = $this->settings['test_mode'] ? 'sandbox' : 'live';
		$args = array(
			'base_url' => "https://auth.{$env}.ledyer.com/oauth/token",
		);

		parent::__construct( $args );
		$this->log_title = 'Create access token';
		$this->endpoint  = '/oauth/token';
	}

	/**
	 * Calculate the auth header.
	 *
	 * @return string
	 */
	protected function calculate_auth() {
		$token = base64_encode( "{$this->settings['client_id']}:{$this->settings['client_secret']}" ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return $token;
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	public function get_body() {
		return array(
			'grant_type' => 'client_credentials',
		);
	}
}
