<?php
/** Checkout Block registration for the offline gateway. @package Patsch9Orderdate */
namespace Patsch9\Orderdate;
defined( 'ABSPATH' ) || exit;

final class GatewayBlocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
	protected $name = 'p9od_pay_on_pickup';
	public function initialize() {
		$this->settings = get_option( 'woocommerce_p9od_pay_on_pickup_settings', array() );
	}
	public function is_active() {
		return 'yes' === $this->get_setting( 'enabled', 'no' );
	}
	public function get_payment_method_script_handles() {
		wp_register_script( 'p9od-payment', plugins_url( 'assets/payment-block.js', P9OD_FILE ), array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ), P9OD_VERSION, true );
		return array( 'p9od-payment' );
	}
	public function get_payment_method_data() {
		return array(
			'title' => $this->get_setting( 'title', __( 'Vor Ort zahlen', 'patsch9-orderdate' ) ),
			'description' => $this->get_setting( 'description', __( 'Bitte bezahle deine Bestellung bei der Abholung vor Ort.', 'patsch9-orderdate' ) ),
			'supports' => array( 'products' ),
		);
	}
}
