<?php
/**
 * Monthly WooCommerce sales figures, with caching.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates orders per calendar month (store timezone).
 */
class SPCW_Orders {

	/**
	 * Months to invalidate at the end of the request, "Y-n" => true.
	 *
	 * @var array
	 */
	private static $dirty = array();

	/**
	 * Hooks that keep the cache fresh when orders change.
	 */
	public static function init() {
		// These pass ( $order_id, $order ); the order object is reused when present.
		foreach ( array( 'woocommerce_new_order', 'woocommerce_update_order', 'woocommerce_before_delete_order', 'woocommerce_before_trash_order' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'order_changed' ), 10, 2 );
		}
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'status_changed' ), 10, 4 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'order_changed' ) );
		// Legacy (non-HPOS) storage deletes/trashes orders as posts.
		add_action( 'before_delete_post', array( __CLASS__, 'post_changed' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'post_changed' ) );
		add_action( 'shutdown', array( __CLASS__, 'flush_dirty' ) );
	}

	/**
	 * Mark the month of a changed order as stale.
	 *
	 * @param int|WC_Order $order_id Order id.
	 * @param mixed        $order    Order object, when the hook provides one.
	 */
	public static function order_changed( $order_id, $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = $order_id instanceof WC_Order ? $order_id : wc_get_order( $order_id );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$created = $order->get_date_created();
		if ( $created ) {
			self::$dirty[ $created->date( 'Y' ) . '-' . $created->date( 'n' ) ] = true;
		}
	}

	/**
	 * Status change: woocommerce_order_status_changed passes the order as 4th argument.
	 *
	 * @param int      $order_id Order id.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 */
	public static function status_changed( $order_id, $from = '', $to = '', $order = null ) {
		self::order_changed( $order_id, $order );
	}

	/**
	 * Legacy storage: trashed/deleted shop_order posts.
	 *
	 * @param int $post_id Post id.
	 */
	public static function post_changed( $post_id ) {
		if ( 'shop_order' === get_post_type( $post_id ) ) {
			self::order_changed( $post_id );
		}
	}

	/**
	 * Delete stale cache entries.
	 */
	public static function flush_dirty() {
		foreach ( array_keys( self::$dirty ) as $ym ) {
			list( $y, $m ) = array_map( 'intval', explode( '-', $ym ) );
			delete_transient( self::cache_key( $y, $m ) );
		}
		self::$dirty = array();
	}

	/**
	 * Transient key for a month.
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 */
	private static function cache_key( int $year, int $month ): string {
		return 'spcw_m_' . SPCW_Settings::cache_version() . '_' . $year . '_' . $month;
	}

	/**
	 * Empty result structure.
	 */
	private static function empty_month(): array {
		return array(
			'order_count'        => 0,
			'gross'              => 0.0, // Order totals incl. tax and shipping.
			'refunds'            => 0.0, // Total refunded.
			'tax'                => 0.0, // Tax kept (after refunded tax).
			'shipping'           => 0.0, // Shipping charged excl. tax, after refunds.
			'cogs'               => 0.0, // Product cost (product mode only).
			'items_missing_cost' => 0,   // Units sold with no cost price.
			'gateways'           => array(), // id => [label, gross, orders].
		);
	}

	/**
	 * Sales figures for a month in the store's timezone.
	 *
	 * @param int $year  Year.
	 * @param int $month Month 1-12.
	 */
	public static function get_month( int $year, int $month ): array {
		$now = current_datetime();
		if ( $year > (int) $now->format( 'Y' ) || ( $year === (int) $now->format( 'Y' ) && $month > (int) $now->format( 'n' ) ) ) {
			return self::empty_month(); // Future month.
		}

		$key    = self::cache_key( $year, $month );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['gateways'] ) ) {
			return $cached;
		}

		$result = self::empty_month();
		$cogs   = SPCW_Settings::COGS_PRODUCT === SPCW_Settings::get( 'cogs_mode' );

		foreach ( self::order_ids( $year, $month ) as $i => $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order && ! $order instanceof WC_Order_Refund ) {
				self::add_order( $result, $order, $cogs );
			}
			if ( 0 === ( $i + 1 ) % 200 ) {
				self::free_memory();
			}
		}

		foreach ( array( 'gross', 'refunds', 'tax', 'shipping', 'cogs' ) as $k ) {
			$result[ $k ] = round( $result[ $k ], 2 );
		}

		// Past months rarely change and are invalidated by hooks anyway; keep the
		// current month short as a safety net for orders changed outside WooCommerce.
		$is_current = (int) $now->format( 'Y' ) === $year && (int) $now->format( 'n' ) === $month;
		set_transient( $key, $result, $is_current ? HOUR_IN_SECONDS : 30 * DAY_IN_SECONDS );

		return $result;
	}

	/**
	 * Add one order to the month totals.
	 *
	 * @param array    $result Running totals.
	 * @param WC_Order $order  Order.
	 * @param bool     $cogs   Whether to compute product cost.
	 */
	private static function add_order( array &$result, WC_Order $order, bool $cogs ) {
		$gross = (float) $order->get_total();

		++$result['order_count'];
		$result['gross']    += $gross;
		$result['refunds']  += (float) $order->get_total_refunded();
		$result['tax']      += (float) $order->get_total_tax() - (float) $order->get_total_tax_refunded();
		$result['shipping'] += (float) $order->get_shipping_total() - (float) $order->get_total_shipping_refunded();

		$method = (string) $order->get_payment_method();
		if ( ! isset( $result['gateways'][ $method ] ) ) {
			$title                         = $order->get_payment_method_title();
			$result['gateways'][ $method ] = array(
				'label'  => '' !== $method ? ( $title ? $title : $method ) : __( 'No payment method', 'simple-profit-calculator-for-woocommerce' ),
				'gross'  => 0.0,
				'orders' => 0,
			);
		}
		$result['gateways'][ $method ]['gross'] += $gross;
		++$result['gateways'][ $method ]['orders'];

		if ( ! $cogs ) {
			return;
		}
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$qty = (float) $item->get_quantity() - abs( (float) $order->get_qty_refunded_for_item( $item_id ) );
			if ( $qty <= 0 ) {
				continue;
			}
			$product = $item->get_product();
			$cost    = $product instanceof WC_Product ? SPCW_Cogs::unit_cost( $product ) : null;
			if ( null === $cost ) {
				$result['items_missing_cost'] += (int) ceil( $qty );
				continue;
			}
			$result['cogs'] += $cost * $qty;
		}
	}

	/**
	 * Order ids created in a month, for the configured statuses.
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @return int[]
	 */
	private static function order_ids( int $year, int $month ): array {
		global $wpdb;
		$statuses = SPCW_Settings::order_statuses();
		$tz       = wp_timezone();
		$start    = new DateTimeImmutable( sprintf( '%04d-%02d-01 00:00:00', $year, $month ), $tz );
		$end      = $start->modify( 'first day of next month' );

		if ( self::hpos_enabled() ) {
			// HPOS stores dates in UTC. Converting each boundary on its own date handles DST correctly.
			$utc          = new DateTimeZone( 'UTC' );
			$table        = $wpdb->prefix . 'wc_orders';
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$args         = array_merge( array( $table ), $statuses, array( $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ), $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ) ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is a list of %s built above; result is cached in a transient.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE type = 'shop_order' AND status IN ({$placeholders}) AND date_created_gmt >= %s AND date_created_gmt < %s ORDER BY id ASC", $args ) );
			return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
		}

		// Legacy storage: post_date is in the store's local time.
		$query = new WP_Query(
			array(
				'post_type'              => 'shop_order',
				'post_status'            => $statuses,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					array(
						'column'    => 'post_date',
						'after'     => $start->format( 'Y-m-d H:i:s' ),
						'before'    => $end->modify( '-1 second' )->format( 'Y-m-d H:i:s' ),
						'inclusive' => true,
					),
				),
			)
		);
		return is_array( $query->posts ) ? array_map( 'intval', $query->posts ) : array();
	}

	/**
	 * Large stores: drop WordPress's in-memory object cache when memory usage
	 * gets high, so aggregating thousands of orders doesn't hit memory_limit.
	 */
	private static function free_memory() {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		if ( $limit > 0 && memory_get_usage() > $limit * 0.6 && function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}
	}

	/**
	 * Whether WooCommerce uses HPOS order tables.
	 */
	public static function hpos_enabled(): bool {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Year of the first order (store time), cached for a day.
	 */
	public static function first_order_year(): int {
		$cached = get_transient( 'spcw_first_order_year' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		global $wpdb;
		if ( self::hpos_enabled() ) {
			$table = $wpdb->prefix . 'wc_orders';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in a transient below.
			$first = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(date_created_gmt) FROM %i WHERE type = 'shop_order' AND date_created_gmt IS NOT NULL", $table ) );
			$first = $first ? get_date_from_gmt( $first, 'Y' ) : null;
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in a transient below.
			$first = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(post_date) FROM %i WHERE post_type = 'shop_order' AND post_date > '1971-01-01'", $wpdb->posts ) );
			$first = $first ? substr( $first, 0, 4 ) : null;
		}
		$current = (int) current_time( 'Y' );
		$year    = $first ? max( (int) $first, $current - 20 ) : $current;
		set_transient( 'spcw_first_order_year', $year, DAY_IN_SECONDS );
		return $year;
	}
}
