<?php
/** Offline payment at collection; never records payment on checkout. @package Patsch9Orderdate */
namespace Patsch9\Orderdate;
defined( 'ABSPATH' ) || exit;

final class Gateway extends \WC_Payment_Gateway {
	public function __construct() {
		$this->id = 'p9od_pay_on_pickup';
		$this->method_title = __( 'Vor Ort zahlen', 'patsch9-orderdate' );
		$this->method_description = __( 'Zahlung bei Abholung. Der Zahlungseingang wird bei der Übergabe bestätigt.', 'patsch9-orderdate' );
		$this->has_fields = false;
		$this->supports = array( 'products' );
		$this->form_fields = array(
			'enabled' => array( 'title' => __( 'Aktivieren', 'patsch9-orderdate' ), 'type' => 'checkbox', 'label' => __( 'Vor Ort zahlen anbieten', 'patsch9-orderdate' ), 'default' => 'no' ),
			'title' => array( 'title' => __( 'Titel', 'patsch9-orderdate' ), 'type' => 'text', 'default' => __( 'Vor Ort zahlen', 'patsch9-orderdate' ) ),
			'description' => array( 'title' => __( 'Beschreibung', 'patsch9-orderdate' ), 'type' => 'textarea', 'default' => __( 'Bitte bezahle deine Bestellung bei der Abholung vor Ort.', 'patsch9-orderdate' ) ),
		);
		$this->init_settings();
		$this->enabled = $this->get_option( 'enabled', 'no' );
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}
		if ( is_checkout_pay_page() ) {
			$order = wc_get_order( absint( get_query_var( 'order-pay' ) ) );
			return $order && PickupLocations::order_eligible( $order ) && PayOnPickup::order_allowed( $order );
		}
		return PickupLocations::cart_eligible() && PayOnPickup::cart_allowed();
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! PickupLocations::order_eligible( $order ) || ! PayOnPickup::order_allowed( $order ) || $order->is_paid() ) {
			throw new \Exception( esc_html__( 'Vor Ort zahlen ist nur für unbezahlte Abholbestellungen mit dafür freigegebenen Produkten verfügbar.', 'patsch9-orderdate' ) );
		}
		if ( ! $order->has_status( 'on-hold' ) ) {
			$order->update_status( 'on-hold', __( 'Zahlung bei Abholung ausstehend.', 'patsch9-orderdate' ) );
		}
		wc_reduce_stock_levels( $order_id );
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}
		return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
	}
}
