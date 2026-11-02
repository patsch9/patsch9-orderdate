<?php
/** Checkout Block and Store API integration. @package Patsch9Orderdate */

namespace Patsch9\Orderdate;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

defined( 'ABSPATH' ) || exit;

final class Blocks {
	public const NS = 'patsch9-orderdate';

	public static function boot(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_filter( 'render_block_data', array( self::class, 'insert_block' ) );
		add_filter( 'woocommerce_store_api_add_to_cart_data', array( self::class, 'add_to_cart' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( self::class, 'checkout' ), 20, 2 );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::store_api();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( self::class, 'store_api' ) );
		}
	}

	public static function register(): void {
		wp_register_script( 'p9od-block', plugins_url( 'assets/checkout-block.js', P9OD_FILE ), array( 'wc-blocks-checkout', 'wp-element', 'wp-data', 'wc-blocks-data-store', 'wp-i18n', 'jquery-ui-datepicker' ), P9OD_VERSION, true );
		wp_register_script( 'p9od-block-editor', plugins_url( 'assets/block-editor.js', P9OD_FILE ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ), P9OD_VERSION, true );
		wp_set_script_translations( 'p9od-block', 'patsch9-orderdate' );
		wp_set_script_translations( 'p9od-block-editor', 'patsch9-orderdate' );
		register_block_type( dirname( P9OD_FILE ) . '/blocks/pickup-date', array( 'render_callback' => static function () {
			return '<div class="wp-block-patsch9-orderdate-pickup-date" data-block-name="patsch9-orderdate/pickup-date"></div>';
		} ) );
	}

	/** Add one block at render time, leaving the saved checkout page untouched. */
	public static function insert_block( array $block ): array {
		if ( 'woocommerce/checkout-fields-block' !== ( $block['blockName'] ?? '' ) ) {
			return $block;
		}
		foreach ( $block['innerBlocks'] as $child ) {
			if ( self::NS . '/pickup-date' === $child['blockName'] ) {
				return $block;
			}
		}
		$block['innerBlocks'][] = array( 'blockName' => self::NS . '/pickup-date', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
		$block['innerContent'][] = null;
		return $block;
	}

	public static function store_api(): void {
		require_once __DIR__ . '/class-blocks-integration.php';
		add_action( 'woocommerce_blocks_checkout_block_registration', static function ( $registry ) {
			$registry->register( new BlocksIntegration() );
		} );
		woocommerce_store_api_register_endpoint_data( array(
			'endpoint' => CartSchema::IDENTIFIER, 'namespace' => self::NS,
			'data_callback' => static function () {
				$rules = Plugin::checkout_rules();
				$dates = Plugin::dates( $rules );
				return array(
					'required' => (bool) $rules,
					'dates' => array_map( static fn( $date ) => array( 'value' => $date, 'label' => Plugin::display( $date ) ), $dates ),
				);
			},
			'schema_callback' => static fn() => array(
				'required' => array( 'type' => 'boolean', 'readonly' => true ),
				'dates' => array( 'type' => 'array', 'readonly' => true, 'items' => array( 'type' => 'object', 'properties' => array( 'value' => array( 'type' => 'string' ), 'label' => array( 'type' => 'string' ) ) ) ),
			), 'schema_type' => ARRAY_A,
		) );
		woocommerce_store_api_register_endpoint_data( array(
			'endpoint' => CheckoutSchema::IDENTIFIER, 'namespace' => self::NS,
			'schema_callback' => static fn() => array( 'date' => array( 'type' => 'string', 'maxLength' => 10, 'context' => array( 'view', 'edit' ) ) ),
			'schema_type' => ARRAY_A,
		) );
	}

	/** The Store API authenticates cart mutations (nonce or Cart-Token). */
	public static function add_to_cart( array $data, \WP_REST_Request $request ): array {
		$rule = Plugin::rule( (int) $data['id'] );
		if ( 'product' === $rule['mode'] ) {
			$extensions = $request->get_param( 'extensions' );
			$date = is_array( $extensions ) ? ( $extensions[ self::NS ]['date'] ?? '' ) : '';
			if ( ! Plugin::allowed( $date, $rule ) ) {
				throw new RouteException( 'p9od_invalid_date', esc_html__( 'Bitte auf der Produktseite ein gültiges Abholdatum auswählen.', 'patsch9-orderdate' ), 400 );
			}
			$data['cart_item_data'][ Plugin::DATE ] = $date;
		}
		if ( 'checkout' === $rule['mode'] && ! Plugin::dates( array( $rule ) ) ) {
			throw new RouteException( 'p9od_no_date', esc_html__( 'Für diesen Artikel ist derzeit kein Abholdatum verfügbar.', 'patsch9-orderdate' ), 400 );
		}
		return $data;
	}

	public static function checkout( \WC_Order $order, \WP_REST_Request $request ): void {
		$extensions = $request->get_param( 'extensions' );
		$date = is_array( $extensions ) ? ( $extensions[ self::NS ]['date'] ?? '' ) : '';
		$errors = Plugin::errors( $date, true );
		if ( $errors ) {
			throw new RouteException( 'p9od_invalid_date', esc_html( implode( ' ', $errors ) ), 400 );
		}
		// Draft orders are reused. Clear obsolete dates after cart/mode changes.
		Plugin::apply_order_date( $order, is_string( $date ) ? $date : '' );
		$order->save();
	}
}
