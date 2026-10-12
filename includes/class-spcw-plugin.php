<?php
/**
 * Bootstrap: activation, WooCommerce checks, hook wiring.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
class SPCW_Plugin {

	/**
	 * Activation: create the table and seed defaults.
	 */
	public static function activate() {
		SPCW_DB::install();
		if ( false === get_option( SPCW_Settings::OPTION ) ) {
			add_option( SPCW_Settings::OPTION, SPCW_Settings::defaults() );
		}
		SPCW_Migration::migrate_v1_data();
	}

	/**
	 * Tell WooCommerce we support High-Performance Order Storage.
	 */
	public static function declare_wc_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SPCW_FILE, true );
			// Admin-only plugin: it doesn't touch the cart or checkout.
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SPCW_FILE, true );
		}
	}

	/**
	 * Runs on plugins_loaded.
	 */
	public static function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_wc_missing' ) );
			return;
		}

		// Covers updates uploaded over FTP, where the activation hook never runs.
		if ( get_option( SPCW_DB::VERSION_OPTION ) !== SPCW_DB::VERSION ) {
			SPCW_DB::install();
		}

		SPCW_Orders::init();
		SPCW_Cogs::init();

		if ( is_admin() ) {
			SPCW_Migration::init();
			SPCW_Admin::init();
		}
	}

	/**
	 * Admin notice when WooCommerce is not active.
	 */
	public static function notice_wc_missing() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Simple Profit Calculator for WooCommerce needs WooCommerce to be installed and active.', 'simple-profit-calculator-for-woocommerce' ) . '</p></div>';
	}
}
