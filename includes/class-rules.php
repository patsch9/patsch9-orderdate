<?php
/** Date rules. No WooCommerce or request state is accessed here. @package Patsch9Orderdate */

namespace Patsch9\Orderdate;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

final class Rules {
	/** Parse a real ISO calendar date without PHP's date overflow normalization. */
	public static function date( mixed $value, DateTimeZone $zone ): ?DateTimeImmutable {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
			return null;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $zone );
		return $date && $date->format( 'Y-m-d' ) === $value ? $date : null;
	}

	/** Defaults are explicit: an empty weekday set permits no recurring dates. */
	public static function defaults(): array {
		return array(
			'mode' => 'off', 'schedule' => 'weekly', 'from' => '', 'until' => '',
			'weekdays' => array( 1, 2, 3, 4, 5, 6, 7 ), 'dates' => array(),
			'blocked' => array(), 'lead_days' => 0, 'cutoff' => '',
		);
	}

	/** Validate every configuration value; preserve the old configuration on error. */
	public static function normalize( array $input, DateTimeZone $zone ): array {
		$result = self::defaults();
		foreach ( array( 'mode' => array( 'off', 'product', 'checkout' ), 'schedule' => array( 'weekly', 'dates', 'combined' ) ) as $key => $allowed ) {
			$value = $input[ $key ] ?? $result[ $key ];
			if ( ! in_array( $value, $allowed, true ) ) {
				throw new \InvalidArgumentException( 'Ungültiger Auswahlmodus.' );
			}
			$result[ $key ] = $value;
		}
		foreach ( array( 'from', 'until' ) as $key ) {
			$value = $input[ $key ] ?? '';
			if ( '' !== $value && ! self::date( $value, $zone ) ) {
				throw new \InvalidArgumentException( 'Ungültiges Datum (JJJJ-MM-TT erforderlich).' );
			}
			$result[ $key ] = $value;
		}
		if ( $result['from'] && $result['until'] && $result['from'] > $result['until'] ) {
			throw new \InvalidArgumentException( 'Verfügbar bis liegt vor Verfügbar ab.' );
		}
		$days = $input['weekdays'] ?? array();
		if ( ! is_array( $days ) || array_filter( $days, static fn( $day ) => ! in_array( $day, array( 1, 2, 3, 4, 5, 6, 7, '1', '2', '3', '4', '5', '6', '7' ), true ) ) ) {
			throw new \InvalidArgumentException( 'Ungültige Wochentage.' );
		}
		$result['weekdays'] = array_values( array_unique( array_map( 'intval', $days ) ) );
		foreach ( array( 'dates', 'blocked' ) as $key ) {
			$dates = $input[ $key ] ?? array();
			if ( ! is_array( $dates ) || count( $dates ) > 730 ) {
				throw new \InvalidArgumentException( 'Höchstens 730 Einzeltermine je Liste erlaubt.' );
			}
			foreach ( $dates as $date ) {
				if ( ! self::date( $date, $zone ) ) {
					throw new \InvalidArgumentException( 'Ungültiger Einzeltermin.' );
				}
			}
			$result[ $key ] = array_values( array_unique( $dates ) );
			sort( $result[ $key ] );
		}
		$lead = $input['lead_days'] ?? 0;
		if ( ! is_scalar( $lead ) || ! preg_match( '/^\d{1,3}$/D', (string) $lead ) || (int) $lead > 730 ) {
			throw new \InvalidArgumentException( 'Vorlauf muss zwischen 0 und 730 Kalendertagen liegen.' );
		}
		$result['lead_days'] = (int) $lead;
		$cutoff = $input['cutoff'] ?? '';
		if ( ! is_string( $cutoff ) || ( '' !== $cutoff && ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $cutoff ) ) ) {
			throw new \InvalidArgumentException( 'Ungültiger Bestellschluss (HH:MM erforderlich).' );
		}
		$result['cutoff'] = $cutoff;
		return $result;
	}

	/** Availability is checked again at checkout against the current shop time. */
	public static function allows( mixed $value, array $rule, DateTimeImmutable $now, int $horizon, array $global_blocked = array() ): bool {
		$date = self::date( $value, $now->getTimezone() );
		if ( ! $date ) {
			return false;
		}
		$today = $now->setTime( 0, 0 );
		$lead = (int) $rule['lead_days'];
		// Bestellschluss shifts the earliest pickup date by one calendar day.
		if ( $rule['cutoff'] && $now->format( 'H:i' ) >= $rule['cutoff'] ) {
			++$lead;
		}
		if ( $date < $today->modify( '+' . $lead . ' days' ) || $date > $today->modify( '+' . $horizon . ' days' ) ) {
			return false;
		}
		if ( ( $rule['from'] && $value < $rule['from'] ) || ( $rule['until'] && $value > $rule['until'] ) || in_array( $value, $rule['blocked'], true ) || in_array( $value, $global_blocked, true ) ) {
			return false;
		}
		$weekly = in_array( (int) $date->format( 'N' ), $rule['weekdays'], true );
		$specific = in_array( $value, $rule['dates'], true );
		return match ( $rule['schedule'] ) {
			'weekly' => $weekly,
			'dates' => $specific,
			'combined' => $weekly || $specific,
			default => false,
		};
	}

	/** Return the intersection for an order; a bounded scan avoids unbounded work. */
	public static function available( array $rules, DateTimeImmutable $now, int $horizon, array $blocked = array() ): array {
		if ( ! $rules ) {
			return array();
		}
		$dates = array();
		$day = $now->setTime( 0, 0 );
		for ( $i = 0; $i <= $horizon; ++$i ) {
			$value = $day->format( 'Y-m-d' );
			$allowed = true;
			foreach ( $rules as $rule ) {
				if ( ! self::allows( $value, $rule, $now, $horizon, $blocked ) ) {
					$allowed = false;
					break;
				}
			}
			if ( $allowed ) {
				$dates[] = $value;
			}
			$day = $day->modify( '+1 day' );
		}
		return $dates;
	}
}
