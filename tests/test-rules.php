<?php
/** Standalone regression suite; no WordPress/database dependency. */
define( 'ABSPATH', __DIR__ );
require dirname( __DIR__ ) . '/includes/class-rules.php';
use Patsch9\Orderdate\Rules;

$checks = 0;
function expect( bool $condition, string $message ): void {
	global $checks;
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function rejects( array $values ): void {
	try {
		Rules::normalize( $values, new DateTimeZone( 'Europe/Berlin' ) );
		expect( false, 'Invalid configuration accepted: ' . json_encode( $values ) );
	} catch ( InvalidArgumentException $error ) {
		expect( true, 'Invalid input rejected' );
	}
}
$zone = new DateTimeZone( 'Europe/Berlin' );
$now = new DateTimeImmutable( '2026-11-02 11:59:00', $zone ); // Monday.
$rule = Rules::normalize( array( 'mode' => 'product', 'weekdays' => array( '3', '5', '6' ), 'lead_days' => '2' ), $zone );
expect( Rules::allows( '2026-11-04', $rule, $now, 30 ), 'Wednesday after 2 calendar days' );
expect( ! Rules::allows( '2026-11-03', $rule, $now, 30 ), 'Tuesday disallowed' );
expect( ! Rules::allows( '2026-10-31', $rule, $now, 30 ), 'Past date disallowed' );
foreach ( array( '2026-02-29', '2026-11-31', '2026-13-01', '2026-1-01', '2026-11-02T00:00', '<script>', array(), null, 20261104 ) as $value ) {
	expect( ! Rules::allows( $value, $rule, $now, 30 ), 'Malformed dates fail closed' );
}
expect( null !== Rules::date( '2028-02-29', $zone ), 'Leap day accepted' );
$specific = Rules::normalize( array( 'mode' => 'checkout', 'schedule' => 'dates', 'dates' => array( '2026-11-03', '2026-11-06' ), 'from' => '2026-11-04' ), $zone );
expect( ! Rules::allows( '2026-11-03', $specific, $now, 30 ), 'Available-from applies to explicit dates' );
expect( Rules::allows( '2026-11-06', $specific, $now, 30 ), 'Explicit date allowed without weekdays' );
expect( array( '2026-11-06' ) === Rules::available( array( $rule, $specific ), $now, 30 ), 'Checkout intersects all product rules' );
expect( array() === Rules::available( array( $rule, $specific ), $now, 30, array( '2026-11-06' ) ), 'Global blackout wins' );
$combined = $rule;
$combined['schedule'] = 'combined';
$combined['dates'] = array( '2026-11-08' );
expect( Rules::allows( '2026-11-08', $combined, $now, 30 ), 'Extra Sunday opens in combined mode' );
$combined['blocked'] = array( '2026-11-08' );
expect( ! Rules::allows( '2026-11-08', $combined, $now, 30 ), 'Explicit blacklist wins over explicit availability' );
$rule['until'] = '2026-11-06';
expect( ! Rules::allows( '2026-11-07', $rule, $now, 30 ), 'Until boundary' );
$daily = Rules::normalize( array( 'weekdays' => range( 1, 7 ), 'cutoff' => '12:00' ), $zone );
expect( Rules::allows( '2026-11-02', $daily, $now, 30 ), 'Same day before cutoff' );
expect( ! Rules::allows( '2026-11-02', $daily, $now->setTime( 12, 0 ), 30 ), 'Cutoff boundary is inclusive' );
$daily['lead_days'] = 2;
expect( ! Rules::allows( '2026-11-04', $daily, $now->setTime( 12, 0 ), 30 ), 'Cutoff shifts positive lead too' );
expect( Rules::allows( '2026-11-05', $daily, $now->setTime( 12, 0 ), 30 ), 'Next calendar day after cutoff' );
$daily['lead_days'] = 0;
$daily['cutoff'] = '';
expect( Rules::allows( '2026-12-02', $daily, $now, 30 ), 'Horizon boundary inclusive' );
expect( ! Rules::allows( '2026-12-03', $daily, $now, 30 ), 'Horizon upper bound' );
expect( count( Rules::available( array( $daily ), $now, 30 ) ) === 31, 'Today plus 30 calendar days' );
$dst = new DateTimeImmutable( '2027-03-27 23:30', $zone );
$daily['lead_days'] = 1;
expect( Rules::allows( '2027-03-28', $daily, $dst, 3 ), 'DST uses calendar days, not 24-hour seconds' );
expect( count( Rules::available( array( $daily ), $dst, 3 ) ) === 3, 'DST scan does not skip dates' );
expect( array() === Rules::available( array(), $now, 30 ), 'No active rules, no dates' );
expect( array() === Rules::available( array( Rules::normalize( array(), $zone ) ), $now, 30 ), 'Empty weekdays mean no recurring dates' );
foreach ( array(
	array( 'from' => '2026-02-30' ), array( 'from' => array() ), array( 'from' => '2027-01-01', 'until' => '2026-01-01' ),
	array( 'mode' => 'admin' ), array( 'mode' => array() ), array( 'schedule' => 'invalid' ), array( 'weekdays' => '1' ),
	array( 'weekdays' => array( 0 ) ), array( 'weekdays' => array( '1x' ) ), array( 'dates' => array( '2026-11-31' ) ),
	array( 'dates' => array_fill( 0, 731, '2026-11-02' ) ), array( 'blocked' => 'today' ), array( 'lead_days' => -1 ),
	array( 'lead_days' => '2foo' ), array( 'lead_days' => 731 ), array( 'cutoff' => '24:00' ), array( 'cutoff' => array() ),
) as $values ) {
	rejects( $values );
}
echo "$checks rule assertions passed.\n";
