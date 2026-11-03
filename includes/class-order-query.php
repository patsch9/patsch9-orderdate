<?php
/** Order query compatibility for classic storage and HPOS. @package Patsch9Orderdate */
namespace Patsch9\Orderdate;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

final class OrderQuery {
	public static function boot(): void {
		add_filter( 'woocommerce_order_query_args', array( self::class, 'prepare' ) );
	}

	/** Translate the private pickup-manager query variable for the active order datastore. */
	public static function prepare( array $args ): array {
		if ( empty( $args['p9od_managed'] ) ) {
			return $args;
		}

		if ( class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
			$indexed = false;
			foreach ( $meta_query as $condition ) {
				if ( is_array( $condition ) && PickupManager::INDEX === ( $condition['key'] ?? null ) && 'yes' === ( $condition['value'] ?? null ) ) {
					$indexed = true;
					break;
				}
			}
			if ( ! $indexed ) {
				$meta_query[] = array( 'key' => PickupManager::INDEX, 'value' => 'yes' );
			}
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded pickup-manager query on the maintained _p9od_managed index marker; HPOS requires meta_query for order meta.
			$args['meta_query'] = $meta_query;
			unset( $args['p9od_managed'] );
			return $args;
		}

		// Legacy CPT storage rejects WC_Order_Query's meta_query argument. The existing
		// data-store filter translates p9od_managed into WP_Query meta_query instead.
		unset( $args['meta_query'] );
		return $args;
	}
}
