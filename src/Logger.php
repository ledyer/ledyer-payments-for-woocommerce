<?php
/**
 * Class Logger.
 *
 * Log to WC.
 */

namespace Krokedil\Ledyer\Payments;

use Krokedil\Ledyer\Payments\Requests\Helpers\Cart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	private $logger;

	private $context;

	public function __construct() {
		$this->logger  = new \WC_Logger();
		$this->context = array(
			'source'    => Gateway::ID,
			'timestamp' => current_time( 'mysql' ),
			'reference' => ( new Cart() )->get_reference(),
		);
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
	 * @param array  $additional_context Additional context to log.
	 */
	public function log( $message, $level = 'debug', $additional_context = array() ) {
		if ( ! empty( $additional_context ) ) {
			$this->context['custom_data'] = $additional_context;
		}

		if ( is_callable( array( $this->logger, $level ) ) ) {
			$this->logger->{$level}( $message, $this->context );
		} else {
			$this->logger->debug( $message, $this->context );
		}
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Error message.
	 * @param array  $additional_context Additional context to log.
	 */
	public function error( $message, $additional_context = array() ) {
		$this->log( $message, 'error', $additional_context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message Warning message.
	 * @param array  $additional_context Additional context to log.
	 */
	public function warning( $message, $additional_context = array() ) {
		$this->log( $message, 'warning', $additional_context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Debug message.
	 * @param array  $additional_context Additional context to log.
	 */
	public function debug( $message, $additional_context = array() ) {
		$this->log( $message, 'debug', $additional_context );
	}
}
