<?php
/**
 * Settings storage, defaults and sanitising.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * All plugin settings live in one option array.
 */
class SPCW_Settings {

	const OPTION = 'spcw_settings';

	/** Cost-of-goods modes. */
	const COGS_PRODUCT = 'product';
	const COGS_MONTHLY = 'monthly';
	const COGS_PERCENT = 'percent';

	/** Key of the expense category used for the shipping-margin calculation. */
	const SHIPPING_CATEGORY = 'shipping';

	/** Input limits (defence against oversized or abusive submissions). */
	const MAX_AMOUNT      = 9999999999.99;
	const MAX_CATEGORIES  = 50;
	const MAX_GATEWAYS    = 100;
	const MAX_NOTE_CHARS  = 1000;
	const MAX_LABEL_CHARS = 60;

	/**
	 * Request-level cache of the merged settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings for a fresh install.
	 *
	 * Category labels are stored untranslated so they stay stable if the admin
	 * language changes; users rename them freely in Settings.
	 */
	public static function defaults(): array {
		return array(
			'order_statuses'      => array( 'wc-completed', 'wc-processing' ),
			'cogs_mode'           => self::COGS_PRODUCT,
			'cogs_source'         => 'auto',
			'cogs_meta_key'       => '',
			'cogs_percent'        => 0,
			'gateways'            => array(),
			'categories'          => array(
				'shipping'   => 'Shipping / postage',
				'ads'        => 'Advertising',
				'packaging'  => 'Packaging',
				'software'   => 'Software & subscriptions',
				'accounting' => 'Accounting',
				'other'      => 'Other',
			),
			'delete_on_uninstall' => false,
		);
	}

	/**
	 * Full settings array with defaults filled in.
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	/**
	 * Single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Persist settings (already sanitised).
	 *
	 * @param array $settings Settings.
	 */
	public static function save( array $settings ) {
		update_option( self::OPTION, $settings );
		self::$cache = null;
	}

	/**
	 * Order statuses to count, always non-empty and limited to real statuses.
	 */
	public static function order_statuses(): array {
		/**
		 * Filters which order statuses count as sales.
		 *
		 * @since 2.0.1
		 *
		 * @param string[] $statuses Status slugs with "wc-" prefix, e.g. array( 'wc-completed' ).
		 */
		$statuses = (array) apply_filters( 'spcw_order_statuses', (array) self::get( 'order_statuses' ) );
		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$statuses = array_values( array_intersect( $statuses, array_keys( wc_get_order_statuses() ) ) );
		}
		return $statuses ? $statuses : array( 'wc-completed', 'wc-processing' );
	}

	/**
	 * Expense categories as key => label.
	 */
	public static function categories(): array {
		$cats = self::get( 'categories' );
		return is_array( $cats ) ? $cats : array();
	}

	/**
	 * Payment gateway fees as id => [label, percent, fixed].
	 */
	public static function gateways(): array {
		$gw = self::get( 'gateways' );
		return is_array( $gw ) ? $gw : array();
	}

	/**
	 * Capability needed to use the plugin.
	 */
	public static function capability(): string {
		/**
		 * Filters the capability required to view and edit profit data.
		 *
		 * @since 2.0.0
		 *
		 * @param string $capability Default 'manage_woocommerce'.
		 */
		return (string) apply_filters( 'spcw_capability', 'manage_woocommerce' );
	}

	/**
	 * Cache version. Bumping it invalidates every cached month at once.
	 */
	public static function cache_version(): int {
		return (int) get_option( 'spcw_cache_version', 1 );
	}

	/**
	 * Invalidate all cached order data.
	 */
	public static function bump_cache_version() {
		update_option( 'spcw_cache_version', self::cache_version() + 1, true );
		self::purge_stale_transients();
	}

	/**
	 * Delete cached months from older cache versions so they don't pile up in
	 * wp_options until they expire (relevant without a persistent object cache).
	 * Runs at most once per minute.
	 */
	private static function purge_stale_transients() {
		if ( wp_using_ext_object_cache() || get_transient( 'spcw_purge_lock' ) ) {
			return;
		}
		set_transient( 'spcw_purge_lock', 1, MINUTE_IN_SECONDS );

		global $wpdb;
		$keep = '_transient_spcw_m_' . self::cache_version() . '_';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk cleanup of this plugin's own transients.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT option_name FROM %i WHERE option_name LIKE %s AND option_name NOT LIKE %s LIMIT 1000',
				$wpdb->options,
				$wpdb->esc_like( '_transient_spcw_m_' ) . '%',
				$wpdb->esc_like( $keep ) . '%'
			)
		);
		foreach ( (array) $names as $name ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
		}
	}

	/**
	 * Whether the current user may change destructive settings (delete data on uninstall).
	 * Shop Managers can use the plugin, but only site administrators can opt in to data deletion.
	 */
	public static function can_manage_data(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'delete_plugins' );
	}

	/**
	 * Parse a user-typed money amount. Accepts "1 234,56", "1.234,56", "1,234.56", "kr 99".
	 *
	 * @param mixed $raw Raw input (already unslashed).
	 * @return float|null Null when the field is empty or not a number.
	 */
	public static function parse_amount( $raw ) {
		if ( ! is_scalar( $raw ) ) {
			return null;
		}
		$raw = trim( (string) $raw );
		$raw = str_replace( array( ' ', "\xc2\xa0", "\xe2\x80\xaf", "'" ), '', $raw );
		$raw = preg_replace( '/[^0-9,.\-]/', '', $raw );
		if ( '' === $raw || '-' === $raw ) {
			return null;
		}

		$negative = 0 === strpos( $raw, '-' );
		$raw      = str_replace( '-', '', $raw );

		$last_comma = strrpos( $raw, ',' );
		$last_dot   = strrpos( $raw, '.' );
		$decimal    = null;

		if ( false !== $last_comma && false !== $last_dot ) {
			$decimal = $last_comma > $last_dot ? ',' : '.';
		} elseif ( false !== $last_comma || false !== $last_dot ) {
			$sep   = false !== $last_comma ? ',' : '.';
			$parts = explode( $sep, $raw );
			$store = function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.';
			if ( count( $parts ) > 2 ) {
				$decimal = null; // "1.234.567" – thousands separators only.
			} elseif ( $sep !== $store && 3 === strlen( end( $parts ) ) ) {
				$decimal = null; // "1,234" in a store that uses "." for decimals.
			} else {
				$decimal = $sep;
			}
		}

		if ( null === $decimal ) {
			$raw = str_replace( array( ',', '.' ), '', $raw );
		} else {
			$raw = str_replace( ',' === $decimal ? '.' : ',', '', $raw );
			$raw = str_replace( $decimal, '.', $raw );
		}

		if ( ! is_numeric( $raw ) ) {
			return null;
		}
		$value = min( round( (float) $raw, 2 ), self::MAX_AMOUNT );
		return $negative ? -$value : $value;
	}
}
