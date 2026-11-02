<?php
/**
 * Plugin Name: Patsch9 Orderdate for WooCommerce
 * Plugin URI: https://github.com/patsch9/patsch9-orderdate
 * Description: Produktbezogene Abholtermine mit Wochentagen, Einzelterminen und Auswahl am Produkt oder an der Kasse.
 * Version: 2026.11.1-beta2
 * Requires at least: 6.9.5
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 10.9.4
 * Author: Patrick Schmidt
 * Author URI: https://github.com/patsch9
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: patsch9-orderdate
 *
 * @package Patsch9Orderdate
 */

defined( 'ABSPATH' ) || exit;

define( 'P9OD_VERSION', '2026.11.1-beta2' );
define( 'P9OD_FILE', __FILE__ );

add_action( 'before_woocommerce_init', static function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', static function () {
	if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '10.9.4', '<' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Patsch9 Orderdate benötigt WooCommerce 10.9.4 oder neuer.', 'patsch9-orderdate' ) . '</p></div>';
		} );
		return;
	}
	require_once __DIR__ . '/includes/class-rules.php';
	require_once __DIR__ . '/includes/class-plugin.php';
	require_once __DIR__ . '/includes/class-admin.php';
	require_once __DIR__ . '/includes/class-blocks.php';
	\Patsch9\Orderdate\Plugin::boot();
	\Patsch9\Orderdate\Admin::boot();
	\Patsch9\Orderdate\Blocks::boot();
} );
