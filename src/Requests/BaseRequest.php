<?php
namespace Krokedil\Ledyer\Payments\Requests;

use KrokedilLedyerPaymentsDeps\Krokedil\WpApi\Request;

/**
 * Class BaseRequest
 *
 * Base request class.
 */
abstract class BaseRequest extends Request {
	/**
	 * BaseRequest constructor.
	 *
	 * @param array $args The request args.
	 */
	public function __construct( $args = array() ) {
		$settings = get_option( 'woocommerce_ledyer_payments_settings', array() );
		$env      = $this->settings['test_mode'] ? 'sandbox' : 'live';
		$config   = array(
			'slug'               => 'ledyer_payments',
			'plugin_version'     => LEDYER_PAYMENTS_VERSION,
			'plugin_short_name'  => 'LP',
			'logging_enabled'    => wc_string_to_bool( $settings['logging'] ),
			'extended_debugging' => wc_string_to_bool( $settings['extended_logging'] ),
			'base_url'           => "https://api.{$env}.ledyer.com",
		);

		parent::__construct( $config, $settings, $args );
	}

	/**
	 * Calculate the auth header.
	 *
	 * @return string
	 */
	protected function calculate_auth() {
		return $this->get_access_token();
	}

	/**
	 * Get the access token.
	 *
	 * @return string
	 */
	protected function get_access_token() {
		$key          = 'ledyer_payments_access_token';
		$access_token = get_transient( $key );
		if ( $access_token ) {
			return $access_token;
		}

		$token = base64_encode( "{$this->settings['client_id']}:{$this->settings['client_secret']}" ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$env          = $this->settings['test_mode'] ? 'sandbox' : 'live';
		$base_url     = "https://auth.{$env}.ledyer.com/oauth/token";
		$request_args = array(
			'headers' => array(
				'Authorization' => "Basic $token",
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'grant_type' => 'client_credentials',
			),
		);

		$request = wp_remote_post( $base_url, $request_args );
		if ( is_wp_error( $request ) ) {
			return '';
		}

		$response = wp_remote_retrieve_body( $request );
		$response = json_decode( $response, true );

		$access_token = "{$response['token_type']} {$response['access_token']}";
		set_transient( $key, $access_token, absint( $response['expires_in'] ) );
		return $access_token;
	}

	/**
	 * Get the error message.
	 *
	 * @param array $response The response.
	 *
	 * @return \WP_Error
	 */
	protected function get_error_message( $response ) {
		$error_message = '';
		$errors        = json_decode( $response['body'], true );
		if ( ! empty( $errors ) ) {
			foreach ( $errors['errors'] as $i => $error ) {
				$error_message .= "[$i] " . implode( ' ', $error );
			}
		}
		$code          = wp_remote_retrieve_response_code( $response );
		$error_message = empty( $error_message ) ? $response['response']['message'] : $error_message;
		return new \WP_Error( $code, $error_message );
	}
}
