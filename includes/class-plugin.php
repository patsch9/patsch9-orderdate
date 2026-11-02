<?php
/** WooCommerce integration. @package Patsch9Orderdate */

namespace Patsch9\Orderdate;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public const META = '_p9od_rules';
	public const DATE = '_p9od_pickup_date';

	public static function boot(): void {
		add_action( 'woocommerce_before_add_to_cart_button', array( self::class, 'product_field' ) );
		add_filter( 'woocommerce_loop_add_to_cart_link', array( self::class, 'loop_link' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( self::class, 'validate_add' ), 10, 6 );
		add_filter( 'woocommerce_add_cart_item_data', array( self::class, 'cart_data' ), 10, 4 );
		add_filter( 'woocommerce_get_item_data', array( self::class, 'item_data' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( self::class, 'check_cart' ) );
		add_action( 'woocommerce_after_order_notes', array( self::class, 'checkout_field' ) );
		add_action( 'woocommerce_after_checkout_validation', array( self::class, 'validate_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'create_order' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( self::class, 'create_line' ), 10, 4 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( self::class, 'admin_date' ) );
		add_filter( 'woocommerce_email_order_meta_fields', array( self::class, 'email_date' ), 10, 3 );
		add_action( 'woocommerce_order_details_after_order_table', array( self::class, 'order_date' ) );
		add_filter( 'woocommerce_order_item_display_meta_key', array( self::class, 'meta_label' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_value', array( self::class, 'meta_value' ), 10, 2 );
		// Show the protected item date in WooCommerce's standard order/email tables.
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( self::class, 'formatted_meta' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function settings(): array {
		$value = get_option( 'p9od_settings', array() );
		return array_merge( array( 'horizon' => 365, 'blocked' => array() ), is_array( $value ) ? $value : array() );
	}

	public static function rule( int $id ): array {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return Rules::defaults();
		}
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		if ( ! $product || ! $product->is_type( array( 'simple', 'variable' ) ) ) {
			return Rules::defaults();
		}
		$stored = $product->get_meta( self::META, true );
		return is_array( $stored ) ? array_merge( Rules::defaults(), $stored ) : Rules::defaults();
	}

	public static function dates( array $rules ): array {
		$settings = self::settings();
		return Rules::available( $rules, current_datetime(), (int) $settings['horizon'], $settings['blocked'] );
	}

	public static function allowed( mixed $date, array $rule ): bool {
		$settings = self::settings();
		return Rules::allows( $date, $rule, current_datetime(), (int) $settings['horizon'], $settings['blocked'] );
	}

	public static function cart(): array {
		return WC()->cart ? WC()->cart->get_cart() : array();
	}

	/** Unique rules only: quantities do not change date availability. */
	public static function checkout_rules(): array {
		$rules = array();
		foreach ( self::cart() as $item ) {
			$id = (int) ( $item['variation_id'] ?: $item['product_id'] );
			$rule = self::rule( $id );
			if ( 'checkout' === $rule['mode'] ) {
				$rules[ $id ] = $rule;
			}
		}
		return array_values( $rules );
	}

	public static function label(): string {
		return __( 'Abholdatum', 'patsch9-orderdate' );
	}

	public static function display( string $date ): string {
		$parsed = Rules::date( $date, wp_timezone() );
		return $parsed ? wp_date( get_option( 'date_format' ), $parsed->getTimestamp(), wp_timezone() ) : '';
	}

	/** Select fallback works without JavaScript; the calendar enhances it. */
	public static function select( string $name, array $dates, string $value = '' ): void {
		echo '<p class="form-row form-row-wide p9od-field"><label for="' . esc_attr( $name ) . '">' . esc_html( self::label() ) . ' <span aria-hidden="true">*</span></label>';
		echo '<select required class="p9od-date" name="' . esc_attr( $name ) . '" id="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Bitte Abholdatum wählen', 'patsch9-orderdate' ) . '</option>';
		foreach ( $dates as $date ) {
			echo '<option value="' . esc_attr( $date ) . '" ' . selected( $value, $date, false ) . '>' . esc_html( wp_date( 'l, ' . get_option( 'date_format' ), Rules::date( $date, wp_timezone() )->getTimestamp(), wp_timezone() ) ) . '</option>';
		}
		echo '</select></p>';
	}

	public static function product_field(): void {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$rule = self::rule( $product->get_id() );
		if ( 'product' !== $rule['mode'] ) {
			return;
		}
		$dates = self::dates( array( $rule ) );
		wp_nonce_field( 'p9od_add_date', 'p9od_nonce' );
		self::select( 'p9od_date', $dates );
		if ( ! $dates ) {
			echo '<p role="status">' . esc_html__( 'Für diesen Artikel sind derzeit keine Abholtermine verfügbar.', 'patsch9-orderdate' ) . '</p>';
		}
	}

	public static function loop_link( string $html, \WC_Product $product ): string {
		if ( 'product' !== self::rule( $product->get_id() )['mode'] ) {
			return $html;
		}
		return '<a class="button" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'Abholdatum wählen', 'patsch9-orderdate' ) . '</a>';
	}

	/** Only a nonce-protected product form supplies classic add-to-cart dates. */
	private static function posted_date(): string {
		if ( ! isset( $_POST['p9od_nonce'] ) || ! is_string( $_POST['p9od_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['p9od_nonce'] ) ), 'p9od_add_date' ) ) {
			return '';
		}
		return isset( $_POST['p9od_date'] ) && is_string( $_POST['p9od_date'] ) ? sanitize_text_field( wp_unslash( $_POST['p9od_date'] ) ) : '';
	}

	public static function validate_add( bool $passed, int $product_id, int $quantity, int $variation_id = 0, array $variations = array(), array $data = array() ): bool {
		$rule = self::rule( $variation_id ?: $product_id );
		if ( 'product' === $rule['mode'] && ! self::allowed( $data[ self::DATE ] ?? self::posted_date(), $rule ) ) {
			wc_add_notice( __( 'Bitte auf der Produktseite ein gültiges Abholdatum auswählen.', 'patsch9-orderdate' ), 'error' );
			return false;
		}
		if ( 'checkout' === $rule['mode'] && ! self::dates( array( $rule ) ) ) {
			wc_add_notice( __( 'Für diesen Artikel ist derzeit kein Abholdatum verfügbar.', 'patsch9-orderdate' ), 'error' );
			return false;
		}
		return $passed;
	}

	public static function cart_data( array $data, int $product_id, int $variation_id = 0, int $quantity = 1 ): array {
		if ( 'product' === self::rule( $variation_id ?: $product_id )['mode'] && ! isset( $data[ self::DATE ] ) ) {
			$data[ self::DATE ] = self::posted_date();
		}
		return $data;
	}

	public static function item_data( array $data, array $item ): array {
		if ( ! empty( $item[ self::DATE ] ) ) {
			$data[] = array( 'key' => self::label(), 'value' => self::display( (string) $item[ self::DATE ] ) );
		}
		return $data;
	}

	/** Return current cart errors without trusting session-saved rule snapshots. */
	public static function errors( mixed $checkout_date = '', bool $final = false ): array {
		$errors = array();
		foreach ( self::cart() as $item ) {
			$rule = self::rule( (int) ( $item['variation_id'] ?: $item['product_id'] ) );
			if ( 'product' === $rule['mode'] && ! self::allowed( $item[ self::DATE ] ?? '', $rule ) ) {
				/* translators: %s: Product name. */
				$errors[] = sprintf( __( 'Das Abholdatum für „%s“ ist nicht mehr gültig. Bitte den Artikel entfernen und mit einem neuen Termin hinzufügen.', 'patsch9-orderdate' ), wp_strip_all_tags( $item['data']->get_name() ) );
			}
		}
		$rules = self::checkout_rules();
		if ( $rules ) {
			if ( ! self::dates( $rules ) ) {
				$errors[] = __( 'Die Artikel mit Terminauswahl an der Kasse haben keinen gemeinsamen Abholtermin. Bitte getrennt bestellen oder die Artikelauswahl ändern.', 'patsch9-orderdate' );
			} elseif ( $final ) {
				foreach ( $rules as $rule ) {
					if ( ! self::allowed( $checkout_date, $rule ) ) {
						$errors[] = __( 'Bitte ein für alle betroffenen Artikel gültiges Abholdatum auswählen.', 'patsch9-orderdate' );
						break;
					}
				}
			}
		}
		return array_unique( $errors );
	}

	public static function check_cart(): void {
		foreach ( self::errors() as $message ) {
			if ( ! wc_has_notice( $message, 'error' ) ) {
				wc_add_notice( $message, 'error' );
			}
		}
	}

	public static function checkout_field( \WC_Checkout $checkout ): void {
		$rules = self::checkout_rules();
		if ( $rules ) {
			self::select( 'p9od_checkout_date', self::dates( $rules ), (string) $checkout->get_value( 'p9od_checkout_date' ) );
			echo '<p>' . esc_html__( 'Dieser Termin gilt für alle Artikel mit Terminauswahl an der Kasse. Termine bereits datierter Artikel bleiben separat bestehen.', 'patsch9-orderdate' ) . '</p>';
		}
	}

	/** WooCommerce verifies the checkout nonce before invoking this hook. */
	public static function classic_date(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checkout lifecycle validates its own nonce.
		return isset( $_POST['p9od_checkout_date'] ) && is_string( $_POST['p9od_checkout_date'] ) ? sanitize_text_field( wp_unslash( $_POST['p9od_checkout_date'] ) ) : '';
	}

	public static function validate_checkout( array $data, \WP_Error $errors ): void {
		foreach ( self::errors( self::classic_date(), true ) as $i => $message ) {
			$errors->add( 'p9od_' . $i, $message );
		}
	}

	public static function create_order( \WC_Order $order, array $data ): void {
		$date = self::classic_date();
		$errors = self::errors( $date, true );
		if ( $errors ) {
			throw new \Exception( esc_html( implode( ' ', $errors ) ) );
		}
		if ( self::checkout_rules() ) {
			self::apply_order_date( $order, $date );
		}
	}

	/** Snapshot the common date on the order and its affected positions. */
	public static function apply_order_date( \WC_Order $order, string $date ): void {
		$order->delete_meta_data( self::DATE );
		if ( self::checkout_rules() ) {
			$order->update_meta_data( self::DATE, $date );
		}
		foreach ( $order->get_items() as $item ) {
			$rule = self::rule( $item->get_variation_id() ?: $item->get_product_id() );
			if ( 'checkout' === $rule['mode'] ) {
				$item->update_meta_data( self::DATE, $date );
			} elseif ( 'off' === $rule['mode'] ) {
				$item->delete_meta_data( self::DATE );
			}
		}
	}

	public static function create_line( \WC_Order_Item_Product $item, string $key, array $values, \WC_Order $order ): void {
		$rule = self::rule( (int) ( $values['variation_id'] ?: $values['product_id'] ) );
		if ( 'product' === $rule['mode'] && self::allowed( $values[ self::DATE ] ?? '', $rule ) ) {
			$item->add_meta_data( self::DATE, $values[ self::DATE ], true );
		}
	}

	public static function meta_label( string $key, object $meta ): string {
		return self::DATE === $meta->key ? self::label() : $key;
	}

	public static function meta_value( mixed $value, object $meta ): mixed {
		return self::DATE === $meta->key ? self::display( (string) $meta->value ) : $value;
	}

	public static function formatted_meta( array $formatted, \WC_Order_Item $item ): array {
		$date = (string) $item->get_meta( self::DATE, true );
		if ( ! $date ) {
			return $formatted;
		}
		foreach ( $formatted as $meta ) {
			if ( self::DATE === $meta->key ) {
				return $formatted;
			}
		}
		$formatted['p9od'] = (object) array( 'key' => self::DATE, 'value' => $date, 'display_key' => self::label(), 'display_value' => esc_html( self::display( $date ) ) );
		return $formatted;
	}

	public static function admin_date( \WC_Order $order ): void {
		self::order_date( $order );
	}

	public static function order_date( \WC_Order $order ): void {
		$date = (string) $order->get_meta( self::DATE, true );
		if ( $date ) {
			echo '<p><strong>' . esc_html( self::label() ) . ':</strong> ' . esc_html( self::display( $date ) ) . '</p>';
		}
	}

	public static function email_date( array $fields, bool $sent_to_admin, \WC_Order $order ): array {
		$date = (string) $order->get_meta( self::DATE, true );
		if ( $date ) {
			$fields['p9od'] = array( 'label' => self::label(), 'value' => self::display( $date ) );
		}
		return $fields;
	}

	public static function assets(): void {
		if ( ! is_product() && ! is_checkout() ) {
			return;
		}
		wp_enqueue_script( 'p9od-calendar', plugins_url( 'assets/calendar.js', P9OD_FILE ), array( 'jquery-ui-datepicker' ), P9OD_VERSION, true );
		wp_enqueue_style( 'p9od', plugins_url( 'assets/orderdate.css', P9OD_FILE ), array(), P9OD_VERSION );
		global $wp_locale;
		wp_localize_script( 'p9od-calendar', 'p9odCalendar', array(
			'monthNames' => array_values( $wp_locale->month ), 'dayNamesMin' => array_values( $wp_locale->weekday_initial ),
			'firstDay' => (int) get_option( 'start_of_week' ),
			'choose' => __( 'Abholdatum wählen', 'patsch9-orderdate' ),
			'previous' => __( 'Vorheriger Monat', 'patsch9-orderdate' ), 'next' => __( 'Nächster Monat', 'patsch9-orderdate' ),
		) );
	}
}
