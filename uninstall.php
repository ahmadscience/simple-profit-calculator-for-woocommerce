<?php
/**
 * Removes plugin data on uninstall, but only on sites where an administrator
 * opted in under Profit → Settings → Data. On multisite every site is checked
 * against its own setting.
 *
 * @package SimpleProfitCalculator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete this plugin's data from the current site if the site opted in.
 */
function spcw_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'spcw_settings', array() );
	if ( ! is_array( $settings ) || empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing this plugin's own table and transients.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'spcw_monthly_costs' ) );
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like( '_transient_spcw_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_spcw_' ) . '%'
		)
	);
	// phpcs:enable

	delete_post_meta_by_key( '_spcw_cost' );

	foreach ( array( 'spcw_settings', 'spcw_db_version', 'spcw_cache_version', 'spcw_v1_migrated' ) as $option ) {
		delete_option( $option );
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $spcw_site_id ) {
		switch_to_blog( $spcw_site_id );
		spcw_uninstall_site();
		restore_current_blog();
	}
} else {
	spcw_uninstall_site();
}
