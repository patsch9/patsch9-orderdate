<?php
/** Disposable wp-env fixtures for the real browser checkout regression. */
use Patsch9\Orderdate\Plugin;
use Patsch9\Orderdate\Rules;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}
update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_default_country', 'DE' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_coming_soon', 'no' );
WC_Install::create_pages();
$date = current_datetime()->modify( '+2 days' )->format( 'Y-m-d' );
update_option( 'p9od_settings', array( 'horizon' => 30, 'blocked' => array(), 'time_windows' => array( '*' => array( '13:00-15:00' ) ) ) );
$ids = array();
foreach ( array( 'product', 'checkout', 'checkout_other' ) as $mode ) {
	$product = new WC_Product_Simple();
	$product->set_name( 'Orderdate browser fixture ' . $mode );
	$product->set_regular_price( '0' );
	$product->set_virtual( true );
	$product->set_status( 'publish' );
	$rule = array( 'mode' => 'product' === $mode ? 'product' : 'checkout', 'schedule' => 'dates', 'dates' => array( $date ) );
	if ( 'checkout' === $mode ) {
		$rule['time_mode'] = 'custom';
		$rule['time_windows'] = array( '*' => array( '14:00-16:00' ) );
	}
	$product->update_meta_data( Plugin::META, Rules::normalize( $rule, wp_timezone() ) );
	$product->save();
	$ids[ $mode ] = $product->get_id();
}
$ids['checkout_page'] = (int) get_option( 'woocommerce_checkout_page_id' );
wp_update_post( array( 'ID' => $ids['checkout_page'], 'post_content' => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading"><!-- wp:woocommerce/checkout-fields-block --><div class="wp-block-woocommerce-checkout-fields-block"><!-- wp:woocommerce/checkout-contact-information-block /--><!-- wp:woocommerce/checkout-billing-address-block /--><!-- wp:woocommerce/checkout-payment-block /--><!-- wp:woocommerce/checkout-order-note-block /--><!-- wp:woocommerce/checkout-terms-block /--><!-- wp:woocommerce/checkout-actions-block /--></div><!-- /wp:woocommerce/checkout-fields-block --><!-- wp:woocommerce/checkout-totals-block --><div class="wp-block-woocommerce-checkout-totals-block"><!-- wp:woocommerce/checkout-order-summary-block /--></div><!-- /wp:woocommerce/checkout-totals-block --></div><!-- /wp:woocommerce/checkout -->' ) );
$ids['classic_page'] = wp_insert_post( array( 'post_title' => 'Classic Checkout', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[woocommerce_checkout]' ) );
$ids['date'] = $date;
WP_CLI::line( 'P9OD_FIXTURES:' . wp_json_encode( $ids ) );
