<?php
/** Run only with wp eval-file in a disposable WooCommerce installation. */
use Patsch9\Orderdate\Gateway;
use Patsch9\Orderdate\PayOnPickup;
use Patsch9\Orderdate\PickupLocations;
use Patsch9\Orderdate\PickupManager;
use Patsch9\Orderdate\Plugin;
use Patsch9\Orderdate\Rules;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'Disposable WP-CLI installation required.' );
}
$checks = 0;
$orders = array();
$products = array();
$term_ids = array();
$messages = array();
$options = array();
foreach ( array( 'timezone_string', 'woocommerce_manage_stock', 'woocommerce_p9od_pay_on_pickup_settings', 'pickup_location_pickup_locations', 'p9od_settings' ) as $key ) {
	$options[ $key ] = get_option( $key, null );
}
$intercept = static function ( $result, $attributes ) use ( &$messages ) {
	$messages[] = $attributes;
	return true; // Never deliver test messages.
};
add_filter( 'pre_wp_mail', $intercept, 10, 2 );
$assert = static function ( $condition, $message ) use ( &$checks ) {
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};
$rejects = static function ( $fn, $message ) use ( $assert ) {
	$rejected = false;
	try { $fn(); } catch ( Exception $error ) { $rejected = true; }
	$assert( $rejected, $message );
};
try {
	update_option( 'timezone_string', 'Europe/Berlin' );
	update_option( 'p9od_settings', array( 'horizon' => 30, 'blocked' => array(), 'time_windows' => array() ) );
	update_option( 'woocommerce_manage_stock', 'yes' );
	$product = new WC_Product_Simple();
	$product->set_name( 'Pickup integration fixture' );
	$product->set_regular_price( 10 );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 10 );
	$product->update_meta_data( Plugin::META, Rules::normalize( array( 'mode' => 'product', 'weekdays' => range( 1, 7 ), 'time_mode' => 'custom', 'time_windows' => array( '*' => array( '13:00-14:00' ) ) ), wp_timezone() ) );
	$product->save();
	$products[] = $product;
	$assert( ! PayOnPickup::product_allowed( $product->get_id() ), 'Products are denied for pickup payment until explicitly enabled.' );
	$parent = wp_insert_term( 'P9OD payment parent', 'product_cat' );
	if ( is_wp_error( $parent ) ) {
		throw new RuntimeException( $parent->get_error_message() );
	}
	$term_ids[] = (int) $parent['term_id'];
	$child = wp_insert_term( 'P9OD payment child', 'product_cat', array( 'parent' => (int) $parent['term_id'] ) );
	if ( is_wp_error( $child ) ) {
		throw new RuntimeException( $child->get_error_message() );
	}
	$term_ids[] = (int) $child['term_id'];
	update_term_meta( (int) $parent['term_id'], PayOnPickup::TERM_META, 'yes' );
	wp_set_object_terms( $product->get_id(), array( (int) $child['term_id'] ), 'product_cat' );
	$assert( PayOnPickup::product_allowed( $product->get_id() ), 'A parent product category can enable pickup payment for child-category products.' );
	$product->update_meta_data( PayOnPickup::PRODUCT_META, 'no' );
	$product->save();
	$assert( ! PayOnPickup::product_allowed( $product->get_id() ), 'An explicit product denial overrides an enabled category.' );
	$product->update_meta_data( PayOnPickup::PRODUCT_META, 'yes' );
	$product->save();
	$assert( PayOnPickup::product_allowed( $product->get_id() ), 'An explicit product allow enables pickup payment.' );
	$date = current_datetime()->modify( '+3 days' )->format( 'Y-m-d' );
	$new_date = current_datetime()->modify( '+4 days' )->format( 'Y-m-d' );
	$order = wc_create_order();
	$orders[] = $order;
	$order->set_billing_email( 'customer@example.test' );
	$order->set_billing_first_name( 'Pickup' );
	$order->set_payment_method( 'p9od_pay_on_pickup' );
	$item_id = $order->add_product( $product, 1 );
	$second_id = $order->add_product( $product, 1 );
	foreach ( $order->get_items() as $item ) {
		$item->update_meta_data( Plugin::DATE, $date );
		$item->update_meta_data( Plugin::TIME, '13:00-14:00' );
		$item->save();
	}
	$order->update_meta_data( Plugin::DATE, $date );
	$shipping = new WC_Order_Item_Shipping();
	$shipping->set_method_id( 'pickup_location' );
	$shipping->set_method_title( 'Abholung' );
	$shipping->set_instance_id( 0 );
	$shipping->add_meta_data( 'pickup_location', 'Filiale A' );
	$shipping->add_meta_data( 'pickup_address', 'Teststraße 1, Kassel' );
	$order->add_item( $shipping );
	$order->calculate_totals();
	$order->save();
	$assert( PayOnPickup::order_allowed( $order ), 'Orders are eligible only when every line item allows pickup payment.' );
	$assert( isset( WC()->payment_gateways()->payment_gateways()['p9od_pay_on_pickup'] ), 'Gateway registered in WooCommerce.' );
	$gateway = new Gateway();
	$assert( 'no' === $gateway->enabled, 'Payment gateway disabled until explicitly enabled.' );
	$gateway->process_payment( $order->get_id() );
	$order = wc_get_order( $order->get_id() );
	$assert( $order->has_status( 'on-hold' ) && ! $order->get_date_paid() && ! $order->is_paid(), 'Offline checkout never marks the order paid.' );
	$assert( 8 === wc_get_product( $product->get_id() )->get_stock_quantity(), 'Stock reduced once for both items.' );
	$gateway->process_payment( $order->get_id() );
	$assert( 8 === wc_get_product( $product->get_id() )->get_stock_quantity(), 'Payment retry does not reduce stock twice.' );
	$assert( array( 'Filiale A · Teststraße 1, Kassel' ) === PickupLocations::order_locations( $order ), 'Native shipping location snapshot reused.' );
	update_option( 'pickup_location_pickup_locations', array( array( 'name' => 'Replacement location' ) ) );
	$assert( str_contains( implode( ', ', PickupLocations::order_locations( $order ) ), 'Filiale A' ), 'Historical pickup address survives location edits.' );
	$non_pickup = new WC_Order_Item_Shipping();
	$non_pickup->set_method_id( 'flat_rate' );
	$order->add_item( $non_pickup );
	$order->save();
	$assert( ! PickupLocations::order_eligible( $order ), 'Mixed delivery and pickup packages cannot use offline pickup payment.' );
	$rejects( static fn() => $gateway->process_payment( $order->get_id() ), 'Server rejects shipped orders even if client submits the offline gateway.' );
	$order->remove_item( $non_pickup->get_id() );
	$order->save();
	$assert( 'yes' === $order->get_meta( PickupManager::INDEX, true ), 'Orders with pickup items are indexed.' );
	$query = wc_get_orders( array( 'p9od_managed' => true, 'meta_query' => array( array( 'key' => PickupManager::INDEX, 'value' => 'yes' ) ), 'return' => 'ids', 'limit' => 100 ) );
	$assert( in_array( $order->get_id(), $query, true ), 'Pickup manager query works with the active order data store.' );
	$ordinary = wc_create_order();
	$orders[] = $ordinary;
	$query = wc_get_orders( array( 'p9od_managed' => true, 'meta_query' => array( array( 'key' => PickupManager::INDEX, 'value' => 'yes' ) ), 'return' => 'ids', 'limit' => 100 ) );
	$assert( ! in_array( $ordinary->get_id(), $query, true ), 'Undated non-pickup orders do not leak into the pickup manager.' );
	$refund = new WC_Order_Refund();
	$refund->set_parent_id( $ordinary->get_id() );
	$refund->set_amount( 0 );
	$refund->save();
	$orders[] = $refund;
	$assert( $refund->get_id() > 0 && ! $refund->get_meta( PickupManager::INDEX, true ), 'Generic order-save indexing leaves refunds unaffected.' );
	$old_version = PickupManager::version( $order );
	$rejects( static fn() => PickupManager::propose( $order, 99999999, $new_date, '13:00-14:00' ), 'Foreign item IDs are rejected.' );
	$rejects( static fn() => PickupManager::propose( $order, $item_id, $new_date, '17:00-18:00' ), 'Unavailable time windows are rejected.' );
	$token = PickupManager::locked( $order->get_id(), static fn( $fresh ) => PickupManager::propose( $fresh, $item_id, $new_date, '13:00-14:00' ) );
	$order = wc_get_order( $order->get_id() );
	$assert( $old_version !== PickupManager::version( $order ), 'An old admin form cannot overwrite a proposal.' );
	$assert( $date === $order->get_item( $item_id )->get_meta( Plugin::DATE, true ), 'Original schedule remains binding until confirmed.' );
	$p = $order->get_meta( PickupManager::PROPOSAL, true );
	$assert( ! str_contains( wp_json_encode( $p ), $token ) && PickupManager::valid_token( $order, $token ), 'Only a hash of the secret token is persisted.' );
	$assert( ! PickupManager::valid_token( $order, str_repeat( 'a', 64 ) ), 'Wrong tokens are rejected.' );
	$emails = WC()->mailer()->get_emails();
	$assert( $emails['p9od_rescheduled']->trigger( $order->get_id(), $token ), 'Schedule proposal email reports successful handoff.' );
	$assert( str_contains( end( $messages )['message'], 'p9od_confirm' ), 'Proposal email contains the protected confirmation link.' );
	$pending = clone $order;
	$rejects( static fn() => PickupManager::assert_status( $pending, PickupManager::READY ), 'Unconfirmed schedule blocks ready status before order mutation.' );
	$pending->set_status( PickupManager::READY );
	$request = new WP_REST_Request( 'PUT', '/wc/v3/orders/' . $order->get_id() );
	$request->set_param( 'status', PickupManager::READY );
	$assert( is_wp_error( PickupManager::guard_rest_status( $pending, $request ) ), 'REST order updates enforce confirmed schedules.' );
	$token2 = PickupManager::propose( $order, $item_id, $new_date, '13:00-14:00' );
	$assert( ! PickupManager::valid_token( $order, $token ), 'Resending invalidates the old token.' );
	$p = $order->get_meta( PickupManager::PROPOSAL, true );
	$p['expires'] = time() - 1;
	$order->update_meta_data( PickupManager::PROPOSAL, $p );
	$order->save();
	$assert( ! PickupManager::valid_token( $order, $token2 ), 'Expired tokens rejected.' );
	$p['expires'] = time() + DAY_IN_SECONDS;
	$order->update_meta_data( PickupManager::PROPOSAL, $p );
	$order->save();
	PickupManager::locked( $order->get_id(), static fn( $fresh ) => PickupManager::confirm( $fresh, $token2 ) );
	$order = wc_get_order( $order->get_id() );
	$assert( $new_date === $order->get_item( $item_id )->get_meta( Plugin::DATE, true ), 'Confirmed schedule is stored on the selected position.' );
	$assert( $date === $order->get_item( $second_id )->get_meta( Plugin::DATE, true ), 'Other item schedules stay unchanged.' );
	$assert( ! $order->get_meta( Plugin::DATE, true ) && ! $order->get_meta( PickupManager::PROPOSAL, true ), 'Stale common date and consumed proposal are cleared.' );
	$assert( ! PickupManager::valid_token( $order, $token2 ), 'Consumed links cannot be replayed.' );
	$order->set_status( PickupManager::READY );
	$order->save();
	$assert( ! $order->is_paid(), 'Ready status does not imply payment.' );
	$count = count( $messages );
	$order->save();
	$assert( $count === count( $messages ), 'Saving an unchanged ready order does not resend email.' );
	$unpaid = clone $order;
	$rejects( static fn() => PickupManager::assert_status( $unpaid, PickupManager::COLLECTED ), 'Unpaid collection rejected before mutation.' );
	$order->payment_complete();
	$assert( $order->get_date_paid() && $order->is_paid() && $order->has_status( PickupManager::READY ), 'Payment at collection records payment while retaining ready status.' );
	$order->set_status( PickupManager::COLLECTED );
	$order->save();
	$assert( $order->has_status( PickupManager::COLLECTED ) && ! PickupManager::open( $order ), 'Collected orders leave the open manager.' );
	$assert( 8 === wc_get_product( $product->get_id() )->get_stock_quantity(), 'Collection never reduces stock a second time.' );
	$assert( count( array_filter( $messages, static fn( $message ) => str_contains( $message['subject'], 'Bereit zur Abholung' ) ) ) === 1, 'Exactly one ready email follows the real status transition.' );
	$assert( count( array_filter( $messages, static fn( $message ) => str_contains( $message['subject'], 'Bestellung abgeholt' ) ) ) === 1, 'Exactly one collected email follows the real status transition.' );
	foreach ( array( 'ready', 'collected', 'confirmed', 'confirmed_admin' ) as $event ) {
		$email = $emails[ 'p9od_' . $event ];
		$email->object = $order;
		$html = $email->get_content_html();
		$plain = $email->get_content_plain();
		$assert( str_contains( $html, 'Filiale A' ) && str_contains( $html, 'Pickup integration fixture' ) && str_contains( $html, '<table' ), 'HTML email uses WooCommerce layout, items and location.' );
		$assert( str_contains( $plain, 'Filiale A' ) && str_contains( $plain, 'Pickup integration fixture' ), 'Plain text email includes positions and location.' );
	}
	// Exercise WooCommerce's actual Store API payment dispatch, not only the gateway method.
	update_option( 'woocommerce_p9od_pay_on_pickup_settings', array( 'enabled' => 'yes' ) );
	WC()->payment_gateways()->payment_gateways()['p9od_pay_on_pickup']->enabled = 'yes';
	wc_load_cart();
	WC()->cart->empty_cart();
	$product->set_virtual( true );
	$product->save();
	WC()->cart->add_to_cart( $product->get_id(), 1, 0, array(), array( Plugin::DATE => $date, Plugin::TIME => '13:00-14:00' ) );
	WC()->cart->calculate_totals();
	$assert( PayOnPickup::cart_allowed(), 'Cart allowlist accepts an explicitly enabled product.' );
	$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
	$request->set_header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
	$request->set_param( 'payment_method', 'p9od_pay_on_pickup' );
	$request->set_param( 'billing_address', array( 'first_name' => 'Pickup', 'last_name' => 'Test', 'email' => 'customer@example.test', 'address_1' => 'Teststraße 1', 'city' => 'Kassel', 'postcode' => '34117', 'country' => 'DE', 'state' => '' ) );
	$response = rest_do_request( $request );
	$data = $response->get_data();
	if ( ! empty( $data['order_id'] ) ) {
		$api_order = wc_get_order( $data['order_id'] );
		$orders[] = $api_order;
	}
	$assert( 200 === $response->get_status(), 'Real Store API accepts the offline payment method: ' . wp_json_encode( $data ) );
	$assert( isset( $api_order ) && $api_order->has_status( 'on-hold' ) && ! $api_order->get_date_paid(), 'Store API order awaits payment at collection.' );
	WC()->cart->empty_cart();
	WP_CLI::success( $checks . ' pickup workflow assertions passed (' . get_bloginfo( 'version' ) . ' / WC ' . WC_VERSION . ').' );
} finally {
	foreach ( $orders as $order ) { $order->delete( true ); }
	foreach ( $products as $product ) { $product->delete( true ); }
	foreach ( array_reverse( $term_ids ) as $term_id ) { wp_delete_term( $term_id, 'product_cat' ); }
	foreach ( $options as $key => $value ) {
		null === $value ? delete_option( $key ) : update_option( $key, $value );
	}
	remove_filter( 'pre_wp_mail', $intercept );
}
