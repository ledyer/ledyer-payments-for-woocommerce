<?php
/**
 * Class Logger.
 *
 * Log to WC.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {
	public function log( $log_data ) {
		\Krokedil\WpApi\Logger::log( Gateway::ID, $log_data );
	}
}
