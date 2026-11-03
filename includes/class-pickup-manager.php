<?php
/** Collection workflow, schedule proposals and serialized order mutations. @package Patsch9Orderdate */
namespace Patsch9\Orderdate;
defined( 'ABSPATH' ) || exit;

final class PickupManager {
	public const READY = 'p9od-ready';
	public const COLLECTED = 'p9od-collected';
	public const PROPOSAL = '_p9od_proposal';
	public const INDEX = '_p9od_managed';

	public static function boot(): void {
		add_action( 'init', array( self::class, 'statuses' ) );
		add_filter( 'wc_order_statuses', array( self::class, 'status_labels' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( self::class, 'guard_admin_status' ), 5 );
		add_filter( 'woocommerce_rest_pre_insert_shop_order_object', array( self::class, 'guard_rest_status' ), 10, 2 );
		add_action( 'woocommerce_after_order_object_save', array( self::class, 'index' ) );
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( self::class, 'legacy_query' ), 10, 2 );
		add_action( 'p9od_index_orders', array( self::class, 'backfill' ) );
		add_action( 'init', array( self::class, 'schedule_backfill' ) );
		add_action( 'woocommerce_order_status_' . self::READY, 'wc_maybe_reduce_stock_levels' );
		add_action( 'woocommerce_order_status_' . self::COLLECTED, 'wc_update_total_sales_counts' );
		add_action( 'woocommerce_order_status_' . self::COLLECTED, 'wc_update_coupon_usage_counts' );
		add_filter( 'woocommerce_order_is_paid', array( self::class, 'is_paid' ), 10, 2 );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( self::class, 'payment_statuses' ), 10, 2 );
		add_filter( 'woocommerce_payment_complete_order_status', array( self::class, 'paid_status' ), 10, 3 );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_p9od_manage_pickup', array( self::class, 'handle_admin' ) );
		add_action( 'template_redirect', array( self::class, 'confirmation_page' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( self::class, 'order_summary' ) );
	}

	public static function status_labels( array $statuses ): array {
		$statuses[ 'wc-' . self::READY ] = __( 'Bereit zur Abholung', 'patsch9-orderdate' );
		$statuses[ 'wc-' . self::COLLECTED ] = __( 'Abgeholt', 'patsch9-orderdate' );
		return $statuses;
	}

	public static function statuses(): void {
		foreach ( self::status_labels( array() ) as $key => $label ) {
			register_post_status( $key, array( 'label' => $label, 'public' => false, 'exclude_from_search' => true, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true, 'label_count' => array( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>' ) ) );
		}
	}

	public static function items( \WC_Order $order ): array {
		return array_filter( $order->get_items(), static fn( $item ) => (bool) $item->get_meta( Plugin::DATE, true ) );
	}

	public static function managed( \WC_Order $order ): bool {
		return (bool) self::items( $order ) || (bool) PickupLocations::order_locations( $order );
	}

	public static function open( \WC_Order $order ): bool {
		return ! $order->has_status( array( 'cancelled', 'refunded', 'failed', 'trash', 'checkout-draft', 'auto-draft', self::COLLECTED ) );
	}

	public static function index( \WC_Abstract_Order $order ): void {
		// The generic save hook also receives WC_Order_Refund objects.
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		$value = self::managed( $order ) ? 'yes' : 'no';
		if ( $order->get_meta( self::INDEX, true ) !== $value ) {
			$order->update_meta_data( self::INDEX, $value );
			$order->save_meta_data();
		}
	}

	public static function legacy_query( array $query, array $vars ): array {
		if ( isset( $vars['p9od_managed'] ) ) {
			$query['meta_query'][] = array( 'key' => self::INDEX, 'value' => 'yes' );
		}
		return $query;
	}

	public static function schedule_backfill(): void {
		if ( 'done' !== get_option( 'p9od_index_progress', 1 ) && ! wp_next_scheduled( 'p9od_index_orders' ) ) {
			wp_schedule_single_event( time() + 5, 'p9od_index_orders' );
		}
	}

	/** Index old orders in bounded batches, without changing their schedules/statuses. */
	public static function backfill(): void {
		$page = get_option( 'p9od_index_progress', 1 );
		if ( 'done' === $page ) {
			return;
		}
		$orders = wc_get_orders( array( 'type' => 'shop_order', 'limit' => 100, 'page' => max( 1, (int) $page ), 'orderby' => 'ID', 'order' => 'ASC', 'status' => array_keys( wc_get_order_statuses() ) ) );
		foreach ( $orders as $order ) {
			self::index( $order );
		}
		update_option( 'p9od_index_progress', count( $orders ) < 100 ? 'done' : (int) $page + 1, false );
		self::schedule_backfill();
	}

	public static function is_paid( bool $paid, \WC_Order $order ): bool {
		return $order->has_status( array( self::READY, self::COLLECTED ) ) ? (bool) $order->get_date_paid() : $paid;
	}

	public static function payment_statuses( array $statuses, \WC_Order $order ): array {
		$statuses[] = self::READY;
		return $statuses;
	}

	public static function paid_status( string $status, int $id, \WC_Order $order ): string {
		// maybe_set_date_paid() also calls this filter during ordinary status changes.
		// Only preserve READY after payment_complete() explicitly set the payment date.
		return $order->has_status( self::READY ) && $order->get_date_paid() ? self::READY : $status;
	}

	public static function assert_status( \WC_Order $order, string $target ): void {
		if ( ! in_array( $target, array( self::READY, self::COLLECTED ), true ) ) {
			return;
		}
		if ( ! self::managed( $order ) || $order->get_meta( self::PROPOSAL, true ) ) {
			throw new \WC_Data_Exception( 'p9od_pickup_pending', esc_html__( 'Abholbestellung und bestätigte Termine erforderlich.', 'patsch9-orderdate' ) );
		}
		if ( self::COLLECTED === $target && (float) $order->get_total() > 0 && ! $order->get_date_paid() ) {
			throw new \WC_Data_Exception( 'p9od_payment_pending', esc_html__( 'Bitte zuerst den Zahlungseingang bestätigen.', 'patsch9-orderdate' ) );
		}
	}

	/** Validate before WooCommerce mutates the order; WC_Order::save catches exceptions. */
	public static function guard_admin_status( int $id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies its order-save nonce and capabilities before this lifecycle hook.
		$target = isset( $_POST['order_status'] ) && is_string( $_POST['order_status'] ) ? sanitize_key( wp_unslash( $_POST['order_status'] ) ) : '';
		$order = wc_get_order( $id );
		if ( ! $order || ! current_user_can( 'edit_shop_order', $id ) ) {
			return;
		}
		try {
			self::assert_status( $order, preg_replace( '/^wc-/', '', $target ) );
		} catch ( \Exception $error ) {
			$_POST['order_status'] = 'wc-' . $order->get_status();
			\WC_Admin_Meta_Boxes::add_error( $error->getMessage() );
		}
	}

	public static function guard_rest_status( mixed $order, \WP_REST_Request $request ): mixed {
		if ( ! $order instanceof \WC_Order || ! $request->has_param( 'status' ) ) {
			return $order;
		}
		try {
			self::assert_status( $order, $order->get_status() );
		} catch ( \Exception $error ) {
			return new \WP_Error( 'p9od_status_invalid', $error->getMessage(), array( 'status' => 400 ) );
		}
		return $order;
	}

	/** Prevent two tabs/customer confirmation from overwriting each other's changes. */
	public static function version( \WC_Order $order ): string {
		$items = array();
		foreach ( self::items( $order ) as $id => $item ) {
			$items[ $id ] = array( $item->get_meta( Plugin::DATE, true ), $item->get_meta( Plugin::TIME, true ) );
		}
		return hash( 'sha256', wp_json_encode( array( $order->get_status(), $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : null, $items, $order->get_meta( self::PROPOSAL, true ) ) ) );
	}

	/** MySQL/MariaDB advisory locks are connection-scoped and released on disconnect. */
	public static function locked( int $id, callable $callback ): mixed {
		global $wpdb;
		$key = 'p9od_' . md5( $wpdb->prefix . ':' . $id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Connection-scoped lock; cannot be cached or represented through CRUD.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $key ) ) ) {
			throw new \RuntimeException( esc_html__( 'Diese Bestellung wird gerade bearbeitet. Bitte erneut versuchen.', 'patsch9-orderdate' ) );
		}
		try {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				throw new \RuntimeException( esc_html__( 'Bestellung nicht gefunden.', 'patsch9-orderdate' ) );
			}
			$order->get_data_store()->read( $order );
			$order->read_meta_data( true );
			return $callback( $order );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release this connection's advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
		}
	}

	public static function propose( \WC_Order $order, int $item_id, string $date, string $window ): string {
		$item = self::items( $order )[ $item_id ] ?? null;
		if ( ! self::open( $order ) || ! $item ) {
			throw new \InvalidArgumentException( esc_html__( 'Diese Abholung kann nicht umgeplant werden.', 'patsch9-orderdate' ) );
		}
		$rule = Plugin::rule( $item->get_variation_id() ?: $item->get_product_id() );
		if ( 'off' === $rule['mode'] || ! Plugin::selection_allowed( $date, $window, array( $rule ) ) || ( ! Rules::requires_time( array( $rule ) ) && '' !== $window ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Der neue Termin entspricht nicht den aktuellen Abholregeln.', 'patsch9-orderdate' ) );
		}
		if ( $date === $item->get_meta( Plugin::DATE, true ) && $window === $item->get_meta( Plugin::TIME, true ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Bitte einen anderen Termin wählen.', 'patsch9-orderdate' ) );
		}
		if ( ! is_email( $order->get_billing_email() ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Für die Terminbestätigung fehlt eine gültige Kunden-E-Mail-Adresse.', 'patsch9-orderdate' ) );
		}
		$token = bin2hex( random_bytes( 32 ) );
		$order->update_meta_data( self::PROPOSAL, array(
			'item' => $item_id, 'date' => $date, 'time' => $window,
			'previous_date' => (string) $item->get_meta( Plugin::DATE, true ), 'previous_time' => (string) $item->get_meta( Plugin::TIME, true ),
			'hash' => hash( 'sha256', $token ), 'expires' => time() + 7 * DAY_IN_SECONDS,
		) );
		if ( $order->has_status( self::READY ) ) {
			$order->set_status( $order->get_date_paid() ? 'processing' : 'on-hold' );
		}
		$order->save();
		$order->add_order_note( __( 'Neuer Abholtermin vorgeschlagen; Kundenbestätigung ausstehend.', 'patsch9-orderdate' ) . ' ' . $item->get_name() . ': ' . Plugin::display( $date ) . ' ' . Plugin::display_time( $window ) );
		return $token;
	}

	public static function valid_token( \WC_Order $order, string $token ): bool {
		$p = $order->get_meta( self::PROPOSAL, true );
		return self::open( $order ) && is_array( $p ) && isset( $p['hash'], $p['expires'] ) && (int) $p['expires'] >= time() && 64 === strlen( $token ) && hash_equals( (string) $p['hash'], hash( 'sha256', $token ) );
	}

	public static function confirm( \WC_Order $order, string $token ): void {
		if ( ! self::valid_token( $order, $token ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Dieser Bestätigungslink ist ungültig oder abgelaufen.', 'patsch9-orderdate' ) );
		}
		$p = $order->get_meta( self::PROPOSAL, true );
		$item = self::items( $order )[ $p['item'] ] ?? null;
		$rule = $item ? Plugin::rule( $item->get_variation_id() ?: $item->get_product_id() ) : Rules::defaults();
		if ( ! $item || 'off' === $rule['mode'] || $p['previous_date'] !== $item->get_meta( Plugin::DATE, true ) || $p['previous_time'] !== (string) $item->get_meta( Plugin::TIME, true ) || ! Plugin::selection_allowed( $p['date'], $p['time'], array( $rule ) ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Der Termin wurde zwischenzeitlich geändert oder ist nicht mehr verfügbar. Bitte kontaktiere den Shop.', 'patsch9-orderdate' ) );
		}
		$item->update_meta_data( Plugin::DATE, $p['date'] );
		$item->delete_meta_data( Plugin::TIME );
		if ( $p['time'] ) {
			$item->update_meta_data( Plugin::TIME, $p['time'] );
		}
		$item->save();
		// A common order-level date is no longer accurate after an individual reschedule.
		$order->delete_meta_data( Plugin::DATE );
		$order->delete_meta_data( Plugin::TIME );
		$order->delete_meta_data( self::PROPOSAL );
		$order->save();
		$order->add_order_note( __( 'Kunde hat den neuen Abholtermin bestätigt.', 'patsch9-orderdate' ) . ' ' . $item->get_name() . ': ' . Plugin::display( $p['date'] ) . ' ' . Plugin::display_time( $p['time'] ) );
		do_action( 'p9od_pickup_confirmed', $order->get_id() );
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', __( 'Abholmanager', 'patsch9-orderdate' ), __( 'Abholmanager', 'patsch9-orderdate' ), 'manage_woocommerce', 'p9od-pickups', array( self::class, 'page' ) );
	}

	private static function input( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification -- Callers validate the appropriate nonce/token before mutation.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	public static function handle_admin(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'patsch9-orderdate' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'p9od_manage_pickup', 'p9od_nonce' );
		try {
			$id = absint( self::input( 'order_id' ) );
			if ( ! current_user_can( 'edit_shop_order', $id ) ) {
				throw new \RuntimeException( esc_html__( 'Keine Berechtigung.', 'patsch9-orderdate' ) );
			}
			$message = self::locked( $id, static function ( $order ) {
				if ( ! hash_equals( self::version( $order ), self::input( 'version' ) ) ) {
					throw new \RuntimeException( esc_html__( 'Die Bestellung wurde inzwischen geändert. Bitte die Seite neu laden.', 'patsch9-orderdate' ) );
				}
				if ( ! self::open( $order ) || ! self::managed( $order ) ) {
					throw new \RuntimeException( esc_html__( 'Diese Abholung ist bereits geschlossen.', 'patsch9-orderdate' ) );
				}
				$action = self::input( 'pickup_action' );
				if ( 'propose' === $action || 'resend' === $action ) {
					$p = $order->get_meta( self::PROPOSAL, true );
					if ( 'resend' === $action && ! is_array( $p ) ) {
						throw new \RuntimeException( esc_html__( 'Keine ausstehende Terminbestätigung.', 'patsch9-orderdate' ) );
					}
					$appointment = explode( '|', self::input( 'appointment' ), 2 );
					$token = self::propose( $order, 'resend' === $action ? (int) $p['item'] : absint( self::input( 'item_id' ) ), 'resend' === $action ? $p['date'] : $appointment[0], 'resend' === $action ? $p['time'] : ( $appointment[1] ?? '' ) );
					$emails = WC()->mailer()->get_emails();
					$sent = $emails['p9od_rescheduled']->trigger( $order->get_id(), $token );
					return $sent ? __( 'Terminvorschlag per E-Mail gesendet.', 'patsch9-orderdate' ) : __( 'Vorschlag gespeichert, E-Mail konnte nicht gesendet werden. Bitte erneut senden.', 'patsch9-orderdate' );
				}
				if ( 'cancel_proposal' === $action ) {
					$order->delete_meta_data( self::PROPOSAL );
					$order->save();
					$order->add_order_note( __( 'Terminvorschlag zurückgezogen; bisheriger Termin bleibt gültig.', 'patsch9-orderdate' ) );
				} elseif ( 'ready' === $action ) {
					if ( $order->has_status( self::READY ) ) {
						throw new \RuntimeException( esc_html__( 'Die Bestellung ist bereits bereit zur Abholung.', 'patsch9-orderdate' ) );
					}
					$order->set_status( self::READY );
					self::assert_status( $order, self::READY );
					$order->save();
				} elseif ( 'collected' === $action ) {
					if ( ! $order->has_status( self::READY ) ) {
						throw new \RuntimeException( esc_html__( 'Bitte zuerst als bereit zur Abholung markieren.', 'patsch9-orderdate' ) );
					}
					if ( ! $order->get_date_paid() && (float) $order->get_total() > 0 && 'p9od_pay_on_pickup' === $order->get_payment_method() && 'yes' === self::input( 'payment_received' ) ) {
						$order->payment_complete();
						$order->add_order_note( __( 'Zahlung vor Ort bei der Übergabe bestätigt.', 'patsch9-orderdate' ) );
					}
					$order->set_status( self::COLLECTED );
					self::assert_status( $order, self::COLLECTED );
					$order->save();
				} else {
					throw new \RuntimeException( esc_html__( 'Ungültige Aktion.', 'patsch9-orderdate' ) );
				}
				return __( 'Abholung aktualisiert.', 'patsch9-orderdate' );
			} );
		} catch ( \Exception $error ) {
			$message = $error->getMessage();
		}
		set_transient( 'p9od_manager_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=p9od-pickups' ) );
		exit;
	}

	public static function order_summary( \WC_Order $order ): void {
		if ( ! self::managed( $order ) ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Abholung', 'patsch9-orderdate' ) . ':</strong> ' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</p>';
		foreach ( PickupLocations::order_locations( $order ) as $location ) {
			echo '<p>' . esc_html( $location ) . '</p>';
		}
		$p = $order->get_meta( self::PROPOSAL, true );
		if ( is_array( $p ) ) {
			echo '<p><strong>' . esc_html__( 'Bestätigung ausstehend', 'patsch9-orderdate' ) . ':</strong> ' . esc_html( Plugin::display( $p['date'] ) . ' ' . Plugin::display_time( $p['time'] ) ) . '</p>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=p9od-pickups' ) ) . '">' . esc_html__( 'Abholmanager öffnen', 'patsch9-orderdate' ) . '</a></p>';
	}

	private static function form_start( \WC_Order $order ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="p9od_manage_pickup"><input type="hidden" name="order_id" value="' . esc_attr( $order->get_id() ) . '"><input type="hidden" name="version" value="' . esc_attr( self::version( $order ) ) . '">';
		wp_nonce_field( 'p9od_manage_pickup', 'p9od_nonce' );
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page = isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$statuses = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-cancelled', 'wc-refunded', 'wc-failed', 'wc-checkout-draft', 'wc-' . self::COLLECTED ) );
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded, indexed order-meta filter required for HPOS-compatible pickup pagination.
		$result = wc_get_orders( array( 'type' => 'shop_order', 'status' => $statuses, 'limit' => 25, 'page' => $page, 'paginate' => true, 'orderby' => 'date', 'order' => 'ASC', 'p9od_managed' => true, 'meta_query' => array( array( 'key' => self::INDEX, 'value' => 'yes' ) ) ) );
		echo '<div class="wrap p9od-manager"><h1>' . esc_html__( 'Abholmanager', 'patsch9-orderdate' ) . '</h1>';
		$notice = get_transient( 'p9od_manager_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'p9od_manager_notice_' . get_current_user_id() );
			echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		if ( 'done' !== get_option( 'p9od_index_progress', 1 ) ) {
			echo '<p role="status">' . esc_html__( 'Ältere Bestellungen werden im Hintergrund eingelesen. Die Übersicht ergänzt sich automatisch.', 'patsch9-orderdate' ) . '</p>';
		}
		if ( ! $result->orders ) {
			echo '<p>' . esc_html__( 'Keine offenen Abholungen auf dieser Seite.', 'patsch9-orderdate' ) . '</p>';
		}
		foreach ( $result->orders as $order ) {
			echo '<section class="p9od-pickup-card"><h2><a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a> · ' . esc_html( $order->get_formatted_billing_full_name() ) . '</h2>';
			self::order_summary( $order );
			$p = $order->get_meta( self::PROPOSAL, true );
			echo '<ul>';
			foreach ( self::items( $order ) as $item ) {
				echo '<li>' . esc_html( $item->get_quantity() . ' × ' . $item->get_name() . ' · ' . Plugin::display( $item->get_meta( Plugin::DATE, true ) ) . ' ' . Plugin::display_time( (string) $item->get_meta( Plugin::TIME, true ) ) ) . '</li>';
			}
			echo '</ul>';
			self::form_start( $order );
			if ( $p ) {
				echo '<button class="button" name="pickup_action" value="resend">' . esc_html__( 'Bestätigungslink erneut senden', 'patsch9-orderdate' ) . '</button> <button class="button" name="pickup_action" value="cancel_proposal">' . esc_html__( 'Vorschlag zurückziehen', 'patsch9-orderdate' ) . '</button>';
			} elseif ( $order->has_status( self::READY ) ) {
				if ( 'p9od_pay_on_pickup' === $order->get_payment_method() && ! $order->get_date_paid() && (float) $order->get_total() > 0 ) {
					echo '<label><input type="checkbox" name="payment_received" value="yes" required> ' . esc_html__( 'Zahlung vor Ort erhalten', 'patsch9-orderdate' ) . '</label> ';
				}
				echo '<button class="button button-primary" name="pickup_action" value="collected">' . esc_html__( 'Abgeholt bestätigen', 'patsch9-orderdate' ) . '</button>';
			} else {
				echo '<button class="button button-primary" name="pickup_action" value="ready">' . esc_html__( 'Bereit zur Abholung', 'patsch9-orderdate' ) . '</button>';
			}
			echo '</form>';
			if ( ! $p && self::items( $order ) ) {
				echo '<details><summary>' . esc_html__( 'Abholung umplanen', 'patsch9-orderdate' ) . '</summary>';
				foreach ( self::items( $order ) as $id => $item ) {
					$rule = Plugin::rule( $item->get_variation_id() ?: $item->get_product_id() );
					$dates = 'off' === $rule['mode'] ? array() : Plugin::dates( array( $rule ) );
					self::form_start( $order );
					echo '<input type="hidden" name="item_id" value="' . esc_attr( $id ) . '"><p><label>' . esc_html( $item->get_name() ) . ' <select name="appointment" required><option value="">' . esc_html__( 'Neuen Abholtermin wählen', 'patsch9-orderdate' ) . '</option>';
					foreach ( $dates as $date ) {
						$windows = Rules::requires_time( array( $rule ) ) ? Plugin::windows( $date, array( $rule ) ) : array( '' );
						foreach ( $windows as $window ) {
							echo '<option value="' . esc_attr( $date . '|' . $window ) . '">' . esc_html( Plugin::display( $date ) . ' ' . Plugin::display_time( $window ) ) . '</option>';
						}
					}
					echo '</select></label></p><button class="button" name="pickup_action" value="propose">' . esc_html__( 'Neuen Termin zur Bestätigung senden', 'patsch9-orderdate' ) . '</button></form>';
				}
				echo '</details>';
			}
			echo '</section>';
		}
		echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'total' => $result->max_num_pages, 'current' => $page ) ) );
		echo '</div>';
	}

	/** GET shows a form; only an explicit, token- and nonce-protected POST confirms. */
	public static function confirmation_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET only renders the confirmation form.
		if ( ! isset( $_GET['p9od_confirm'] ) ) {
			return;
		}
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token is the authorization; GET never mutates an order.
		$id = isset( $_GET['order'] ) && is_string( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated against the stored hash before rendering any order information.
		$token = isset( $_GET['token'] ) && is_string( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		try {
			$order = wc_get_order( $id );
			if ( ! $order || ! self::valid_token( $order, $token ) ) {
				throw new \InvalidArgumentException( esc_html__( 'Dieser Bestätigungslink ist ungültig oder abgelaufen.', 'patsch9-orderdate' ) );
			}
			if ( 'post' === ( isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
				if ( ! wp_verify_nonce( self::input( 'p9od_nonce' ), 'p9od_confirm_' . hash( 'sha256', $token ) ) ) {
					throw new \InvalidArgumentException( esc_html__( 'Bitte den Link erneut öffnen und die Bestätigung wiederholen.', 'patsch9-orderdate' ) );
				}
				self::locked( $id, static fn( $fresh ) => self::confirm( $fresh, $token ) );
				wp_die( esc_html__( 'Dein neuer Abholtermin ist bestätigt. Vielen Dank!', 'patsch9-orderdate' ), esc_html__( 'Abholtermin bestätigt', 'patsch9-orderdate' ), array( 'response' => 200 ) );
			}
			$p = $order->get_meta( self::PROPOSAL, true );
			$item = $order->get_item( $p['item'] );
			$html = '<p><strong>#' . esc_html( $order->get_order_number() ) . ' · ' . esc_html( $item ? $item->get_name() : '' ) . '</strong></p><p>' . esc_html( Plugin::display( $p['date'] ) . ' ' . Plugin::display_time( $p['time'] ) ) . '</p>';
			foreach ( PickupLocations::order_locations( $order ) as $location ) {
				$html .= '<p>' . esc_html( $location ) . '</p>';
			}
			$html .= '<form method="post">' . wp_nonce_field( 'p9od_confirm_' . hash( 'sha256', $token ), 'p9od_nonce', false, false ) . '<button type="submit">' . esc_html__( 'Neuen Abholtermin bestätigen', 'patsch9-orderdate' ) . '</button></form>';
			wp_die( $html, esc_html__( 'Abholtermin bestätigen', 'patsch9-orderdate' ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic text escaped; nonce field is trusted markup.
		} catch ( \Exception $error ) {
			wp_die( esc_html( $error->getMessage() ), esc_html__( 'Abholtermin', 'patsch9-orderdate' ), array( 'response' => 400 ) );
		}
	}
}
