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
$timed = Rules::normalize( array( 'time_mode' => 'custom', 'weekdays' => range( 1, 7 ), 'time_windows' => array( '*' => array( '13:00-15:00', '15:00-16:00' ), 7 => array( '12:00-13:00' ) ) ), $zone );
expect( array( '13:00-15:00', '15:00-16:00' ) === Rules::windows( '2026-11-02', array( $timed ), $now ), 'Two same-day windows before their start' );
expect( array( '15:00-16:00' ) === Rules::windows( '2026-11-02', array( $timed ), $now->setTime( 13, 0 ) ), 'Already started windows are excluded at their start' );
expect( array() === Rules::windows( '2026-11-02', array( $timed ), $now->setTime( 16, 0 ) ), 'Expired day has no windows' );
expect( array( '12:00-13:00' ) === Rules::windows( '2026-11-08', array( $timed ), $now ), 'Sunday override replaces all-days schedule' );
$other = $timed;
$other['time_windows'] = array( '*' => array( '14:00-15:30' ) );
expect( array( '14:00-15:00', '15:00-15:30' ) === Rules::windows( '2026-11-03', array( $timed, $other ), $now ), 'Intersect time intervals across checkout products' );
expect( Rules::windows( '2026-11-03', array( $timed ), $now ) === Rules::windows( '2026-11-03', array( $timed, $daily ), $now ), 'Date-only products do not constrain windows' );
$other['time_windows'] = array( '*' => array( '16:00-17:00' ) );
expect( array() === Rules::available( array( $timed, $other ), $now, 5 ), 'Touching but non-overlapping intervals do not offer a date' );
expect( array() === Rules::windows( array(), array( $timed ), $now ), 'Malformed date rejected for time availability' );
expect( array() === Rules::available( array( array_merge( $timed, array( 'time_windows' => array() ) ) ), $now, 5 ), 'Custom empty schedule closes all dates' );
expect( Rules::parse_windows( "*:13:00-14:00\n7:12:00-13:00" ) === array( '*' => array( '13:00-14:00' ), 7 => array( '12:00-13:00' ) ), 'Admin schedule parser' );
foreach ( array( '24:00-25:00', '13:00-13:00', '15:00-13:00', '23:00-01:00', '9:00-10:00', '<script>' ) as $range ) {
	rejects( array( 'time_windows' => array( '*' => array( $range ) ) ) );
}
rejects( array( 'time_windows' => array( '*' => array( '13:00-15:00', '14:00-16:00' ) ) ) );
rejects( array( 'time_windows' => array( 8 => array( '13:00-14:00' ) ) ) );
rejects( array( 'time_windows' => '13:00-14:00' ) );
rejects( array( 'time_mode' => 'arbitrary' ) );
rejects( array( 'time_windows' => array( '*' => array_fill( 0, 65, '13:00-14:00' ) ) ) );
$dst_rule = $timed;
$dst_rule['time_windows'] = array( '*' => array( '12:00-13:00' ) );
expect( array( '12:00-13:00' ) === Rules::windows( '2027-03-28', array( $dst_rule ), $dst ), 'Windows use local wall-clock time over DST change' );
$dst_rule['time_windows'] = array( '*' => array( '02:30-03:30' ) );
expect( array() === Rules::windows( '2027-03-28', array( $dst_rule ), $dst ), 'Nonexistent DST local times are excluded' );
echo "$checks rule assertions passed.\n";
