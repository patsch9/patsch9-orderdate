<?php
/** Product rules and shop settings. @package Patsch9Orderdate */

namespace Patsch9\Orderdate;

defined( 'ABSPATH' ) || exit;

final class Admin {
	public static function boot(): void {
		add_filter( 'woocommerce_product_data_tabs', array( self::class, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( self::class, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( self::class, 'save_product' ) );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_p9od_settings', array( self::class, 'save_settings' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function tab( array $tabs ): array {
		$tabs['p9od'] = array( 'label' => __( 'Abholtermine', 'patsch9-orderdate' ), 'target' => 'p9od_rules', 'class' => array( 'show_if_simple', 'show_if_variable' ) );
		return $tabs;
	}

	public static function panel(): void {
		global $product_object;
		$rule = $product_object ? Plugin::raw_rule( $product_object->get_id() ) : Rules::defaults();
		echo '<div id="p9od_rules" class="panel woocommerce_options_panel hidden">';
		wp_nonce_field( 'p9od_save_product', 'p9od_admin_nonce' );

		echo '<div class="options_group p9od-product-card"><h4>' . esc_html__( 'Abholung', 'patsch9-orderdate' ) . '</h4>';
		woocommerce_wp_select( array( 'id' => 'p9od_mode', 'label' => __( 'Terminauswahl', 'patsch9-orderdate' ), 'value' => $rule['mode'], 'options' => array(
			'off' => __( 'Deaktiviert', 'patsch9-orderdate' ), 'product' => __( 'Auf der Produktseite', 'patsch9-orderdate' ), 'checkout' => __( 'An der Kasse', 'patsch9-orderdate' ),
		) ) );
		woocommerce_wp_select( array( 'id' => 'p9od_schedule', 'label' => __( 'Abholtage', 'patsch9-orderdate' ), 'value' => $rule['schedule'], 'options' => array(
			'weekly' => __( 'Wochentage', 'patsch9-orderdate' ), 'dates' => __( 'Einzeltermine', 'patsch9-orderdate' ), 'combined' => __( 'Wochentage + Einzeltermine', 'patsch9-orderdate' ),
		) ) );
		foreach ( array( 'from' => __( 'Verfügbar ab', 'patsch9-orderdate' ), 'until' => __( 'Verfügbar bis', 'patsch9-orderdate' ) ) as $key => $label ) {
			woocommerce_wp_text_input( array( 'id' => 'p9od_' . $key, 'label' => $label, 'type' => 'date', 'value' => $rule[ $key ] ) );
		}
		global $wp_locale;
		echo '<fieldset class="form-field p9od-weekdays"><legend>' . esc_html__( 'Wochentage', 'patsch9-orderdate' ) . '</legend><div class="p9od-weekday-options">';
		for ( $day = 1; $day <= 7; ++$day ) {
			echo '<label><input type="checkbox" name="p9od_weekdays[]" value="' . esc_attr( $day ) . '" ' . checked( in_array( $day, $rule['weekdays'], true ), true, false ) . '> <span>' . esc_html( $wp_locale->weekday[ $day % 7 ] ) . '</span></label>';
		}
		echo '</div></fieldset>';
		self::date_list( 'p9od_dates', __( 'Einzeltermine', 'patsch9-orderdate' ), $rule['dates'] );
		self::date_list( 'p9od_blocked', __( 'Gesperrte Termine', 'patsch9-orderdate' ), $rule['blocked'] );
		echo '</div>';

		echo '<div class="options_group p9od-product-card"><h4>' . esc_html__( 'Zeitfenster', 'patsch9-orderdate' ) . '</h4>';
		woocommerce_wp_select( array( 'id' => 'p9od_time_mode', 'label' => __( 'Zeitfenster', 'patsch9-orderdate' ), 'value' => $rule['time_mode'], 'options' => array(
			'inherit' => __( 'Globale übernehmen', 'patsch9-orderdate' ), 'custom' => __( 'Eigene Zeitfenster', 'patsch9-orderdate' ), 'off' => __( 'Ohne Zeitfenster', 'patsch9-orderdate' ),
		) ) );
		self::window_editor( 'p9od_time_windows', $rule['time_windows'] );
		echo '</div>';

		echo '<div class="options_group p9od-product-card"><h4>' . esc_html__( 'Bestellregeln', 'patsch9-orderdate' ) . '</h4>';
		woocommerce_wp_text_input( array( 'id' => 'p9od_lead_days', 'label' => __( 'Vorlauf', 'patsch9-orderdate' ), 'type' => 'number', 'value' => $rule['lead_days'], 'description' => __( 'Kalendertage', 'patsch9-orderdate' ), 'custom_attributes' => array( 'min' => 0, 'max' => 730, 'step' => 1 ) ) );
		woocommerce_wp_text_input( array( 'id' => 'p9od_cutoff', 'label' => __( 'Bestellschluss', 'patsch9-orderdate' ), 'type' => 'time', 'value' => $rule['cutoff'], 'description' => __( 'Danach verschiebt sich der früheste Termin um einen Tag.', 'patsch9-orderdate' ), 'desc_tip' => true ) );
		echo '</div></div>';
	}

	/** A native date picker appends ISO dates to an editable, accessible list. */
	private static function date_list( string $id, string $label, array $dates ): void {
		echo '<div class="form-field p9od-date-list"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><div class="p9od-field-content"><textarea rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">' . esc_textarea( implode( "\n", $dates ) ) . '</textarea><span class="description">' . esc_html__( 'Ein Datum je Zeile.', 'patsch9-orderdate' ) . '</span><div class="p9od-field-actions"><input type="date" class="p9od-list-picker" aria-label="' . esc_attr( $label ) . '"> <button type="button" class="button p9od-list-add">' . esc_html__( 'Hinzufügen', 'patsch9-orderdate' ) . '</button></div></div></div>';
	}

	private static function window_editor( string $id, array $windows ): void {
		$rows = array();
		foreach ( $windows as $day => $ranges ) {
			foreach ( $ranges as $range ) {
				$rows[] = $day . ':' . $range;
			}
		}
		echo '<div class="form-field p9od-window-list"><label for="' . esc_attr( $id ) . '">' . esc_html__( 'Eigene Zeitfenster', 'patsch9-orderdate' ) . '</label><div class="p9od-field-content"><textarea rows="5" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">' . esc_textarea( implode( "\n", $rows ) ) . '</textarea><span class="description">' . esc_html__( 'Ein Eintrag je Zeile. *=alle Tage, 1=Montag bis 7=Sonntag.', 'patsch9-orderdate' ) . '</span><div class="p9od-field-actions"><select class="p9od-window-day" aria-label="' . esc_attr__( 'Wochentag des Zeitfensters', 'patsch9-orderdate' ) . '"><option value="*">' . esc_html__( 'Alle Tage', 'patsch9-orderdate' ) . '</option>';
		global $wp_locale;
		for ( $day = 1; $day <= 7; ++$day ) {
			echo '<option value="' . esc_attr( $day ) . '">' . esc_html( $wp_locale->weekday[ $day % 7 ] ) . '</option>';
		}
		echo '</select> <input type="time" class="p9od-window-start" aria-label="' . esc_attr__( 'Beginn des Zeitfensters', 'patsch9-orderdate' ) . '"> <input type="time" class="p9od-window-end" aria-label="' . esc_attr__( 'Ende des Zeitfensters', 'patsch9-orderdate' ) . '"> <button type="button" class="button p9od-window-add">' . esc_html__( 'Hinzufügen', 'patsch9-orderdate' ) . '</button></div></div></div>';
	}

	/** Reads are only used after the corresponding nonce and capability checks. */
	private static function input( string $name, string $default = '' ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in save_product/save_settings before this helper is used.
		return isset( $_POST[ $name ] ) && is_string( $_POST[ $name ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $name ] ) ) : $default;
	}

	private static function list_input( string $name ): array {
		$value = trim( self::input( $name ) );
		return '' === $value ? array() : preg_split( '/[\s,;]+/', $value );
	}

	public static function save_product( \WC_Product $product ): void {
		if ( ! current_user_can( 'edit_post', $product->get_id() ) || ! wp_verify_nonce( self::input( 'p9od_admin_nonce' ), 'p9od_save_product' ) ) {
			return;
		}
		try {
			$input = array();
			foreach ( array( 'mode', 'schedule', 'from', 'until', 'lead_days', 'cutoff', 'time_mode' ) as $key ) {
				$input[ $key ] = self::input( 'p9od_' . $key, (string) Rules::defaults()[ $key ] );
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Rules::normalize strictly validates each weekday against an allowlist.
			$input['weekdays'] = isset( $_POST['p9od_weekdays'] ) ? wp_unslash( $_POST['p9od_weekdays'] ) : array();
			$input['dates'] = self::list_input( 'p9od_dates' );
			$input['blocked'] = self::list_input( 'p9od_blocked' );
			$input['time_windows'] = Rules::parse_windows( self::input( 'p9od_time_windows' ) );
			$product->update_meta_data( Plugin::META, Rules::normalize( $input, wp_timezone() ) );
		} catch ( \InvalidArgumentException $error ) {
			/* translators: %s: Validation error. */
			\WC_Admin_Meta_Boxes::add_error( sprintf( __( 'Abholregeln nicht gespeichert: %s', 'patsch9-orderdate' ), $error->getMessage() ) );
		}
	}

	public static function menu(): void {
		add_submenu_page( 'woocommerce', __( 'Abholtermine', 'patsch9-orderdate' ), __( 'Abholtermine', 'patsch9-orderdate' ), 'manage_woocommerce', 'p9od', array( self::class, 'settings_page' ) );
	}

	public static function settings_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = Plugin::settings();
		echo '<div class="wrap woocommerce p9od-settings-page"><h1>' . esc_html__( 'Abholtermine', 'patsch9-orderdate' ) . '</h1>';
		// A per-user transient avoids echoing untrusted query-string messages.
		$notice = get_transient( 'p9od_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'p9od_notice_' . get_current_user_id() );
			echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="p9od_settings">';
		wp_nonce_field( 'p9od_settings', 'p9od_settings_nonce' );
		echo '<div class="p9od-settings-grid">';
		echo '<section class="p9od-settings-card"><h2>' . esc_html__( 'Allgemein', 'patsch9-orderdate' ) . '</h2><div class="p9od-setting-field"><label for="p9od_horizon">' . esc_html__( 'Maximal im Voraus', 'patsch9-orderdate' ) . '</label><div><input type="number" required min="1" max="730" name="p9od_horizon" id="p9od_horizon" value="' . esc_attr( $settings['horizon'] ) . '"> <span class="description">' . esc_html__( 'Kalendertage', 'patsch9-orderdate' ) . '</span></div></div></section>';
		echo '<section class="p9od-settings-card"><h2>' . esc_html__( 'Sperrtage', 'patsch9-orderdate' ) . '</h2>';
		self::date_list( 'p9od_blocked', __( 'Betriebsferien', 'patsch9-orderdate' ), $settings['blocked'] );
		echo '</section>';
		echo '<section class="p9od-settings-card"><h2>' . esc_html__( 'Globale Zeitfenster', 'patsch9-orderdate' ) . '</h2>';
		self::window_editor( 'p9od_time_windows', $settings['time_windows'] );
		echo '</section></div>';
		submit_button();
		echo '<p class="description p9od-settings-note">' . esc_html__( 'Versandart „Abholung“ separat in WooCommerce konfigurieren.', 'patsch9-orderdate' ) . '</p></form></div>';
	}

	public static function save_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'patsch9-orderdate' ), 403 );
		}
		check_admin_referer( 'p9od_settings', 'p9od_settings_nonce' );
		try {
			$horizon = self::input( 'p9od_horizon' );
			if ( ! preg_match( '/^\d{1,3}$/D', $horizon ) || (int) $horizon < 1 || (int) $horizon > 730 ) {
				throw new \InvalidArgumentException( __( 'Buchungshorizont muss zwischen 1 und 730 Tagen liegen.', 'patsch9-orderdate' ) );
			}
			$rule = Rules::normalize( array( 'blocked' => self::list_input( 'p9od_blocked' ) ), wp_timezone() );
			$windows = Rules::parse_windows( self::input( 'p9od_time_windows' ) );
			update_option( 'p9od_settings', array( 'horizon' => (int) $horizon, 'blocked' => $rule['blocked'], 'time_windows' => $windows ), false );
			$message = __( 'Einstellungen gespeichert.', 'patsch9-orderdate' );
		} catch ( \InvalidArgumentException $error ) {
			$message = $error->getMessage();
		}
		set_transient( 'p9od_notice_' . get_current_user_id(), $message, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=p9od' ) );
		exit;
	}

	public static function assets(): void {
		$screen = get_current_screen();
		if ( $screen && ( 'product' === $screen->id || 'woocommerce_page_p9od' === $screen->id ) ) {
			wp_enqueue_style( 'p9od-admin', plugins_url( 'assets/admin.css', P9OD_FILE ), array( 'dashicons' ), P9OD_VERSION );
			wp_enqueue_script( 'p9od-admin', plugins_url( 'assets/admin.js', P9OD_FILE ), array(), P9OD_VERSION, true );
		}
	}
}
