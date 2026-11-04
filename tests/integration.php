<?php
/** Execute with wp eval-file in a DISPOSABLE WooCommerce installation. */
use Patsch9\Orderdate\Admin;
use Patsch9\Orderdate\Blocks;
use Patsch9\Orderdate\Plugin;
use Patsch9\Orderdate\OrderDeadline;
use Patsch9\Orderdate\Rules;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( Plugin::class ) ) {
	throw new RuntimeException( 'Run in an activated disposable WP/WooCommerce installation.' );
}
$GLOBALS['p9od_test_checks'] = 0;
$GLOBALS['p9od_test_products'] = array();
$orders = array();
$old_settings = get_option( 'p9od_settings', null );
$old_timezone = get_option( 'timezone_string' );
$old_post = $_POST;
$old_user = get_current_user_id();
function p9od_assert( bool $pass, string $message ): void {
	++$GLOBALS['p9od_test_checks'];
	if ( ! $pass ) {
		throw new RuntimeException( $message );
	}
}
function p9od_fixture( string $mode, array $overrides = array() ): WC_Product_Simple {
	$product = new WC_Product_Simple();
	$product->set_name( 'Orderdate integration fixture' );
	$product->set_regular_price( '10' );
	$product->set_status( 'publish' );
	$product->update_meta_data( Plugin::META, Rules::normalize( array_merge( array( 'mode' => $mode, 'weekdays' => range( 1, 7 ) ), $overrides ), wp_timezone() ) );
	$product->save();
	$GLOBALS['p9od_test_products'][] = $product->get_id();
	return $product;
}
function p9od_rest( string $route, array $params ): mixed {
	$request = new WP_REST_Request( 'POST', '/wc/store/v1/' . $route );
	$request->set_header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
}
try {
	update_option( 'timezone_string', 'Europe/Berlin' );
	update_option( 'p9od_settings', array( 'horizon' => 30, 'blocked' => array() ) );
	wc_load_cart();
	WC()->cart->empty_cart();
	$first = current_datetime()->modify( '+2 days' )->format( 'Y-m-d' );
	$second = current_datetime()->modify( '+3 days' )->format( 'Y-m-d' );
	$product = p9od_fixture( 'product' );
	$checkout = p9od_fixture( 'checkout', array( 'schedule' => 'dates', 'dates' => array( $first ) ) );
	$off = p9od_fixture( 'off' );
	$_POST = array( 'p9od_date' => $first );
	p9od_assert( ! Plugin::validate_add( true, $product->get_id(), 1 ), 'Product date requires a valid nonce: ' . wp_json_encode( array( $product->get_id(), Plugin::rule( $product->get_id() ), $product->get_meta( Plugin::META, true ) ) ) );
	$_POST['p9od_nonce'] = wp_create_nonce( 'p9od_add_date' );
	p9od_assert( Plugin::validate_add( true, $product->get_id(), 1 ), 'Valid classic product form' );
	$one = WC()->cart->add_to_cart( $product->get_id(), 1 );
	$_POST['p9od_date'] = $second;
	$two = WC()->cart->add_to_cart( $product->get_id(), 1 );
	p9od_assert( $one !== $two && 2 === count( WC()->cart->get_cart() ), 'Distinct product dates create distinct cart lines' );
	p9od_assert( WC()->cart->get_cart()[ $one ][ Plugin::DATE ] === $first, 'First cart date preserved' );
	WC()->cart->add_to_cart( $checkout->get_id(), 1 );
	WC()->cart->add_to_cart( $off->get_id(), 1 );
	p9od_assert( array( $first ) === Plugin::dates( Plugin::checkout_rules() ), 'Checkout ignores product-specific and disabled date modes' );
	p9od_assert( ! Plugin::errors( $first, true ), 'Mixed cart with valid common date passes' );
	p9od_assert( (bool) Plugin::errors( $second, true ), 'Invalid common date rejected' );
	$_POST['p9od_checkout_date'] = $second;
	$errors = new WP_Error();
	do_action( 'woocommerce_after_checkout_validation', array(), $errors );
	p9od_assert( $errors->has_errors(), 'Classic validation hook blocks invalid checkout' );
	$_POST['p9od_checkout_date'] = $first;
	$errors = new WP_Error();
	do_action( 'woocommerce_after_checkout_validation', array(), $errors );
	p9od_assert( ! $errors->has_errors(), 'Classic validation accepts valid common date' );
	$order = wc_create_order();
	$orders[] = $order->get_id();
	WC()->checkout()->create_order_line_items( $order, WC()->cart );
	do_action( 'woocommerce_checkout_create_order', $order, array() );
	$order->save();
	$loaded = wc_get_order( $order->get_id() );
	p9od_assert( $first === $loaded->get_meta( Plugin::DATE, true ), 'Order date survives CRUD reload' );
	$line_dates = array();
	foreach ( $loaded->get_items() as $item ) {
		$line_dates[] = $item->get_meta( Plugin::DATE, true );
	}
	p9od_assert( array( $first, $second, $first, '' ) === $line_dates, 'Correct dates on mixed order lines: ' . wp_json_encode( $line_dates ) );
	$all_items = $loaded->get_items();
	$item = reset( $all_items );
	$formatted = $item->get_formatted_meta_data();
	p9od_assert( ! empty( $formatted['p9od']->display_value ), 'Protected line date appears in standard Woo email/order metadata' );
	p9od_assert( ! empty( Plugin::email_date( array(), false, $loaded )['p9od'] ), 'Order date appears in email metadata' );
	$blocked_settings = Plugin::settings();
	$blocked_settings['blocked'] = array( $first );
	update_option( 'p9od_settings', $blocked_settings );
	p9od_assert( (bool) Plugin::errors( $first, true ), 'Rule changes invalidate existing cart dates' );
	update_option( 'p9od_settings', array( 'horizon' => 30, 'blocked' => array() ) );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $checkout->get_id(), 1 );
	$other = p9od_fixture( 'checkout', array( 'schedule' => 'dates', 'dates' => array( $second ) ) );
	WC()->cart->add_to_cart( $other->get_id(), 1 );
	p9od_assert( array() === Plugin::dates( Plugin::checkout_rules() ) && (bool) Plugin::errors(), 'Disjoint checkout products fail explicitly' );
	WC()->cart->empty_cart();
	wc_clear_notices();
	$_POST = array();
	$invalid = p9od_rest( 'cart/add-item', array( 'id' => $product->get_id(), 'quantity' => 1 ) );
	p9od_assert( $invalid->get_status() >= 400, 'Real Store API cannot add product without a date' );
	$valid = p9od_rest( 'cart/add-item', array( 'id' => $product->get_id(), 'quantity' => 1, 'extensions' => array( Blocks::NS => array( 'date' => $first ) ) ) );
	p9od_assert( in_array( $valid->get_status(), array( 200, 201 ), true ), 'Real Store API accepts a valid product date: ' . wp_json_encode( $valid->get_data() ) );
	p9od_assert( ! Plugin::errors(), 'API cart line contains validated date' );
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $checkout->get_id(), 1 );
	$request = new WP_REST_Request( 'POST' );
	$request->set_param( 'extensions', array( Blocks::NS => array( 'date' => $second ) ) );
	try {
		do_action( 'woocommerce_store_api_checkout_update_order_from_request', $order, $request );
		p9od_assert( false, 'Invalid Store API checkout date accepted' );
	} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $exception ) {
		p9od_assert( true, 'Store API checkout rejects invalid date' );
	}
	$request->set_param( 'extensions', array( Blocks::NS => array( 'date' => $first ) ) );
	do_action( 'woocommerce_store_api_checkout_update_order_from_request', $order, $request );
	p9od_assert( $first === wc_get_order( $order->get_id() )->get_meta( Plugin::DATE, true ), 'Store API saves current common date' );
	WC()->cart->empty_cart();
	$request->set_param( 'extensions', array() );
	Blocks::checkout( $order, $request );
	p9od_assert( '' === wc_get_order( $order->get_id() )->get_meta( Plugin::DATE, true ), 'Draft retry clears obsolete order date' );
	// Global defaults apply to existing products without rewriting their metadata.
	update_option( 'p9od_settings', array( 'horizon' => 30, 'blocked' => array(), 'time_windows' => array( '*' => array( '13:00-15:00', '15:00-16:00' ) ) ) );
	p9od_assert( 'custom' === Plugin::rule( $product->get_id() )['time_mode'] && 'inherit' === Plugin::raw_rule( $product->get_id() )['time_mode'], 'Global schedule resolves without changing saved inheritance' );
	$_POST = array( 'p9od_nonce' => wp_create_nonce( 'p9od_add_date' ), 'p9od_date' => $first );
	p9od_assert( ! Plugin::validate_add( true, $product->get_id(), 1 ), 'Product time cannot be omitted' );
	$_POST['p9od_time'] = '13:00-15:00';
	p9od_assert( Plugin::validate_add( true, $product->get_id(), 1 ), 'Product date and time accepted' );
	$early = WC()->cart->add_to_cart( $product->get_id(), 1 );
	$_POST['p9od_time'] = '15:00-16:00';
	$late = WC()->cart->add_to_cart( $product->get_id(), 1 );
	p9od_assert( $early !== $late, 'Same product and date with distinct windows remain separate lines' );
	$timed_checkout = p9od_fixture( 'checkout', array( 'time_mode' => 'custom', 'time_windows' => array( '*' => array( '14:00-16:00' ) ) ) );
	WC()->cart->add_to_cart( $timed_checkout->get_id(), 1 );
	WC()->cart->add_to_cart( $checkout->get_id(), 1 );
	p9od_assert( array( '14:00-15:00', '15:00-16:00' ) === Plugin::windows( $first, Plugin::checkout_rules() ), 'Checkout intersects differently configured windows' );
	p9od_assert( (bool) Plugin::errors( $first, true ) && (bool) Plugin::errors( $first, true, '13:00-15:00' ), 'Missing and forged common windows are rejected' );
	p9od_assert( ! Plugin::errors( $first, true, '14:00-15:00' ), 'Valid common interval is accepted even when not a literal product window' );
	$_POST['p9od_checkout_date'] = $first;
	$_POST['p9od_checkout_time'] = '14:00-15:00';
	$timed_order = wc_create_order();
	$orders[] = $timed_order->get_id();
	WC()->checkout()->create_order_line_items( $timed_order, WC()->cart );
	Plugin::create_order( $timed_order, array() );
	$timed_order->save();
	$timed_loaded = wc_get_order( $timed_order->get_id() );
	p9od_assert( '14:00-15:00' === $timed_loaded->get_meta( Plugin::TIME, true ), 'Common window survives order CRUD reload' );
	$times = array();
	foreach ( $timed_loaded->get_items() as $timed_item ) {
		$times[] = $timed_item->get_meta( Plugin::TIME, true );
		p9od_assert( isset( $timed_item->get_formatted_meta_data()['p9od_time'] ), 'Item windows appear in standard order and email tables' );
	}
	p9od_assert( array( '13:00-15:00', '15:00-16:00', '14:00-15:00', '14:00-15:00' ) === $times, 'Mixed cart preserves position windows and common window' );
	p9od_assert( isset( Plugin::email_date( array(), false, $timed_loaded )['p9od_time'] ), 'Common window appears in email metadata' );
	$time_request = new WP_REST_Request( 'POST' );
	$time_request->set_param( 'extensions', array( Blocks::NS => array( 'date' => $first, 'time' => '14:00-15:00' ) ) );
	Blocks::checkout( $timed_order, $time_request );
	p9od_assert( '14:00-15:00' === wc_get_order( $timed_order->get_id() )->get_meta( Plugin::TIME, true ), 'Store API checkout persists a valid window' );
	$time_request->set_param( 'extensions', array( Blocks::NS => array( 'date' => $first, 'time' => array( '14:00-15:00' ) ) ) );
	try {
		Blocks::checkout( $timed_order, $time_request );
		p9od_assert( false, 'Malformed Store API time accepted' );
	} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $exception ) {
		p9od_assert( true, 'Malformed Store API time rejected' );
	}
	WC()->cart->empty_cart();
	$time_request->set_param( 'extensions', array() );
	Blocks::checkout( $timed_order, $time_request );
	p9od_assert( '' === wc_get_order( $timed_order->get_id() )->get_meta( Plugin::TIME, true ), 'Draft retry clears obsolete order window' );
	wc_clear_notices();
	$_POST = array();
	$missing_time = p9od_rest( 'cart/add-item', array( 'id' => $product->get_id(), 'quantity' => 1, 'extensions' => array( Blocks::NS => array( 'date' => $first ) ) ) );
	p9od_assert( $missing_time->get_status() >= 400, 'Real Store API cannot omit a required product window' );
	$with_time = p9od_rest( 'cart/add-item', array( 'id' => $product->get_id(), 'quantity' => 1, 'extensions' => array( Blocks::NS => array( 'date' => $first, 'time' => '13:00-15:00' ) ) ) );
	p9od_assert( in_array( $with_time->get_status(), array( 200, 201 ), true ), 'Real Store API accepts valid date and window' );
	$changed = Plugin::settings();
	$changed['time_windows'] = array( '*' => array( '15:00-16:00' ) );
	update_option( 'p9od_settings', $changed );
	p9od_assert( (bool) Plugin::errors(), 'Changed schedule invalidates a stale cart window' );
	WC()->cart->empty_cart();
	update_option( 'p9od_settings', array( 'horizon' => 30, 'blocked' => array() ) );
	$before = $product->get_meta( Plugin::META, true );
	wp_set_current_user( 0 );
	$_POST = array( 'p9od_admin_nonce' => wp_create_nonce( 'p9od_save_product' ), 'p9od_mode' => 'off' );
	Admin::save_product( $product );
	p9od_assert( $before === $product->get_meta( Plugin::META, true ), 'Product rules require edit permission even with valid nonce' );
	$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
	wp_set_current_user( $admin->ID );
	$_POST = array( 'p9od_admin_nonce' => 'invalid', 'p9od_mode' => 'off' );
	Admin::save_product( $product );
	p9od_assert( $before === $product->get_meta( Plugin::META, true ), 'Product rules require nonce even with edit permission' );
	$parent = new WC_Product_Variable();
	$parent->set_name( 'Orderdate variation parent' );
	$parent->update_meta_data( Plugin::META, $before );
	$parent->save();
	$GLOBALS['p9od_test_products'][] = $parent->get_id();
	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $parent->get_id() );
	$variation->set_regular_price( '10' );
	$variation->save();
	$GLOBALS['p9od_test_products'][] = $variation->get_id();
	p9od_assert( 'product' === Plugin::rule( $variation->get_id() )['mode'], 'Variation inherits parent rules' );
	// Absolute order deadlines are independent of pickup rules or shipping methods.
	$future_deadline = current_datetime()->modify( '+1 day' )->format( 'Y-m-d\TH:i' );
	$past_deadline = current_datetime()->modify( '-1 day' )->format( 'Y-m-d\TH:i' );
	$_POST = array( 'p9od_order_deadline_nonce' => 'invalid', 'p9od_order_deadline' => $future_deadline );
	OrderDeadline::save_simple( $off );
	p9od_assert( '' === $off->get_meta( OrderDeadline::META, true ), 'Simple deadline requires a valid nonce' );
	$_POST['p9od_order_deadline_nonce'] = wp_create_nonce( 'p9od_save_order_deadline' );
	wp_set_current_user( 0 );
	OrderDeadline::save_simple( $off );
	p9od_assert( '' === $off->get_meta( OrderDeadline::META, true ), 'Simple deadline requires edit permission' );
	wp_set_current_user( $admin->ID );
	OrderDeadline::save_simple( $off );
	$off->save();
	$saved_deadline = OrderDeadline::parse_local( $future_deadline, wp_timezone() );
	p9od_assert( (string) $saved_deadline === (string) wc_get_product( $off->get_id() )->get_meta( OrderDeadline::META, true ), 'Simple deadline persists as UTC timestamp' );
	p9od_assert( ! OrderDeadline::expired( wc_get_product( $off->get_id() ) ), 'Future deadline permits sales' );
	p9od_assert( str_contains( OrderDeadline::notice_text( $off ), wp_date( get_option( 'date_format' ) . ' H:i', (int) $saved_deadline, wp_timezone() ) ), 'Future deadline notice contains shop-local date and time' );
	p9od_assert( str_contains( OrderDeadline::notice_html( $off ), 'p9od-order-deadline' ), 'Simple product gets a frontend deadline notice' );
	$_POST['p9od_order_deadline'] = '2027-02-30T12:00';
	OrderDeadline::save_simple( $off );
	p9od_assert( (string) $saved_deadline === (string) $off->get_meta( OrderDeadline::META, true ), 'Invalid simple date cannot replace valid deadline' );
	$off->update_meta_data( OrderDeadline::META, (string) ( time() - 60 ) );
	$off->save();
	p9od_assert( OrderDeadline::expired( wc_get_product( $off->get_id() ) ), 'Past deadline closes simple product without pickup rules' );
	p9od_assert( str_contains( OrderDeadline::notice_html( $off ), 'p9od-order-deadline--expired' ) && str_contains( OrderDeadline::notice_text( $off ), 'nicht mehr bestellt' ), 'Expired simple product clearly explains unavailability' );
	p9od_assert( ! OrderDeadline::purchasable( true, wc_get_product( $off->get_id() ) ), 'Expired product is not purchasable' );
	wc_clear_notices();
	p9od_assert( ! apply_filters( 'woocommerce_add_to_cart_validation', true, $off->get_id(), 1, 0 ), 'Classic add-to-cart cannot bypass simple deadline' );
	$_POST = array();
	$expired_add = p9od_rest( 'cart/add-item', array( 'id' => $off->get_id(), 'quantity' => 1 ) );
	p9od_assert( $expired_add->get_status() >= 400, 'Store API cannot add an expired simple product' );
	WC()->cart->empty_cart();
	$off->update_meta_data( OrderDeadline::META, (string) ( time() + DAY_IN_SECONDS ) );
	$off->save();
	wc_clear_notices();
	p9od_assert( (bool) WC()->cart->add_to_cart( $off->get_id(), 1 ), 'Product can be added before its deadline' );
	$off->update_meta_data( OrderDeadline::META, (string) ( time() - 60 ) );
	$off->save();
	p9od_assert( (bool) Plugin::errors(), 'Cart item is revalidated when deadline passes after adding' );
	$deadline_cart_errors = new WP_Error();
	do_action( 'woocommerce_store_api_cart_errors', $deadline_cart_errors );
	p9od_assert( $deadline_cart_errors->has_errors(), 'Store API cart errors include newly expired items' );
	$quantity_result = apply_filters( 'woocommerce_store_api_cart_item_quantity_validation', true, 2, wc_get_product( $off->get_id() ), array() );
	p9od_assert( is_wp_error( $quantity_result ), 'Store API quantity updates reject expired items' );
	$deadline_order = wc_create_order();
	$orders[] = $deadline_order->get_id();
	try {
		Plugin::create_order( $deadline_order, array() );
		p9od_assert( false, 'Classic checkout accepted a stale expired cart item' );
	} catch ( Exception $exception ) {
		p9od_assert( true, 'Classic checkout rejects expired cart items' );
	}
	try {
		Blocks::checkout( $deadline_order, new WP_REST_Request( 'POST' ) );
		p9od_assert( false, 'Store API checkout accepted a stale expired cart item' );
	} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $exception ) {
		p9od_assert( true, 'Store API checkout rejects expired cart items' );
	}
	WC()->cart->empty_cart();
	wc_clear_notices();
	// Variations have separate deadlines; one expired option must not close another.
	$variation->update_meta_data( OrderDeadline::META, (string) ( time() + DAY_IN_SECONDS ) );
	$variation->save();
	$expired_variation = new WC_Product_Variation();
	$expired_variation->set_parent_id( $parent->get_id() );
	$expired_variation->set_regular_price( '10' );
	$expired_variation->update_meta_data( OrderDeadline::META, (string) ( time() - 60 ) );
	$expired_variation->save();
	$GLOBALS['p9od_test_products'][] = $expired_variation->get_id();
	p9od_assert( ! OrderDeadline::expired( wc_get_product( $variation->get_id() ) ), 'Valid variation remains open' );
	p9od_assert( OrderDeadline::expired( wc_get_product( $expired_variation->get_id() ) ), 'Other variation closes independently' );
	p9od_assert( OrderDeadline::purchasable( true, $variation ) && ! OrderDeadline::purchasable( true, $expired_variation ), 'Expired variation is not purchasable while another remains available' );
	p9od_assert( ! has_filter( 'woocommerce_variation_is_active', array( OrderDeadline::class, 'variation_active' ) ), 'Deadline does not hide variation options before users can read the reason' );
	p9od_assert( str_contains( OrderDeadline::variation_data( array( 'availability_html' => '' ), $parent, $variation )['availability_html'], 'Bestellbar bis:' ), 'Future variation shows deadline in native WooCommerce availability' );
	p9od_assert( str_contains( OrderDeadline::variation_data( array( 'availability_html' => '' ), $parent, $expired_variation )['availability_html'], 'p9od-order-deadline--expired' ), 'Expired variation shows reason when selected' );
	$_POST = array( 'p9od_variation_deadline_nonce' => array( 0 => 'invalid' ), 'p9od_variation_order_deadline' => array( 0 => $past_deadline ) );
	OrderDeadline::save_variation( $variation, 0 );
	p9od_assert( ! OrderDeadline::expired( $variation ), 'Invalid variation nonce cannot change deadline' );
	$_POST['p9od_variation_deadline_nonce'][0] = wp_create_nonce( 'p9od_save_variation_deadline_' . $variation->get_id() );
	OrderDeadline::save_variation( $variation, 0 );
	$variation->save();
	p9od_assert( OrderDeadline::expired( wc_get_product( $variation->get_id() ) ), 'Variation editor saves its own absolute deadline' );
	p9od_assert( ! OrderDeadline::validate_add( true, $parent->get_id(), 1, $variation->get_id() ), 'Classic add-to-cart checks selected variation ID' );
	p9od_assert( ! OrderDeadline::expired( $parent ), 'Variable parent itself has no absolute deadline' );
	$_POST['p9od_variation_order_deadline'][0] = '';
	OrderDeadline::save_variation( $variation, 0 );
	$variation->save();
	p9od_assert( '' === wc_get_product( $variation->get_id() )->get_meta( OrderDeadline::META, true ), 'Clearing the variation deadline restores unlimited availability' );
	p9od_assert( '' === OrderDeadline::notice_text( $variation ) && '' === OrderDeadline::notice_html( $variation ), 'Variations without deadlines show no notice' );
	$_POST = array();
	$block = parse_blocks( '<!-- wp:woocommerce/checkout-fields-block --><div></div><!-- /wp:woocommerce/checkout-fields-block -->' )[0];
	$once = Blocks::insert_block( $block );
	$twice = Blocks::insert_block( $once );
	p9od_assert( 1 === count( $twice['innerBlocks'] ), 'Block is inserted exactly once' );
	$actions = parse_blocks( '<!-- wp:woocommerce/checkout-fields-block --><div><!-- wp:woocommerce/checkout-actions-block /--></div><!-- /wp:woocommerce/checkout-fields-block -->' )[0];
	$ordered = Blocks::insert_block( $actions );
	p9od_assert( Blocks::NS . '/pickup-date' === $ordered['innerBlocks'][0]['blockName'], 'Injected date field precedes checkout actions' );
	p9od_assert( str_contains( render_block( $twice ), 'data-block-name="patsch9-orderdate/pickup-date"' ), 'Server-rendered checkout includes block mount point' );
	WP_CLI::success( $GLOBALS['p9od_test_checks'] . ' integration assertions passed (' . get_bloginfo( 'version' ) . ' / WC ' . WC_VERSION . ').' );
} finally {
	$_POST = $old_post;
	wp_set_current_user( $old_user );
	WC()->cart->empty_cart();
	wc_clear_notices();
	foreach ( $orders as $id ) {
		wc_get_order( $id )?->delete( true );
	}
	foreach ( array_reverse( $GLOBALS['p9od_test_products'] ) as $id ) {
		wc_get_product( $id )?->delete( true );
	}
	null === $old_settings ? delete_option( 'p9od_settings' ) : update_option( 'p9od_settings', $old_settings );
	update_option( 'timezone_string', $old_timezone );
}
