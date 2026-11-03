<?php
/** Product and category allowlist for the pay-on-pickup gateway. @package Patsch9Orderdate */

namespace Patsch9\Orderdate;

defined( 'ABSPATH' ) || exit;

final class PayOnPickup {
	public const PRODUCT_META = '_p9od_pay_on_pickup';
	public const TERM_META    = 'p9od_pay_on_pickup';

	private const INHERIT = 'inherit';
	private const ALLOW   = 'yes';
	private const DENY    = 'no';

	public static function boot(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( self::class, 'product_field' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_product' ), 20 );
		add_action( 'product_cat_add_form_fields', array( self::class, 'category_add_field' ) );
		add_action( 'product_cat_edit_form_fields', array( self::class, 'category_edit_field' ) );
		add_action( 'created_product_cat', array( self::class, 'save_category' ) );
		add_action( 'edited_product_cat', array( self::class, 'save_category' ) );
	}

	public static function product_field(): void {
		global $product_object;
		if ( ! $product_object instanceof \WC_Product ) {
			return;
		}
		wp_nonce_field( 'p9od_pay_on_pickup_product', 'p9od_pay_on_pickup_nonce' );
		woocommerce_wp_select(
			array(
				'id'          => 'p9od_pay_on_pickup',
				'label'       => __( 'Vor Ort zahlen', 'patsch9-orderdate' ),
				'value'       => self::product_setting( $product_object->get_id() ),
				'options'     => array(
					self::INHERIT => __( 'Von Produktkategorien übernehmen', 'patsch9-orderdate' ),
					self::ALLOW   => __( 'Erlauben', 'patsch9-orderdate' ),
					self::DENY    => __( 'Nicht erlauben', 'patsch9-orderdate' ),
				),
				'description' => __( 'Steuert, ob „Vor Ort zahlen“ für dieses Produkt verfügbar sein darf. Eine Produktentscheidung überschreibt die Kategorieeinstellung.', 'patsch9-orderdate' ),
				'desc_tip'    => true,
			)
		);
	}

	public static function save_product( \WC_Product $product ): void {
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Sanitized and verified immediately below.
		$nonce = isset( $_POST['p9od_pay_on_pickup_nonce'] ) && is_string( $_POST['p9od_pay_on_pickup_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['p9od_pay_on_pickup_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'p9od_pay_on_pickup_product' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$value = isset( $_POST['p9od_pay_on_pickup'] ) && is_string( $_POST['p9od_pay_on_pickup'] ) ? sanitize_key( wp_unslash( $_POST['p9od_pay_on_pickup'] ) ) : self::INHERIT;
		if ( ! in_array( $value, array( self::INHERIT, self::ALLOW, self::DENY ), true ) ) {
			$value = self::INHERIT;
		}
		if ( self::INHERIT === $value ) {
			$product->delete_meta_data( self::PRODUCT_META );
			return;
		}
		$product->update_meta_data( self::PRODUCT_META, $value );
	}

	public static function category_add_field(): void {
		wp_nonce_field( 'p9od_pay_on_pickup_category', 'p9od_pay_on_pickup_category_nonce' );
		echo '<div class="form-field term-p9od-pay-on-pickup-wrap"><label for="p9od_pay_on_pickup_category"><input type="checkbox" id="p9od_pay_on_pickup_category" name="p9od_pay_on_pickup_category" value="yes"> ' . esc_html__( 'Vor Ort zahlen erlauben', 'patsch9-orderdate' ) . '</label><p>' . esc_html__( 'Gilt für Produkte dieser Kategorie und ihrer Unterkategorien, sofern das Produkt die Einstellung nicht überschreibt.', 'patsch9-orderdate' ) . '</p></div>';
	}

	public static function category_edit_field( \WP_Term $term ): void {
		wp_nonce_field( 'p9od_pay_on_pickup_category', 'p9od_pay_on_pickup_category_nonce' );
		$checked = self::ALLOW === get_term_meta( $term->term_id, self::TERM_META, true );
		echo '<tr class="form-field term-p9od-pay-on-pickup-wrap"><th scope="row"><label for="p9od_pay_on_pickup_category">' . esc_html__( 'Vor Ort zahlen', 'patsch9-orderdate' ) . '</label></th><td><label><input type="checkbox" id="p9od_pay_on_pickup_category" name="p9od_pay_on_pickup_category" value="yes" ' . checked( $checked, true, false ) . '> ' . esc_html__( 'Für diese Kategorie erlauben', 'patsch9-orderdate' ) . '</label><p class="description">' . esc_html__( 'Gilt auch für Unterkategorien. Eine explizite Produkteinstellung hat Vorrang.', 'patsch9-orderdate' ) . '</p></td></tr>';
	}

	public static function save_category( int $term_id ): void {
		$taxonomy = get_taxonomy( 'product_cat' );
		if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Sanitized and verified immediately below.
		$nonce = isset( $_POST['p9od_pay_on_pickup_category_nonce'] ) && is_string( $_POST['p9od_pay_on_pickup_category_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['p9od_pay_on_pickup_category_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'p9od_pay_on_pickup_category' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above; checkbox is reduced to an allowlisted value.
		$value = isset( $_POST['p9od_pay_on_pickup_category'] ) && is_string( $_POST['p9od_pay_on_pickup_category'] ) ? sanitize_key( wp_unslash( $_POST['p9od_pay_on_pickup_category'] ) ) : '';
		if ( self::ALLOW === $value ) {
			update_term_meta( $term_id, self::TERM_META, self::ALLOW );
		} else {
			delete_term_meta( $term_id, self::TERM_META );
		}
	}

	public static function product_allowed( int $product_id ): bool {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
			if ( ! $product ) {
				return false;
			}
		}
		$setting = self::product_setting( $product->get_id() );
		if ( self::ALLOW === $setting ) {
			return true;
		}
		if ( self::DENY === $setting ) {
			return false;
		}

		$terms = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return false;
		}
		$category_ids = array_map( 'absint', $terms );
		foreach ( $terms as $term_id ) {
			$category_ids = array_merge( $category_ids, get_ancestors( (int) $term_id, 'product_cat', 'taxonomy' ) );
		}
		foreach ( array_unique( array_map( 'absint', $category_ids ) ) as $term_id ) {
			if ( self::ALLOW === get_term_meta( $term_id, self::TERM_META, true ) ) {
				return true;
			}
		}
		return false;
	}

	public static function cart_allowed(): bool {
		if ( ! WC()->cart ) {
			return false;
		}
		$items = WC()->cart->get_cart();
		if ( ! $items ) {
			return false;
		}
		$checked = array();
		foreach ( $items as $item ) {
			$product_id = absint( $item['variation_id'] ?: $item['product_id'] );
			if ( ! array_key_exists( $product_id, $checked ) ) {
				$checked[ $product_id ] = self::product_allowed( $product_id );
			}
			if ( ! $checked[ $product_id ] ) {
				return false;
			}
		}
		return true;
	}

	public static function order_allowed( \WC_Order $order ): bool {
		$items = $order->get_items( 'line_item' );
		if ( ! $items ) {
			return false;
		}
		$checked = array();
		foreach ( $items as $item ) {
			$product_id = absint( $item->get_variation_id() ?: $item->get_product_id() );
			if ( ! array_key_exists( $product_id, $checked ) ) {
				$checked[ $product_id ] = self::product_allowed( $product_id );
			}
			if ( ! $checked[ $product_id ] ) {
				return false;
			}
		}
		return true;
	}

	private static function product_setting( int $product_id ): string {
		$value = sanitize_key( (string) get_post_meta( $product_id, self::PRODUCT_META, true ) );
		return in_array( $value, array( self::ALLOW, self::DENY ), true ) ? $value : self::INHERIT;
	}
}
