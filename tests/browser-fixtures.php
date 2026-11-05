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
// Independent product deadlines and variation notices (no pickup dates required).
$future_deadline = (string) ( time() + 2 * DAY_IN_SECONDS );
$past_deadline = (string) ( time() - HOUR_IN_SECONDS );
foreach ( array( 'future' => $future_deadline, 'expired' => $past_deadline, 'pending' => $future_deadline ) as $kind => $deadline ) {
	$deadline_product = new WC_Product_Simple();
	$deadline_product->set_name( 'Orderdate deadline fixture ' . $kind );
	$deadline_product->set_regular_price( '10' );
	$deadline_product->set_virtual( true );
	$deadline_product->set_status( 'publish' );
	$deadline_product->update_meta_data( \Patsch9\Orderdate\OrderDeadline::META, $deadline );
	if ( 'pending' === $kind ) {
		$deadline_product->update_meta_data( \Patsch9\Orderdate\OrderDeadline::META_FROM, (string) ( time() + HOUR_IN_SECONDS ) );
	}
	$deadline_product->save();
	$ids[ 'deadline_' . $kind ] = $deadline_product->get_id();
}
$deadline_attribute = new WC_Product_Attribute();
$deadline_attribute->set_name( 'Schedule' );
$deadline_attribute->set_options( array( 'Available', 'Expired', 'Pending' ) );
$deadline_attribute->set_position( 0 );
$deadline_attribute->set_visible( true );
$deadline_attribute->set_variation( true );
$deadline_parent = new WC_Product_Variable();
$deadline_parent->set_name( 'Orderdate deadline variation fixture' );
$deadline_parent->set_status( 'publish' );
$deadline_parent->set_attributes( array( $deadline_attribute ) );
$deadline_parent->save();
$ids['deadline_variable'] = $deadline_parent->get_id();
foreach ( array( 'Available' => $future_deadline, 'Expired' => $past_deadline, 'Pending' => $future_deadline ) as $choice => $deadline ) {
	$deadline_variation = new WC_Product_Variation();
	$deadline_variation->set_parent_id( $deadline_parent->get_id() );
	$deadline_variation->set_attributes( array( 'schedule' => $choice ) );
	$deadline_variation->set_regular_price( '10' );
	$deadline_variation->set_status( 'publish' );
	$deadline_variation->update_meta_data( \Patsch9\Orderdate\OrderDeadline::META, $deadline );
	if ( 'Pending' === $choice ) {
		$deadline_variation->update_meta_data( \Patsch9\Orderdate\OrderDeadline::META_FROM, (string) ( time() + HOUR_IN_SECONDS ) );
	}
	$deadline_variation->save();
}
WC_Product_Variable::sync( $deadline_parent->get_id() );

$ids['checkout_page'] = (int) get_option( 'woocommerce_checkout_page_id' );
wp_update_post( array( 'ID' => $ids['checkout_page'], 'post_content' => '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading"><!-- wp:woocommerce/checkout-fields-block --><div class="wp-block-woocommerce-checkout-fields-block"><!-- wp:woocommerce/checkout-contact-information-block /--><!-- wp:woocommerce/checkout-billing-address-block /--><!-- wp:woocommerce/checkout-payment-block /--><!-- wp:woocommerce/checkout-order-note-block /--><!-- wp:woocommerce/checkout-terms-block /--><!-- wp:woocommerce/checkout-actions-block /--></div><!-- /wp:woocommerce/checkout-fields-block --><!-- wp:woocommerce/checkout-totals-block --><div class="wp-block-woocommerce-checkout-totals-block"><!-- wp:woocommerce/checkout-order-summary-block /--></div><!-- /wp:woocommerce/checkout-totals-block --></div><!-- /wp:woocommerce/checkout -->' ) );
$ids['classic_page'] = wp_insert_post( array( 'post_title' => 'Classic Checkout', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[woocommerce_checkout]' ) );
$ids['date'] = $date;
WP_CLI::line( 'P9OD_FIXTURES:' . wp_json_encode( $ids ) );
