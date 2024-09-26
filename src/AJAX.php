<?php //phpcs:ignore -- PCR-4 compliant
/**
 * Class AJAX.
 *
 * AJAX endpoints.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AJAX {
	public function __construct() {
		$ajax_events = array(
			Gateway::ID . '_wc_log_js'    => true,
			Gateway::ID . '_create_order' => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( $this, $ajax_event ) );
				add_action( 'wc_ajax_' . $ajax_event, array( $this, $ajax_event ) );
			}
		}
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function ledyer_payments_wc_log_js() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, Gateway::ID . '_wc_log_js' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		$message = sanitize_text_field( filter_input( INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$prefix  = sanitize_text_field( filter_input( INPUT_POST, 'reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		$level   = sanitize_text_field( filter_input( INPUT_POST, 'level', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) ?? 'notice';
		if ( ! empty( $message ) ) {
			$message = "{$prefix}: {$message}";
			Ledyer()->logger()->log( $message, $level );
		}

		wp_send_json_success();
	}

	public static function ledyer_payments_create_order() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, Gateway::ID . '_create_order' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		$order_id   = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$auth_token = filter_input( INPUT_POST, 'auth_token', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$result     = Ledyer()->api()->create_order( $order_id, $auth_token );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}
}
