<?php
/** Plain text pickup email. @package Patsch9Orderdate */
defined( 'ABSPATH' ) || exit;
echo wp_strip_all_tags( $email_heading ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text email.
echo wp_strip_all_tags( $email->message() ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text email.
if ( 'rescheduled' === $email->pickup_event ) {
	$p9od_proposal = $order->get_meta( \Patsch9\Orderdate\PickupManager::PROPOSAL, true );
	$p9od_item = $order->get_item( $p9od_proposal['item'] );
	if ( $p9od_item ) {
		echo wp_strip_all_tags( $p9od_item->get_name() . ': ' . \Patsch9\Orderdate\Plugin::display( $p9od_proposal['date'] ) . ' ' . \Patsch9\Orderdate\Plugin::display_time( $p9od_proposal['time'] ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text email.
	}
	echo esc_url_raw( $email->confirmation_url ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text email URL.
}
foreach ( \Patsch9\Orderdate\PickupLocations::order_locations( $order ) as $p9od_location ) {
	echo wp_strip_all_tags( $p9od_location ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text email.
}
if ( 'ready' === $email->pickup_event && 'p9od_pay_on_pickup' === $order->get_payment_method() && ! $order->get_date_paid() ) {
	echo esc_html__( 'Die Zahlung ist bei der Abholung vor Ort fällig.', 'patsch9-orderdate' ) . "\n\n";
}
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
echo wp_strip_all_tags( $additional_content ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text email.
echo wp_strip_all_tags( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text email.
