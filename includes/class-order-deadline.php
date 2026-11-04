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

	public static function boot(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'simple_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_simple' ) );
		add_action( 'woocommerce_variation_options_pricing', array( self::class, 'variation_field' ), 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', array( self::class, 'save_variation' ), 10, 2 );
		add_filter( 'woocommerce_is_purchasable', array( self::class, 'purchasable' ), 10, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', array( self::class, 'purchasable' ), 10, 2 );
		add_filter( 'woocommerce_variation_is_active', array( self::class, 'variation_active' ), 10, 2 );
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
			throw new \InvalidArgumentException( __( 'Bitte ein gültiges Datum mit Uhrzeit angeben.', 'patsch9-orderdate' ) );
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, $zone );
		if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $value ) {
			throw new \InvalidArgumentException( __( 'Das angegebene Datum oder die Ortszeit existiert nicht.', 'patsch9-orderdate' ) );
		}
		return $date->getTimestamp();
	}

	/** Display timestamps in the shop's configured local timezone. */
	private static function field_value( ?WC_Product $product ): string {
		if ( ! $product ) {
			return '';
		}
		$timestamp = $product->get_meta( self::META, true );
		if ( ! is_scalar( $timestamp ) || ! ctype_digit( (string) $timestamp ) || (int) $timestamp <= 0 ) {
			return '';
		}
		return wp_date( 'Y-m-d\TH:i', (int) $timestamp, wp_timezone() );
	}

	private static function description(): string {
		return __( 'Optional. Bestellungen sind nur bis zu diesem Zeitpunkt (Shop-Zeitzone) möglich, unabhängig von Versandart oder Abholtermin. Ab der angegebenen Minute ist der Artikel nicht mehr bestellbar.', 'patsch9-orderdate' );
	}

	public static function simple_field(): void {
		global $product_object;
		woocommerce_wp_text_input( array(
			'id' => 'p9od_order_deadline',
			'label' => __( 'Bestellbar bis', 'patsch9-orderdate' ),
			'type' => 'datetime-local',
			'wrapper_class' => 'show_if_simple',
			'value' => self::field_value( $product_object instanceof WC_Product ? $product_object : null ),
			'description' => self::description(),
			'desc_tip' => true,
		) );
		wp_nonce_field( 'p9od_save_order_deadline', 'p9od_order_deadline_nonce' );
	}

	public static function variation_field( int $loop, array $variation_data, \WP_Post $variation ): void {
		$product = wc_get_product( $variation->ID );
		woocommerce_wp_text_input( array(
			'id' => 'p9od_variation_order_deadline_' . $loop,
			'name' => 'p9od_variation_order_deadline[' . $loop . ']',
			'label' => __( 'Bestellbar bis', 'patsch9-orderdate' ),
			'type' => 'datetime-local',
			'wrapper_class' => 'form-row form-row-full',
			'value' => self::field_value( $product instanceof WC_Product ? $product : null ),
			'description' => self::description(),
		) );
		wp_nonce_field( 'p9od_save_variation_deadline_' . $variation->ID, 'p9od_variation_deadline_nonce[' . $loop . ']' );
	}

	/**
	 * Only update a single product after checking its dedicated editor nonce.
	 * The WooCommerce product editor also checks its own save nonce.
	 */
	public static function save_simple( WC_Product $product ): void {
		if ( ! $product->is_type( 'simple' ) || ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.
		$nonce = $_POST['p9od_order_deadline_nonce'] ?? '';
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'p9od_save_order_deadline' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		self::save_value( $product, $_POST['p9od_order_deadline'] ?? '' );
	}

	/** WooCommerce additionally verifies its AJAX variation-save nonce. */
	public static function save_variation( WC_Product_Variation $variation, int $loop ): void {
		if ( ! current_user_can( 'edit_post', $variation->get_parent_id() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified immediately below.
		$nonces = $_POST['p9od_variation_deadline_nonce'] ?? array();
		if ( ! is_array( $nonces ) || ! isset( $nonces[ $loop ] ) || ! is_string( $nonces[ $loop ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonces[ $loop ] ) ), 'p9od_save_variation_deadline_' . $variation->get_id() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$values = $_POST['p9od_variation_order_deadline'] ?? array();
		if ( ! is_array( $values ) || ! array_key_exists( $loop, $values ) ) {
			return;
		}
		self::save_value( $variation, $values[ $loop ] );
	}

	private static function save_value( WC_Product $product, mixed $raw ): void {
		try {
			if ( ! is_string( $raw ) ) {
				throw new \InvalidArgumentException( __( 'Ungültiger Bestellschluss.', 'patsch9-orderdate' ) );
			}
			$timestamp = self::parse_local( sanitize_text_field( wp_unslash( $raw ) ), wp_timezone() );
			if ( null === $timestamp ) {
				$product->delete_meta_data( self::META );
			} else {
				$product->update_meta_data( self::META, (string) $timestamp );
			}
		} catch ( \InvalidArgumentException $exception ) {
			\WC_Admin_Meta_Boxes::add_error( $exception->getMessage() );
		}
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
		/* translators: %s: Product or variation name. */
		return sprintf( __( 'Der Bestellschluss für „%s“ ist erreicht. Bitte den Artikel aus dem Warenkorb entfernen.', 'patsch9-orderdate' ), wp_strip_all_tags( $product->get_name() ) );
	}

	public static function purchasable( bool $purchasable, WC_Product $product ): bool {
		return $purchasable && ! self::expired( $product );
	}

	public static function variation_active( bool $active, WC_Product_Variation $variation ): bool {
		return $active && ! self::expired( $variation );
	}

	/** Validate the exact selected variation, not merely the variable parent. */
	public static function validate_add( bool $passed, int $product_id, int $quantity, int $variation_id = 0 ): bool {
		$product = wc_get_product( $variation_id ?: $product_id );
		if ( $product && self::expired( $product ) ) {
			wc_add_notice( esc_html( self::message( $product ) ), 'error' );
			return false;
		}
		return $passed;
	}

	public static function validate_store_add( WC_Product $product ): void {
		if ( self::expired( $product ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'p9od_order_deadline',
				esc_html( self::message( $product ) ),
				400
			);
		}
	}

	/** The Store API uses WP_Error, not WC notices, for quantity updates. */
	public static function validate_quantity( mixed $valid, int|float $quantity, WC_Product $product, array $cart_item ): mixed {
		if ( self::expired( $product ) ) {
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
			if ( $product && self::expired( $product ) ) {
				$errors->add( 'p9od_order_deadline', self::message( $product ) );
			}
		}
	}
}
