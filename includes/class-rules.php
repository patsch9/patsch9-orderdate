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
			'time_mode' => 'inherit', 'time_windows' => array(),
		);
	}

	/** Validate every configuration value; preserve the old configuration on error. */
	public static function normalize( array $input, DateTimeZone $zone ): array {
		$result = self::defaults();
		foreach ( array( 'mode' => array( 'off', 'product', 'checkout' ), 'schedule' => array( 'weekly', 'dates', 'combined' ), 'time_mode' => array( 'inherit', 'custom', 'off' ) ) as $key => $allowed ) {
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
		$result['time_windows'] = self::normalize_windows( $input['time_windows'] ?? array() );
		return $result;
	}

	/** Parse editable schedules; the admin's native time inputs generate these rows. */
	public static function parse_windows( string $text ): array {
		$windows = array();
		foreach ( preg_split( '/\R/', trim( $text ) ) as $row ) {
			if ( '' === trim( $row ) ) {
				continue;
			}
			if ( ! preg_match( '/^\s*([1-7*])\s*:\s*(\d{2}:\d{2}-\d{2}:\d{2})\s*$/D', $row, $match ) ) {
				throw new \InvalidArgumentException( 'Zeitfenster im Format *:13:00-14:00 oder 1:13:00-14:00 (1=Montag, 7=Sonntag) eingeben.' );
			}
			$windows[ $match[1] ][] = $match[2];
		}
		return self::normalize_windows( $windows );
	}

	/** Reject malformed, overnight and overlapping windows instead of silently changing them. */
	public static function normalize_windows( mixed $windows ): array {
		if ( ! is_array( $windows ) || count( $windows ) > 8 ) {
			throw new \InvalidArgumentException( 'Ungültige Zeitfenster.' );
		}
		$result = array();
		$count = 0;
		foreach ( $windows as $day => $ranges ) {
			if ( ! in_array( (string) $day, array( '*', '1', '2', '3', '4', '5', '6', '7' ), true ) || ! is_array( $ranges ) ) {
				throw new \InvalidArgumentException( 'Ungültiger Wochentag für Zeitfenster.' );
			}
			$count += count( $ranges );
			if ( $count > 64 ) {
				throw new \InvalidArgumentException( 'Höchstens 64 Zeitfenster je Zeitplan erlaubt.' );
			}
			foreach ( $ranges as $range ) {
				if ( ! is_string( $range ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d-(?:[01]\d|2[0-3]):[0-5]\d$/D', $range ) || substr( $range, 0, 5 ) >= substr( $range, 6 ) ) {
					throw new \InvalidArgumentException( 'Zeitfenster benötigen einen Beginn vor dem Ende am selben Tag (HH:MM-HH:MM).' );
				}
			}
			$ranges = array_values( array_unique( $ranges ) );
			sort( $ranges );
			$last_end = '';
			foreach ( $ranges as $range ) {
				if ( substr( $range, 0, 5 ) < $last_end ) {
					throw new \InvalidArgumentException( 'Zeitfenster desselben Wochentags dürfen sich nicht überschneiden.' );
				}
				$last_end = substr( $range, 6 );
			}
			$result[ $day ] = $ranges;
		}
		return $result;
	}

	public static function requires_time( array $rules ): bool {
		foreach ( $rules as $rule ) {
			if ( 'custom' === ( $rule['time_mode'] ?? 'off' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Intersect intervals rather than requiring identical window labels on products. */
	public static function windows( mixed $date, array $rules, DateTimeImmutable $now ): array {
		$day = self::date( $date, $now->getTimezone() );
		if ( ! $day || $date < $now->format( 'Y-m-d' ) ) {
			return array();
		}
		$common = null;
		foreach ( $rules as $rule ) {
			if ( 'custom' !== ( $rule['time_mode'] ?? 'off' ) ) {
				continue;
			}
			$weekday = $day->format( 'N' );
			$windows = $rule['time_windows'];
			$ranges = $windows[ $weekday ] ?? $windows['*'] ?? array();
			if ( null === $common ) {
				$common = $ranges;
				continue;
			}
			$intersection = array();
			foreach ( $common as $left ) {
				foreach ( $ranges as $right ) {
					$start = max( substr( $left, 0, 5 ), substr( $right, 0, 5 ) );
					$end = min( substr( $left, 6 ), substr( $right, 6 ) );
					if ( $start < $end ) {
						$intersection[] = $start . '-' . $end;
					}
				}
			}
			$common = array_values( array_unique( $intersection ) );
		}
		$common = $common ?? array();
		// Reject local times that do not exist during the spring DST transition.
		$common = array_values( array_filter( $common, static function ( $range ) use ( $date, $now ) {
			foreach ( array( substr( $range, 0, 5 ), substr( $range, 6 ) ) as $time ) {
				$local = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, $now->getTimezone() );
				if ( ! $local || $local->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
					return false;
				}
			}
			return true;
		} ) );
		if ( $date === $now->format( 'Y-m-d' ) ) {
			$common = array_values( array_filter( $common, static fn( $range ) => substr( $range, 0, 5 ) > $now->format( 'H:i' ) ) );
		}
		sort( $common );
		return $common;
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
			if ( $allowed && ( ! self::requires_time( $rules ) || self::windows( $value, $rules, $now ) ) ) {
				$dates[] = $value;
			}
			$day = $day->modify( '+1 day' );
		}
		return $dates;
	}
}
