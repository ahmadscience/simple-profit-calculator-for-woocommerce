<?php
/**
 * One-time import from the original "Profit Calculator" (v1) plugin.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Copies v1 data (table profit_monthly_data, options pc_settings and
 * pc_gateway_fees). The v1 data is left untouched.
 */
class SPCW_Migration {

	const FLAG = 'spcw_v1_migrated';

	/** Map of v1 column => v2 expense category. */
	const COLUMN_MAP = array(
		'postal_cost'     => 'shipping',
		'ads_spend'       => 'ads',
		'accountant_cost' => 'accounting',
		'packing_cost'    => 'packaging',
		'saas_cost'       => 'software',
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		// admin_init: WooCommerce payment gateways are available by then.
		add_action( 'admin_init', array( __CLASS__, 'migrate_v1_data' ) );
	}

	/**
	 * Run the import once.
	 */
	public static function migrate_v1_data() {
		if ( get_option( self::FLAG ) || ! did_action( 'woocommerce_loaded' ) ) {
			return;
		}
		// admin_init also fires for logged-out admin-ajax/admin-post requests: only
		// run for a user who could use the plugin anyway.
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- capability comes from SPCW_Settings::capability(): 'manage_woocommerce', filterable via spcw_capability.
		if ( wp_doing_ajax() || ! current_user_can( SPCW_Settings::capability() ) ) {
			return;
		}
		update_option( self::FLAG, time(), false );

		// The v1 option and table names are generic. Only import when the table
		// has the exact v1 structure, so data from another plugin is never touched.
		if ( ! self::v1_table_present() ) {
			return;
		}
		self::migrate_settings();
		self::migrate_table();
	}

	/**
	 * Whether the v1 table exists with the v1 columns.
	 */
	private static function v1_table_present(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'profit_monthly_data';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
		return ! array_diff( array( 'year', 'month', 'postal_cost', 'ads_spend', 'notes' ), $columns );
	}

	/**
	 * Copy statuses, COGS meta key and gateway fees.
	 */
	private static function migrate_settings() {
		$old = get_option( 'pc_settings' );
		$fee = get_option( 'pc_gateway_fees' );
		if ( ! is_array( $old ) && ! is_array( $fee ) ) {
			return;
		}

		$settings = SPCW_Settings::all();

		if ( is_array( $old ) ) {
			if ( ! empty( $old['order_statuses'] ) && is_array( $old['order_statuses'] ) ) {
				$settings['order_statuses'] = array_map( 'sanitize_text_field', $old['order_statuses'] );
			}
			$key = isset( $old['cost_meta_key'] ) && is_string( $old['cost_meta_key'] ) ? preg_replace( '/[^A-Za-z0-9_\-]/', '', $old['cost_meta_key'] ) : '';
			$src = array_search( $key, SPCW_Cogs::META_KEYS, true );
			if ( false !== $src ) {
				$settings['cogs_source'] = $src;
			} elseif ( '' !== $key ) {
				$settings['cogs_source']   = 'custom';
				$settings['cogs_meta_key'] = $key;
			}
		}

		if ( is_array( $fee ) ) {
			$registered = array();
			if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
				$registered = array_keys( WC()->payment_gateways()->payment_gateways() );
			}
			foreach ( array_slice( $fee, 0, SPCW_Settings::MAX_GATEWAYS, true ) as $old_id => $conf ) {
				$old_id = sanitize_key( $old_id );
				if ( '' === $old_id || ! is_array( $conf ) || ! isset( $conf['fee'] ) || ! is_numeric( $conf['fee'] ) ) {
					continue;
				}
				$entry = array(
					'label'   => sanitize_text_field( $conf['label'] ?? '' ),
					'percent' => min( 100, max( 0, (float) $conf['fee'] ) ),
					'fixed'   => 0.0,
				);
				// v1 used substring matching ("klarna" for "klarna_payments"); v2 uses exact ids.
				$matches = array_filter(
					$registered,
					function ( $id ) use ( $old_id ) {
						return false !== strpos( $id, $old_id );
					}
				);
				foreach ( $matches ? $matches : array( $old_id ) as $id ) {
					$settings['gateways'][ $id ] = $entry;
				}
			}
		}

		SPCW_Settings::save( $settings );
	}

	/**
	 * Copy monthly rows that don't exist yet in the new table.
	 */
	private static function migrate_table() {
		global $wpdb;
		$old_table = $wpdb->prefix . 'profit_monthly_data';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $old_table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old_table ) ) ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import from the v1 table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $old_table ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$year  = (int) ( $row['year'] ?? 0 );
			$month = (int) ( $row['month'] ?? 0 );
			if ( $year < 2000 || $year > 2100 || $month < 1 || $month > 12 ) {
				continue;
			}
			$existing = SPCW_DB::get_month( $year, $month );
			if ( $existing['costs'] || '' !== $existing['notes'] ) {
				continue;
			}
			$costs = array();
			foreach ( self::COLUMN_MAP as $column => $category ) {
				if ( isset( $row[ $column ] ) && 0.0 !== (float) $row[ $column ] ) {
					$costs[ $category ] = round( (float) $row[ $column ], 2 );
				}
			}
			SPCW_DB::save_month( $year, $month, $costs, sanitize_textarea_field( (string) ( $row['notes'] ?? '' ) ) );
		}
	}

	/**
	 * Whether the v1 plugin is still running.
	 */
	public static function v1_active(): bool {
		return class_exists( 'Profit_Calculator' ) && class_exists( 'Profit_DB' );
	}
}
