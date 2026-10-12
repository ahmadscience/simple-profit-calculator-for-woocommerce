<?php
/**
 * Storage for the manual monthly costs.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * One row per month. Amounts per expense category are kept as JSON so users can
 * add and rename categories without schema changes.
 */
class SPCW_DB {

	const TABLE          = 'spcw_monthly_costs';
	const VERSION        = '2.0.0';
	const VERSION_OPTION = 'spcw_db_version';

	/**
	 * Request-level cache of get_year() results.
	 *
	 * @var array
	 */
	private static $year_cache = array();

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create or upgrade the table. Column names avoid MySQL reserved words so
	 * dbDelta() handles them correctly.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  period_year smallint(4) unsigned NOT NULL,
  period_month tinyint(2) unsigned NOT NULL,
  costs longtext NULL,
  notes text NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY period (period_year,period_month)
) {$charset};";

		dbDelta( $sql );
		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Whether the table exists.
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Manual data for every month of a year.
	 *
	 * @param int $year Year.
	 * @return array month => ['costs' => [key => float], 'notes' => string]
	 */
	public static function get_year( int $year ): array {
		global $wpdb;
		if ( isset( self::$year_cache[ $year ] ) ) {
			return self::$year_cache[ $year ];
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, cached per request in $year_cache.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT period_month, costs, notes FROM %i WHERE period_year = %d', $table, $year ) );

		$out = array();
		for ( $m = 1; $m <= 12; $m++ ) {
			$out[ $m ] = array(
				'costs' => array(),
				'notes' => '',
			);
		}
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$m = (int) $row->period_month;
			if ( $m < 1 || $m > 12 ) {
				continue;
			}
			$out[ $m ] = array(
				'costs' => self::decode_costs( $row->costs ),
				'notes' => (string) $row->notes,
			);
		}
		self::$year_cache[ $year ] = $out;
		return $out;
	}

	/**
	 * Manual data for one month.
	 *
	 * @param int $year  Year.
	 * @param int $month Month 1-12.
	 */
	public static function get_month( int $year, int $month ): array {
		$all = self::get_year( $year );
		return $all[ $month ] ?? array(
			'costs' => array(),
			'notes' => '',
		);
	}

	/**
	 * Insert, update or delete the row for a month.
	 *
	 * @param int    $year  Year.
	 * @param int    $month Month 1-12.
	 * @param array  $costs key => float. Empty array + empty notes deletes the row.
	 * @param string $notes Notes.
	 * @return bool Success.
	 */
	public static function save_month( int $year, int $month, array $costs, string $notes ): bool {
		global $wpdb;
		unset( self::$year_cache[ $year ] );
		$table = self::table();
		$where = array(
			'period_year'  => $year,
			'period_month' => $month,
		);

		if ( empty( $costs ) && '' === $notes ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return false !== $wpdb->delete( $table, $where, array( '%d', '%d' ) );
		}

		$costs = array_map(
			function ( $v ) {
				return max( -SPCW_Settings::MAX_AMOUNT, min( SPCW_Settings::MAX_AMOUNT, round( (float) $v, 2 ) ) );
			},
			$costs
		);
		$notes = mb_substr( $notes, 0, SPCW_Settings::MAX_NOTE_CHARS );

		// Single atomic upsert on the (period_year, period_month) unique key, so two
		// simultaneous saves can't collide.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (period_year, period_month, costs, notes, updated_at) VALUES (%d, %d, %s, %s, %s)
				ON DUPLICATE KEY UPDATE costs = VALUES(costs), notes = VALUES(notes), updated_at = VALUES(updated_at)',
				$table,
				$year,
				$month,
				wp_json_encode( $costs ),
				$notes,
				current_time( 'mysql', true )
			)
		);
		return false !== $result;
	}

	/**
	 * Decode the JSON cost column into key => float.
	 *
	 * @param string|null $json Stored JSON.
	 */
	private static function decode_costs( $json ): array {
		$data = json_decode( (string) $json, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$out[ (string) $key ] = (float) $value;
			}
		}
		return $out;
	}
}
