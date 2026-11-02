<?php
/** Reuse WooCommerce shipping selections and their historical location snapshots. @package Patsch9Orderdate */
namespace Patsch9\Orderdate;
defined( 'ABSPATH' ) || exit;

final class PickupLocations {
	public static function is_pickup( string $method ): bool {
		return in_array( explode( ':', $method )[0], array( 'local_pickup', 'pickup_location' ), true );
	}

	/** Never resolve an old order through today's editable location array indices. */
	public static function order_locations( \WC_Order $order ): array {
		$locations = array();
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( self::is_pickup( $item->get_method_id() ) ) {
				$name = wp_strip_all_tags( (string) $item->get_meta( 'pickup_location', true ) );
				$address = wp_strip_all_tags( (string) $item->get_meta( 'pickup_address', true ) );
				$locations[] = trim( ( $name ?: $item->get_method_title() ) . ( $address ? ' · ' . $address : '' ) );
			}
		}
		return array_values( array_unique( $locations ) );
	}

	public static function order_eligible( \WC_Order $order ): bool {
		$shipping = $order->get_items( 'shipping' );
		foreach ( $shipping as $item ) {
			if ( ! self::is_pickup( $item->get_method_id() ) ) {
				return false;
			}
		}
		return (bool) $shipping || (bool) PickupManager::items( $order );
	}

	public static function cart_eligible(): bool {
		if ( ! WC()->cart || ! WC()->session ) {
			return false;
		}
		if ( ! WC()->cart->needs_shipping() ) {
			foreach ( Plugin::cart() as $item ) {
				if ( 'off' !== Plugin::rule( (int) ( $item['variation_id'] ?: $item['product_id'] ) )['mode'] ) {
					return true;
				}
			}
			return false;
		}
		$packages = WC()->shipping()->get_packages();
		$chosen = (array) WC()->session->get( 'chosen_shipping_methods', array() );
		if ( ! $packages ) {
			return false;
		}
		foreach ( $packages as $index => $package ) {
			$method = $chosen[ $index ] ?? '';
			if ( ! self::is_pickup( (string) $method ) || ! isset( $package['rates'][ $method ] ) ) {
				return false;
			}
		}
		return true;
	}
}
