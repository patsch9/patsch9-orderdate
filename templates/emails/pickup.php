<?php
/** WooCommerce pickup email. @package Patsch9Orderdate */
defined( 'ABSPATH' ) || exit;
do_action( 'woocommerce_email_header', $email_heading, $email );
?>
<p><?php echo esc_html( $email->message() ); ?></p>
<?php
if ( 'rescheduled' === $email->pickup_event ) {
	$p9od_proposal = $order->get_meta( \Patsch9\Orderdate\PickupManager::PROPOSAL, true );
	$p9od_item = $order->get_item( $p9od_proposal['item'] );
	if ( $p9od_item ) {
		echo '<p><strong>' . esc_html( $p9od_item->get_name() ) . '</strong><br>' . esc_html( \Patsch9\Orderdate\Plugin::display( $p9od_proposal['date'] ) . ' ' . \Patsch9\Orderdate\Plugin::display_time( $p9od_proposal['time'] ) ) . '</p>';
	}
	echo '<p><a href="' . esc_url( $email->confirmation_url ) . '">' . esc_html__( 'Neuen Abholtermin bestätigen', 'patsch9-orderdate' ) . '</a></p>';
}
foreach ( \Patsch9\Orderdate\PickupLocations::order_locations( $order ) as $p9od_location ) {
	echo '<p><strong>' . esc_html__( 'Abholort', 'patsch9-orderdate' ) . ':</strong> ' . esc_html( $p9od_location ) . '</p>';
}
if ( 'ready' === $email->pickup_event && 'p9od_pay_on_pickup' === $order->get_payment_method() && ! $order->get_date_paid() ) {
	echo '<p>' . esc_html__( 'Die Zahlung ist bei der Abholung vor Ort fällig.', 'patsch9-orderdate' ) . '</p>';
}
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
do_action( 'woocommerce_email_footer', $email );
