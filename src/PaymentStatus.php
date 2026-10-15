<?php
/**
 * Class PaymentStatus.
 */

namespace Krokedil\Ledyer\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PaymentStatus.
 */
abstract class PaymentStatus {
	const ORDER_INITIATED   = 'orderInitiated';
	const ORDER_PENDING     = 'orderPending';
	const PAYMENT_PENDING   = 'paymentPending';
	const PAYMENT_CONFIRMED = 'paymentConfirmed';
	const ORDER_CAPTURED    = 'orderCaptured';
	const ORDER_REFUNDED    = 'orderRefunded';
	const ORDER_CANCELLED   = 'orderCancelled';
	const UNKNOWN           = 'unknown';
}
