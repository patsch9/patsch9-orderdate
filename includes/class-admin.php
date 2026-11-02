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
		echo '<div id="p9od_rules" class="panel woocommerce_options_panel hidden"><div class="options_group">';
		wp_nonce_field( 'p9od_save_product', 'p9od_admin_nonce' );
		woocommerce_wp_select( array( 'id' => 'p9od_mode', 'label' => __( 'Terminauswahl', 'patsch9-orderdate' ), 'value' => $rule['mode'], 'options' => array(
			'off' => __( 'Deaktiviert', 'patsch9-orderdate' ), 'product' => __( 'Auf der Produktseite (je Position)', 'patsch9-orderdate' ), 'checkout' => __( 'An der Kasse (gemeinsamer Termin)', 'patsch9-orderdate' ),
		) ) );
		woocommerce_wp_select( array( 'id' => 'p9od_schedule', 'label' => __( 'Abholtage', 'patsch9-orderdate' ), 'value' => $rule['schedule'], 'options' => array(
			'weekly' => __( 'Nur Wochentage', 'patsch9-orderdate' ), 'dates' => __( 'Nur Einzeltermine', 'patsch9-orderdate' ), 'combined' => __( 'Wochentage und zusätzliche Einzeltermine', 'patsch9-orderdate' ),
		) ) );
		foreach ( array( 'from' => __( 'Verfügbar ab', 'patsch9-orderdate' ), 'until' => __( 'Verfügbar bis', 'patsch9-orderdate' ) ) as $key => $label ) {
			woocommerce_wp_text_input( array( 'id' => 'p9od_' . $key, 'label' => $label, 'type' => 'date', 'value' => $rule[ $key ] ) );
		}
		global $wp_locale;
		echo '<fieldset class="form-field p9od-weekdays"><legend>' . esc_html__( 'Wochentage', 'patsch9-orderdate' ) . '</legend>';
		for ( $day = 1; $day <= 7; ++$day ) {
			echo '<label style="float:none;width:auto;margin:0 12px 0 0;display:inline-block"><input type="checkbox" name="p9od_weekdays[]" value="' . esc_attr( $day ) . '" ' . checked( in_array( $day, $rule['weekdays'], true ), true, false ) . '> ' . esc_html( $wp_locale->weekday[ $day % 7 ] ) . '</label>';
		}
		echo '</fieldset>';
		self::date_list( 'p9od_dates', __( 'Einzelne Abholtermine', 'patsch9-orderdate' ), $rule['dates'] );
		self::date_list( 'p9od_blocked', __( 'Gesperrte Termine', 'patsch9-orderdate' ), $rule['blocked'] );
		woocommerce_wp_select( array( 'id' => 'p9od_time_mode', 'label' => __( 'Abholzeitfenster', 'patsch9-orderdate' ), 'value' => $rule['time_mode'], 'options' => array(
			'inherit' => __( 'Globale Zeitfenster übernehmen', 'patsch9-orderdate' ), 'custom' => __( 'Eigene Zeitfenster', 'patsch9-orderdate' ), 'off' => __( 'Nur Datum, ohne Zeitfenster', 'patsch9-orderdate' ),
		) ) );
		self::window_editor( 'p9od_time_windows', $rule['time_windows'] );
		woocommerce_wp_text_input( array( 'id' => 'p9od_lead_days', 'label' => __( 'Vorlauf (Kalendertage)', 'patsch9-orderdate' ), 'type' => 'number', 'value' => $rule['lead_days'], 'custom_attributes' => array( 'min' => 0, 'max' => 730, 'step' => 1 ) ) );
		woocommerce_wp_text_input( array( 'id' => 'p9od_cutoff', 'label' => __( 'Täglicher Bestellschluss', 'patsch9-orderdate' ), 'type' => 'time', 'value' => $rule['cutoff'], 'description' => __( 'Ab dieser Uhrzeit verschiebt sich der früheste Termin um einen Kalendertag. Es gilt die WordPress-Zeitzone.', 'patsch9-orderdate' ), 'desc_tip' => true ) );
		echo '<p class="form-field">' . esc_html__( 'Varianten übernehmen die Regeln des Hauptprodukts. Sperrtage, Verfügbarkeitsgrenzen und Vorlauf gelten auch für Einzeltermine. Ohne gewählten Wochentag sind keine wiederkehrenden Termine verfügbar.', 'patsch9-orderdate' ) . '</p>';
		echo '</div></div>';
	}

	/** A native date picker appends ISO dates to an editable, accessible list. */
	private static function date_list( string $id, string $label, array $dates ): void {
		echo '<p class="form-field p9od-date-list"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><textarea rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">' . esc_textarea( implode( "\n", $dates ) ) . '</textarea><span class="description">' . esc_html__( 'Je Zeile ein Datum im Format JJJJ-MM-TT. Zum Entfernen die betreffende Zeile löschen.', 'patsch9-orderdate' ) . '</span><span style="display:block"><input type="date" class="p9od-list-picker" aria-label="' . esc_attr( $label ) . '"> <button type="button" class="button p9od-list-add">' . esc_html__( 'Datum hinzufügen', 'patsch9-orderdate' ) . '</button></span></p>';
	}

	private static function window_editor( string $id, array $windows ): void {
		$rows = array();
		foreach ( $windows as $day => $ranges ) {
			foreach ( $ranges as $range ) {
				$rows[] = $day . ':' . $range;
			}
		}
		echo '<p class="form-field p9od-window-list"><label for="' . esc_attr( $id ) . '">' . esc_html__( 'Zeitplan', 'patsch9-orderdate' ) . '</label><textarea rows="5" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">' . esc_textarea( implode( "\n", $rows ) ) . '</textarea><span class="description">' . esc_html__( 'Je Zeile z. B. *:13:00-14:00 (alle Tage) oder 7:12:00-13:00 (Sonntag). 1=Montag bis 7=Sonntag. Eigene Wochentage ersetzen „alle Tage“. Zeile löschen zum Entfernen. Keine Übernacht-Zeitfenster.', 'patsch9-orderdate' ) . '</span><span style="display:block"><select class="p9od-window-day" aria-label="' . esc_attr__( 'Wochentag des Zeitfensters', 'patsch9-orderdate' ) . '"><option value="*">' . esc_html__( 'Alle Tage', 'patsch9-orderdate' ) . '</option>';
		global $wp_locale;
		for ( $day = 1; $day <= 7; ++$day ) {
			echo '<option value="' . esc_attr( $day ) . '">' . esc_html( $wp_locale->weekday[ $day % 7 ] ) . '</option>';
		}
		echo '</select> <input type="time" class="p9od-window-start" aria-label="' . esc_attr__( 'Beginn des Zeitfensters', 'patsch9-orderdate' ) . '"> <input type="time" class="p9od-window-end" aria-label="' . esc_attr__( 'Ende des Zeitfensters', 'patsch9-orderdate' ) . '"> <button type="button" class="button p9od-window-add">' . esc_html__( 'Zeitfenster hinzufügen', 'patsch9-orderdate' ) . '</button></span></p>';
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
		echo '<div class="wrap woocommerce"><h1>' . esc_html__( 'Abholtermine', 'patsch9-orderdate' ) . '</h1><p>' . esc_html__( 'Aktiviere die Terminauswahl je Produkt unter Produktdaten → Abholtermine. Diese Einstellungen gelten für alle aktiven Produkte.', 'patsch9-orderdate' ) . '</p>';
		// A per-user transient avoids echoing untrusted query-string messages.
		$notice = get_transient( 'p9od_notice_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'p9od_notice_' . get_current_user_id() );
			echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="p9od_settings">';
		wp_nonce_field( 'p9od_settings', 'p9od_settings_nonce' );
		echo '<p><label for="p9od_horizon">' . esc_html__( 'Maximal im Voraus buchbar (Kalendertage, 1–730)', 'patsch9-orderdate' ) . '</label> <input type="number" required min="1" max="730" name="p9od_horizon" id="p9od_horizon" value="' . esc_attr( $settings['horizon'] ) . '"></p>';
		self::date_list( 'p9od_blocked', __( 'Globale Sperrtage / Betriebsferien', 'patsch9-orderdate' ), $settings['blocked'] );
		self::window_editor( 'p9od_time_windows', $settings['time_windows'] );
		echo '<p>' . esc_html__( 'Globale Zeitfenster gelten für Produkte mit „Globale Zeitfenster übernehmen“. Eine leere globale Liste lässt die reine Datumsauswahl bestehen. Bereits begonnene Zeitfenster am heutigen Tag sind nicht buchbar.', 'patsch9-orderdate' ) . '</p>';
		submit_button();
		echo '</form><p>' . esc_html__( 'Abholorte unter WooCommerce → Einstellungen → Versand konfigurieren. Offene Abholungen stehen im Abholmanager.', 'patsch9-orderdate' ) . '</p></div>';
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
		if ( $screen && in_array( $screen->id, array( 'product', 'woocommerce_page_p9od', 'woocommerce_page_p9od-pickups' ), true ) ) {
			wp_enqueue_style( 'p9od-admin', plugins_url( 'assets/admin.css', P9OD_FILE ), array( 'dashicons' ), P9OD_VERSION );
			wp_enqueue_script( 'p9od-admin', plugins_url( 'assets/admin.js', P9OD_FILE ), array(), P9OD_VERSION, true );
		}
	}
}

