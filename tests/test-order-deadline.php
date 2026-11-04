<?php
/** Standalone regression tests for absolute ordering deadline parsing. */
define( 'ABSPATH', __DIR__ );

function __( string $message, string $domain = '' ): string {
	return $message;
}

function esc_html__( string $message, string $domain = '' ): string {
	return $message;
}

require dirname( __DIR__ ) . '/includes/class-order-deadline.php';

use Patsch9\Orderdate\OrderDeadline;

$checks = 0;
function deadline_expect( bool $condition, string $message ): void {
	global $checks;
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$berlin = new DateTimeZone( 'Europe/Berlin' );
deadline_expect( null === OrderDeadline::parse_local( '', $berlin ), 'Empty deadline is unlimited' );
deadline_expect( strtotime( '2026-01-15 11:30:00 UTC' ) === OrderDeadline::parse_local( '2026-01-15T12:30', $berlin ), 'Winter CET is converted to UTC' );
deadline_expect( strtotime( '2026-07-15 10:30:00 UTC' ) === OrderDeadline::parse_local( '2026-07-15T12:30', $berlin ), 'Summer CEST is converted to UTC' );
deadline_expect( strtotime( '2027-03-28 01:00:00 UTC' ) === OrderDeadline::parse_local( '2027-03-28T03:00', $berlin ), 'DST after spring transition is valid' );

foreach ( array(
	'2027-03-28T02:30', // Nonexistent wall time.
	'2026-02-29T12:00',
	'2026-11-31T12:00',
	'2026-11-04T24:00',
	'2026-11-04T12:60',
	'2026-11-04T12:00:00',
	'2026-11-04',
	'2026-11-04T12:00Z',
	'<script>',
	array( '2026-11-04T12:00' ),
	null,
	20261104,
) as $bad ) {
	try {
		OrderDeadline::parse_local( $bad, $berlin );
		deadline_expect( false, 'Invalid deadline was accepted: ' . json_encode( $bad ) );
	} catch ( InvalidArgumentException $exception ) {
		deadline_expect( true, 'Invalid deadline rejected' );
	}
}

echo "$checks deadline assertions passed.\n";
