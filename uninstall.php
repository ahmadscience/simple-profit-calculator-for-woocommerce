<?php
/**
 * Removes plugin data on uninstall, but only if the user opted in under Settings → Data.
 *
 * @package SimpleProfitCalculator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$spcw_settings = get_option( 'spcw_settings', array() );
if ( empty( $spcw_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'spcw_monthly_costs' ) );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s', $wpdb->options, $wpdb->esc_like( '_transient_spcw_' ) . '%', $wpdb->esc_like( '_transient_timeout_spcw_' ) . '%' ) );
// phpcs:enable

delete_post_meta_by_key( '_spcw_cost' );

foreach ( array( 'spcw_settings', 'spcw_db_version', 'spcw_cache_version', 'spcw_v1_migrated' ) as $spcw_option ) {
	delete_option( $spcw_option );
}
