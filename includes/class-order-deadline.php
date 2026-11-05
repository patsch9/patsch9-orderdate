<?php
/**
 * Absolute ordering deadlines for simple products and individual variations.
 *
 * @package Patsch9Orderdate
 */

namespace Patsch9\Orderdate;

use DateTimeImmutable;
use DateTimeZone;
use WC_Product;
use WC_Product_Variation;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class OrderDeadline {
	/** UTC Unix timestamp; deliberately separate from pickup-date rules. */
	public const META = '_p9od_order_deadline';
	/** Optional sale start timestamp in UTC, independent from pickup availability. */
	public const META_FROM = '_p9od_order_available_from';

	public static function boot(): void {
		add_action( 'woocommerce_product_options_pricing', array( self::class, 'simple_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_simple' ) );
		add_action( 'woocommerce_variation_options_pricing', array( self::class, 'variation_field' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( self::class, 'save_variation' ), 10, 2 );
		add_filter( 'woocommerce_is_purchasable', array( self::class, 'purchasable' ), 10, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( self::class, 'purchasable' ), 10, 2 );
		add_filter( 'woocommerce_available_variation', array( self::class, 'variation_data' ), 10, 3 );
		add_action( 'woocommerce_single_product_summary', array( self::class, 'simple_notice' ), 25 );
		add_filter( 'woocommerce_add_to_cart_validation', array( self::class, 'validate_add' ), 5, 4 );
		add_action( 'woocommerce_store_api_validate_add_to_cart', array( self::class, 'validate_store_add' ), 10, 1 );
		add_filter( 'woocommerce_store_api_cart_item_quantity_validation', array( self::class, 'validate_quantity' ), 10, 4 );
		add_action( 'woocommerce_store_api_cart_errors', array( self::class, 'store_cart_errors' ) );
	}

	/**
	 * Parse a local HTML datetime-local value strictly. Empty means no limit.
	 * Rejects normalized/invalid values, including nonexistent DST wall times.
	 *
	 * @throws \InvalidArgumentException For malformed dates.
	 */
	public static function parse_local( mixed $value, DateTimeZone $zone ): ?int {
		if ( '' === $value ) {
			return null;
		}
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d$/D', $value ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Bitte ein gültiges Datum mit Uhrzeit angeben.', 'patsch9-orderdate' ) );
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, $zone );
		if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $value ) {
			throw new \InvalidArgumentException( esc_html__( 'Das angegebene Datum oder die Ortszeit existiert nicht.', 'patsch9-orderdate' ) );
		}
		return $date->getTimestamp();
	}

	/** Display timestamps in the shop's configured local timezone. */
	private static function field_value( ?WC_Product $product, string $key ): string {
		if ( ! $product ) {
			return '';
		}
		$timestamp = $product->get_meta( $key, true );
		if ( ! is_scalar( $timestamp ) || ! ctype_digit( (string) $timestamp ) || (int) $timestamp <= 0 ) {
			return '';
		}
		return wp_date( 'Y-m-d\TH:i', (int) $timestamp, wp_timezone() );
	}

	private static function description( bool $is_start ): string {
		return $is_start
			? __( 'Optional. Ab diesem Zeitpunkt (Shop-Zeitzone) darf der Artikel unabhängig von der Versandart bestellt werden.', 'patsch9-orderdate' )
			: __( 'Optional. Bis zu diesem Zeitpunkt (Shop-Zeitzone) kann der Artikel unabhängig von der Versandart bestellt werden.', 'patsch9-orderdate' );
	}

	public static function simple_field(): void {
		global $product_object;
		$product = $product_object instanceof WC_Product ? $product_object : null;
		foreach ( array(
			self::META_FROM => array( 'p9od_order_available_from', __( 'Bestellbar ab', 'patsch9-orderdate' ), true ),
			self::META      => array( 'p9od_order_deadline', __( 'Bestellbar bis', 'patsch9-orderdate' ), false ),
		) as $meta => $field ) {
			woocommerce_wp_text_input( array(
				'id' => $field[0],
				'label' => $field[1],
				'type' => 'datetime-local',
				'wrapper_class' => 'show_if_simple p9od-order-availability-field',
				'value' => self::field_value( $product, $meta ),
				'description' => self::description( $field[2] ),
				'desc_tip' => false,
			) );
		}
		wp_nonce_field( 'p9od_save_order_deadline', 'p9od_order_deadline_nonce' );
	}

	public static function variation_field( int $loop, array $variation_data, \WP_Post $variation ): void {
		$product = wc_get_product( $variation->ID );
		foreach ( array(
			self::META_FROM => array( 'p9od_variation_order_available_from', __( 'Bestellbar ab', 'patsch9-orderdate' ), true ),
			self::META      => array( 'p9od_variation_order_deadline', __( 'Bestellbar bis', 'patsch9-orderdate' ), false ),
		) as $meta => $field ) {
			woocommerce_wp_text_input( array(
				'id' => $field[0] . '_' . $loop,
				'name' => $field[0] . '[' . $loop . ']',
				'label' => $field[1],
				'type' => 'datetime-local',
				'wrapper_class' => 'form-row form-row-full p9od-variation-availability-field',
				'value' => self::field_value( $product instanceof WC_Product ? $product : null, $meta ),
				'description' => self::description( $field[2] ),
			) );
		}
		wp_nonce_field( 'p9od_save_variation_deadline_' . $variation->ID, 'p9od_variation_deadline_nonce[' . $loop . ']' );
	}

	/**
	 * Normalize editor form values before use. Callers verify the relevant nonce
	 * and edit capability before changing any product metadata.
	 */
	private static function posted( string $name ): mixed {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve input shape and immediately unslash/sanitize below; verified by callers.
		$raw = $_POST[ $name ] ?? null;
		if ( is_string( $raw ) ) {
			return sanitize_text_field( wp_unslash( $raw ) );
		}
		if ( is_array( $raw ) ) {
			return map_deep( wp_unslash( $raw ), 'sanitize_text_field' );
		}
		return null;
	}

	/**
	 * WooCommerce validates the product save nonce before this action.
	 * The dedicated nonce and edit permission protect our own fields.
	 */
	public static function save_simple( WC_Product $product ): void {
		if ( ! $product->is_type( 'simple' ) || ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}
		$nonce = self::posted( 'p9od_order_deadline_nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'p9od_save_order_deadline' ) ) {
			return;
		}
		self::save_values( $product, self::posted( 'p9od_order_available_from' ), self::posted( 'p9od_order_deadline' ) );
	}

	/** WooCommerce additionally verifies the variation editor's AJAX save nonce. */
	public static function save_variation( WC_Product_Variation $variation, int $loop ): void {
		if ( ! current_user_can( 'edit_post', $variation->get_parent_id() ) ) {
			return;
		}
		$nonces = self::posted( 'p9od_variation_deadline_nonce' );
		if ( ! is_array( $nonces ) || ! isset( $nonces[ $loop ] ) || ! is_string( $nonces[ $loop ] ) ||
			! wp_verify_nonce( $nonces[ $loop ], 'p9od_save_variation_deadline_' . $variation->get_id() ) ) {
			return;
		}
		$starts = self::posted( 'p9od_variation_order_available_from' );
		$ends = self::posted( 'p9od_variation_order_deadline' );
		$start = is_array( $starts ) && array_key_exists( $loop, $starts ) ? $starts[ $loop ] : null;
		$end = is_array( $ends ) && array_key_exists( $loop, $ends ) ? $ends[ $loop ] : null;
		self::save_values( $variation, $start, $end );
	}

	/** Existing stored values are preserved if a field is not submitted. */
	private static function stored_timestamp( WC_Product $product, string $key ): ?int {
		$raw = $product->get_meta( $key, true );
		return is_scalar( $raw ) && ctype_digit( (string) $raw ) && (int) $raw > 0 ? (int) $raw : null;
	}

	/** Validate both dates before mutating either meta field. */
	private static function save_values( WC_Product $product, mixed $start_input, mixed $end_input ): void {
		if ( null === $start_input && null === $end_input ) {
			return;
		}
		try {
			if ( ( null !== $start_input && ! is_string( $start_input ) ) || ( null !== $end_input && ! is_string( $end_input ) ) ) {
				throw new \InvalidArgumentException( esc_html__( 'Ungültiger Bestellzeitraum.', 'patsch9-orderdate' ) );
			}
			$start = null === $start_input ? self::stored_timestamp( $product, self::META_FROM ) : self::parse_local( $start_input, wp_timezone() );
			$end = null === $end_input ? self::stored_timestamp( $product, self::META ) : self::parse_local( $end_input, wp_timezone() );
			if ( null !== $start && null !== $end && $start >= $end ) {
				throw new \InvalidArgumentException( esc_html__( '„Bestellbar ab“ muss vor „Bestellbar bis“ liegen.', 'patsch9-orderdate' ) );
			}
			if ( null !== $start_input ) {
				null === $start ? $product->delete_meta_data( self::META_FROM ) : $product->update_meta_data( self::META_FROM, (string) $start );
			}
			if ( null !== $end_input ) {
				null === $end ? $product->delete_meta_data( self::META ) : $product->update_meta_data( self::META, (string) $end );
			}
		} catch ( \InvalidArgumentException $exception ) {
			\WC_Admin_Meta_Boxes::add_error( $exception->getMessage() );
		}
	}

	/** Invalid stored timestamps must not silently reopen ordering. */
	public static function not_started( ?WC_Product $product ): bool {
		if ( ! $product || ! $product->is_type( array( 'simple', 'variation' ) ) ) {
			return false;
		}
		$raw = $product->get_meta( self::META_FROM, true );
		if ( '' === $raw || null === $raw ) {
			return false;
		}
		return ! is_scalar( $raw ) || ! ctype_digit( (string) $raw ) || (int) $raw <= 0 || time() < (int) $raw;
	}

	public static function blocked( ?WC_Product $product ): bool {
		return self::expired( $product ) || self::not_started( $product );
	}

	/** Malformed stored limits fail closed instead of silently reopening sales. */
	public static function expired( ?WC_Product $product ): bool {
		if ( ! $product || ! $product->is_type( array( 'simple', 'variation' ) ) ) {
			return false;
		}
		$raw = $product->get_meta( self::META, true );
		if ( '' === $raw || null === $raw ) {
			return false;
		}
		return ! is_scalar( $raw ) || ! ctype_digit( (string) $raw ) || (int) $raw <= 0 || time() >= (int) $raw;
	}

	public static function message( WC_Product $product ): string {
		if ( self::expired( $product ) ) {
			/* translators: %s: Product or variation name. */
			return sprintf( __( 'Der Bestellschluss für „%s“ ist erreicht. Bitte den Artikel aus dem Warenkorb entfernen.', 'patsch9-orderdate' ), wp_strip_all_tags( $product->get_name() ) );
		}
		$start = self::stored_timestamp( $product, self::META_FROM );
		if ( null !== $start && self::not_started( $product ) ) {
			return sprintf(
				/* translators: 1: Product or variation name; 2: local ordering start date and time. */
				__( '„%1$s“ ist erst ab %2$s Uhr bestellbar.', 'patsch9-orderdate' ),
				wp_strip_all_tags( $product->get_name() ),
				wp_date( get_option( 'date_format' ) . ' H:i', $start, wp_timezone() )
			);
		}
		/* translators: %s: Product or variation name. */
		return sprintf( __( '„%s“ kann derzeit nicht bestellt werden.', 'patsch9-orderdate' ), wp_strip_all_tags( $product->get_name() ) );
	}

	public static function purchasable( bool $purchasable, WC_Product $product ): bool {
		return $purchasable && ! self::blocked( $product );
	}

	/**
	 * A product deadline is a sales restriction, not a reason to hide its
	 * variation. Keep expired variations selectable so their explanation is
	 * visible, while is_purchasable and the server-side guards still block sales.
	 */
	public static function notice_text( WC_Product $product ): string {
		if ( ! $product->is_type( array( 'simple', 'variation' ) ) ) {
			return '';
		}
		if ( self::expired( $product ) ) {
			return __( 'Bestellschluss erreicht: Dieser Artikel kann nicht mehr bestellt werden.', 'patsch9-orderdate' );
		}
		if ( self::not_started( $product ) ) {
			$start = self::stored_timestamp( $product, self::META_FROM );
			if ( null === $start ) {
				return __( 'Dieser Artikel ist derzeit nicht bestellbar.', 'patsch9-orderdate' );
			}
			return sprintf(
				/* translators: %s: Ordering start date and time in the shop timezone. */
				__( 'Bestellbar ab: %s Uhr.', 'patsch9-orderdate' ),
				wp_date( get_option( 'date_format' ) . ' H:i', $start, wp_timezone() )
			);
		}
		$end = self::stored_timestamp( $product, self::META );
		if ( null === $end ) {
			return '';
		}
		return sprintf(
			/* translators: %s: Ordering deadline date and time in the shop timezone. */
			__( 'Bestellbar bis: %s Uhr.', 'patsch9-orderdate' ),
			wp_date( get_option( 'date_format' ) . ' H:i', $end, wp_timezone() )
		);
	}

	public static function notice_html( WC_Product $product ): string {
		$text = self::notice_text( $product );
		if ( '' === $text ) {
			return '';
		}
		$class = 'p9od-order-deadline';
		if ( self::expired( $product ) ) {
			$class .= ' p9od-order-deadline--expired';
		} elseif ( self::not_started( $product ) ) {
			$class .= ' p9od-order-deadline--pending';
		}
		return '<p class="' . esc_attr( $class ) . '" role="status">' . esc_html( $text ) . '</p>';
	}

	/** Product summary is rendered even when add-to-cart is no longer shown. */
	public static function simple_notice(): void {
		global $product;
		if ( $product instanceof WC_Product && $product->is_type( 'simple' ) ) {
			echo self::notice_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- notice_html escapes every dynamic value.
		}
	}

	/** WooCommerce renders availability_html after a variation is selected. */
	public static function variation_data( array $data, \WC_Product_Variable $parent, WC_Product_Variation $variation ): array {
		$notice = self::notice_html( $variation );
		if ( '' !== $notice ) {
			$data['availability_html'] = (string) ( $data['availability_html'] ?? '' ) . $notice;
		}
		return $data;
	}

	/** Validate the exact selected variation, not merely the variable parent. */
	public static function validate_add( bool $passed, int $product_id, int $quantity, int $variation_id = 0 ): bool {
		$product = wc_get_product( $variation_id ?: $product_id );
		if ( $product && self::blocked( $product ) ) {
			wc_add_notice( esc_html( self::message( $product ) ), 'error' );
			return false;
		}
		return $passed;
	}

	public static function validate_store_add( WC_Product $product ): void {
		if ( self::blocked( $product ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'p9od_order_deadline',
				esc_html( self::message( $product ) ),
				400
			);
		}
	}

	/** The Store API uses WP_Error, not WC notices, for quantity updates. */
	public static function validate_quantity( mixed $valid, int|float $quantity, WC_Product $product, array $cart_item ): mixed {
		if ( self::blocked( $product ) ) {
			return new WP_Error( 'p9od_order_deadline', self::message( $product ) );
		}
		return $valid;
	}

	/** Store API cart and checkout validation, including items saved in a session. */
	public static function store_cart_errors( WP_Error $errors ): void {
		if ( ! WC()->cart ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			$product = wc_get_product( (int) ( ( $item['variation_id'] ?? 0 ) ?: ( $item['product_id'] ?? 0 ) ) );
			if ( $product && self::blocked( $product ) ) {
				$errors->add( 'p9od_order_deadline', self::message( $product ) );
			}
		}
	}
}
