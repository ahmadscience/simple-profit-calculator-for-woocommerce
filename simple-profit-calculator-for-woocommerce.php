<?php
/**
 * Plugin Name:          Simple Profit Calculator for WooCommerce
 * Plugin URI:           https://github.com/ahmadscience/simple-profit-calculator-for-woocommerce
 * Description:          See your real monthly and yearly profit. Combines WooCommerce sales with product costs (from WooCommerce, popular Cost of Goods plugins, its own cost field, a monthly lump sum or a percentage), payment fees and your own monthly expenses.
 * Version:              2.0.0
 * Requires at least:    6.3
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Ahmad Abdur Rehman
 * Author URI:           https://ahmad.science
 * License:              GPLv2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          simple-profit-calculator-for-woocommerce
 * WC requires at least: 7.0
 * WC tested up to:      11.2
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

define( 'SPCW_VERSION', '2.0.0' );
define( 'SPCW_FILE', __FILE__ );
define( 'SPCW_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPCW_URL', plugin_dir_url( __FILE__ ) );

require_once SPCW_DIR . 'includes/class-spcw-settings.php';
require_once SPCW_DIR . 'includes/class-spcw-db.php';
require_once SPCW_DIR . 'includes/class-spcw-cogs.php';
require_once SPCW_DIR . 'includes/class-spcw-orders.php';
require_once SPCW_DIR . 'includes/class-spcw-calculator.php';
require_once SPCW_DIR . 'includes/class-spcw-migration.php';
require_once SPCW_DIR . 'includes/class-spcw-plugin.php';
require_once SPCW_DIR . 'admin/class-spcw-admin.php';

register_activation_hook( __FILE__, array( 'SPCW_Plugin', 'activate' ) );

add_action( 'before_woocommerce_init', array( 'SPCW_Plugin', 'declare_wc_compatibility' ) );
add_action( 'plugins_loaded', array( 'SPCW_Plugin', 'init' ) );
