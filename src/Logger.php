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

	private $logger;

	public function __construct() {
		$this->logger = new \WC_Logger();
	}

	/**
	 * Add a log entry.
	 *
	 * @param string $message Log message.
	 * @param string $level One of the following:
	 *    - `emergency`: System is unusable.
	 *    - `alert`: Action must be taken immediately.
	 *    - `critical`: Critical conditions.
	 *    - `error`: Error conditions.
	 *    - `warning`: Warning conditions.
	 *    - `notice`: Normal but significant condition.
	 *    - `info`: Informational messages.
	 *    - `debug`: Debug-level messages.
	 */
	public function log( $message, $level = 'debug' ) {
		$context = array( 'source' => Gateway::ID );

		if ( is_callable( array( $this->logger, $level ) ) ) {
			$this->logger->{$level}( $message, $context );
		} else {
			$this->logger->debug( $message, $context );
		}
	}
}
