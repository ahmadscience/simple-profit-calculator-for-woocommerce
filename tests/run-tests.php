<?php
/**
 * Integration tests, run inside WordPress Playground after tests/seed.php.
 * Writes tests/results.txt and throws (failing the run) if any check fails.
 *
 * Run locally:
 *   npx @wp-playground/cli@3.1.55 run-blueprint --blueprint=tests/blueprint.json \
 *     --mount=.:/wordpress/wp-content/plugins/simple-profit-calculator-for-woocommerce --mount=./tests:/tests
 *
 * @package SimpleProfitCalculator
 */

require_once '/wordpress/wp-load.php';

$GLOBALS['spcw_out']  = array();
$GLOBALS['spcw_fail'] = 0;

/**
 * Record one assertion.
 *
 * @param string $name What is checked.
 * @param mixed  $got  Actual.
 * @param mixed  $exp  Expected.
 */
function spcw_check( $name, $got, $exp ) {
	$ok = is_float( $exp ) ? ( is_numeric( $got ) && abs( $got - $exp ) < 0.001 ) : $got === $exp;
	if ( ! $ok ) {
		++$GLOBALS['spcw_fail'];
	}
	$GLOBALS['spcw_out'][] = ( $ok ? 'PASS ' : 'FAIL ' ) . $name . ( $ok ? '' : ': got ' . var_export( $got, true ) . ', expected ' . var_export( $exp, true ) );
}

/**
 * Create a completed cheque order.
 */
function spcw_mkorder( $pid, $qty, $date ) {
	$o = wc_create_order();
	$o->add_product( wc_get_product( $pid ), $qty );
	$o->set_payment_method( 'cheque' );
	$o->set_payment_method_title( 'Cheque' );
	$o->calculate_totals();
	$o->set_date_created( $date );
	$o->set_status( 'completed' );
	$o->save();
	return $o;
}

global $wpdb;
$spcw_hpos_expected = 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' );
$spcw_admin         = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
$spcw_manager       = wp_insert_user(
	array(
		'user_login' => 'spcw_manager_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 24 ),
		'role'       => 'shop_manager',
	)
);
$spcw_customer      = wp_insert_user(
	array(
		'user_login' => 'spcw_customer_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 24 ),
		'role'       => 'customer',
	)
);
$spcw_products      = get_option( 'spcw_seed_products' );
$spcw_p             = $spcw_products[0];

/* ── Environment ─────────────────────────────────────────────── */
spcw_check( 'running on PHP ' . PHP_VERSION . ', WC ' . WC_VERSION . ', WP ' . get_bloginfo( 'version' ), true, true );
spcw_check( 'order storage matches setup (HPOS=' . ( $spcw_hpos_expected ? 'yes' : 'no' ) . ')', SPCW_Orders::hpos_enabled(), $spcw_hpos_expected );
spcw_check( 'table installed', SPCW_DB::table_exists(), true );

/* ── v1 import ───────────────────────────────────────────────── */
// Activation (run as admin by the blueprint) may already have imported; start clean.
spcw_check( 'v1 tables/options seeded', (bool) get_option( 'pc_settings' ), true );
delete_option( SPCW_Migration::FLAG );
wp_set_current_user( 0 );
SPCW_Migration::migrate_v1_data();
spcw_check( 'security: import does not run for logged-out requests', get_option( SPCW_Migration::FLAG ), false );
wp_set_current_user( $spcw_customer );
SPCW_Migration::migrate_v1_data();
spcw_check( 'security: import does not run for customers', get_option( SPCW_Migration::FLAG ), false );
wp_set_current_user( $spcw_admin );
SPCW_Migration::migrate_v1_data();
spcw_check( 'import ran for admin', (bool) get_option( SPCW_Migration::FLAG ), true );
spcw_check( 'import: COGS source', SPCW_Settings::get( 'cogs_source' ), 'wpfactory' );
$spcw_gw = SPCW_Settings::gateways();
spcw_check( 'import: cheque fee', $spcw_gw['cheque']['percent'] ?? null, 2.5 );
$spcw_rows = SPCW_DB::get_year( 2026 );
spcw_check( 'import: Sep shipping', $spcw_rows[9]['costs']['shipping'] ?? null, 450.0 );
spcw_check( 'import: Aug accounting', $spcw_rows[8]['costs']['accounting'] ?? null, 1500.0 );
spcw_check( 'import: notes', $spcw_rows[9]['notes'], 'Sept legacy' );

// Foreign data with v1-like option names but no v1 table must be ignored.
$spcw_before = SPCW_Settings::all();
// Playground runs on SQLite, which doesn't support RENAME TABLE; the import is done, so drop the v1 table.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'profit_monthly_data' ) );
spcw_check( 'v1 table removed for the next check', (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'profit_monthly_data' ) ), false );
update_option( 'pc_settings', array( 'cost_meta_key' => '_other_plugins_key' ) );
delete_option( SPCW_Migration::FLAG );
SPCW_Migration::migrate_v1_data();
spcw_check( 'security: generic pc_* options without v1 table are not imported', SPCW_Settings::all(), $spcw_before );

/* ── Profit maths ────────────────────────────────────────────── */
$spcw_d = SPCW_Calculator::month( 2026, 9 );
spcw_check( 'Sep: orders (cancelled excluded)', $spcw_d['order_count'], 3 );
spcw_check( 'Sep: refunds', $spcw_d['refunds'], 125.0 );
spcw_check( 'Sep: net sales', $spcw_d['net_sales'], 500.0 );
spcw_check( 'Sep: COGS excludes refunded unit', $spcw_d['cogs'], 80.0 );
spcw_check( 'Sep: items missing cost', $spcw_d['items_missing_cost'], 1 );
spcw_check( 'Sep: no fee on order without payment method', $spcw_d['payment_fees'], 6.25 );
spcw_check( 'Sep: profit', $spcw_d['profit'], -1615.25 );
spcw_check( 'Aug: 23:30 on 31 Aug counts in August', SPCW_Calculator::month( 2026, 8 )['order_count'], 1 );

/* ── Month boundaries across daylight saving time ────────────── */
spcw_mkorder( $spcw_p, 1, '2026-10-01 00:30:00' );
spcw_mkorder( $spcw_p, 1, '2026-03-31 23:30:00' );
spcw_mkorder( $spcw_p, 1, '2026-03-01 00:15:00' );
SPCW_Orders::flush_dirty();
spcw_check( 'DST: Sep unchanged', SPCW_Orders::get_month( 2026, 9 )['order_count'], 3 );
spcw_check( 'DST: 1 Oct 00:30 is October', SPCW_Orders::get_month( 2026, 10 )['order_count'], 1 );
spcw_check( 'DST: March has 2', SPCW_Orders::get_month( 2026, 3 )['order_count'], 2 );
spcw_check( 'DST: February has 0', SPCW_Orders::get_month( 2026, 2 )['order_count'], 0 );

/* ── Cache ───────────────────────────────────────────────────── */
spcw_mkorder( $spcw_p, 2, '2026-10-05 12:00:00' );
spcw_check( 'cache: cached value used before flush', SPCW_Orders::get_month( 2026, 10 )['order_count'], 1 );
SPCW_Orders::flush_dirty();
spcw_check( 'cache: new order refreshes its month', SPCW_Orders::get_month( 2026, 10 )['order_count'], 2 );
$spcw_v = SPCW_Settings::cache_version();
update_post_meta( $spcw_p, '_alg_wc_cog_cost', '41' );
spcw_check( 'cache: cost change resets cache', SPCW_Settings::cache_version(), $spcw_v + 1 );
spcw_check( 'cache: new cost used', SPCW_Calculator::month( 2026, 9 )['cogs'], 82.0 );
set_transient( 'spcw_m_1_2000_1', array( 'x' ), DAY_IN_SECONDS );
delete_transient( 'spcw_purge_lock' );
SPCW_Settings::bump_cache_version();
spcw_check( 'cache: stale cache entries purged from database', get_transient( 'spcw_m_1_2000_1' ), false );

/* ── Storage ─────────────────────────────────────────────────── */
SPCW_DB::save_month( 2025, 5, array( 'ads' => 10 ), 'a' );
SPCW_DB::save_month( 2025, 5, array( 'ads' => 20 ), 'b' );
spcw_check( 'db: upsert keeps one row', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE period_year = 2025 AND period_month = 5', SPCW_DB::table() ) ), 1 );
spcw_check( 'db: upsert updates value', SPCW_DB::get_month( 2025, 5 )['costs']['ads'], 20.0 );
SPCW_DB::save_month( 2025, 6, array( 'ads' => -1e15 ), str_repeat( 'x', 5000 ) );
spcw_check( 'limits: negative amount clamped', SPCW_DB::get_month( 2025, 6 )['costs']['ads'], -SPCW_Settings::MAX_AMOUNT );
spcw_check( 'limits: note truncated', strlen( SPCW_DB::get_month( 2025, 6 )['notes'] ), SPCW_Settings::MAX_NOTE_CHARS );
SPCW_DB::save_month( 2025, 6, array(), '' );
spcw_check( 'db: empty month deletes row', SPCW_DB::get_month( 2025, 6 )['costs'], array() );

/* ── Input parsing ───────────────────────────────────────────── */
foreach ( array( '1 234,56' => 1234.56, '1.234,56' => 1234.56, '1,234.56' => 1234.56, '1.500' => 1500.0, '12,5' => 12.5, 'kr 99' => 99.0, '-50' => -50.0, '1.5' => 1.5, '' => null, 'abc' => null, '<b>7</b>' => 7.0, '1e309' => 1309.0 ) as $spcw_in => $spcw_exp ) {
	spcw_check( "parse '$spcw_in'", SPCW_Settings::parse_amount( (string) $spcw_in ), $spcw_exp );
}
spcw_check( 'parse: huge value clamped', SPCW_Settings::parse_amount( '999999999999999' ), SPCW_Settings::MAX_AMOUNT );

/* ── CSV injection ───────────────────────────────────────────── */
foreach ( array( '=HYPERLINK("x")', '+1+1', '@SUM(A1)', ' =cmd', "\t=cmd", "\n=cmd", '|calc', '-1+cmd' ) as $spcw_cell ) {
	spcw_check( 'csv: formula neutralised ' . wp_json_encode( $spcw_cell ), SPCW_Admin::csv_safe( $spcw_cell ), "'" . $spcw_cell );
}
spcw_check( 'csv: negative number kept', SPCW_Admin::csv_safe( '-12.50' ), '-12.50' );
spcw_check( 'csv: plain text kept', SPCW_Admin::csv_safe( 'Black Friday' ), 'Black Friday' );

/* ── Gateway matching ────────────────────────────────────────── */
spcw_check( 'gateway: empty method never matches', SPCW_Calculator::match_gateway( '', array( 'vipps' => array( 'percent' => 1 ) ) ), null );
spcw_check( 'gateway: v1 substring id matches', SPCW_Calculator::match_gateway( 'klarna_payments', array( 'klarna' => array( 'percent' => 2.5 ) ) )['percent'], 2.5 );
spcw_check( 'gateway: longest id wins', SPCW_Calculator::match_gateway( 'stripe_klarna', array( 'stripe' => array( 'percent' => 1.4 ), 'stripe_klarna' => array( 'percent' => 3 ) ) )['percent'], 3.0 );

/* ── Permissions ─────────────────────────────────────────────── */
wp_set_current_user( $spcw_customer );
spcw_check( 'permissions: customer cannot use plugin', current_user_can( SPCW_Settings::capability() ), false );
wp_set_current_user( $spcw_manager );
spcw_check( 'permissions: shop manager can use plugin', current_user_can( SPCW_Settings::capability() ), true );
spcw_check( 'security: shop manager cannot opt in to data deletion', SPCW_Settings::can_manage_data(), false );
wp_set_current_user( $spcw_admin );
spcw_check( 'permissions: admin can opt in to data deletion', SPCW_Settings::can_manage_data(), true );

/* ── Developer filters ───────────────────────────────────────── */
$spcw_f = function () {
	return 10;
};
add_filter( 'spcw_product_unit_cost', $spcw_f );
SPCW_Settings::bump_cache_version();
spcw_check( 'filter: spcw_product_unit_cost', SPCW_Calculator::month( 2026, 9 )['cogs'], 30.0 );
remove_filter( 'spcw_product_unit_cost', $spcw_f );
$spcw_f2 = function ( $r ) {
	$r['notes'] = 'filtered';
	return $r;
};
add_filter( 'spcw_month_result', $spcw_f2 );
spcw_check( 'filter: spcw_month_result', SPCW_Calculator::month( 2026, 9 )['notes'], 'filtered' );
remove_filter( 'spcw_month_result', $spcw_f2 );

/* ── COGS modes ──────────────────────────────────────────────── */
$spcw_s              = SPCW_Settings::all();
$spcw_s['cogs_mode'] = 'monthly';
SPCW_Settings::save( $spcw_s );
SPCW_DB::save_month( 2026, 9, array( '_cogs' => 300, 'shipping' => 450 ), '' );
spcw_check( 'COGS mode monthly lump sum', SPCW_Calculator::month( 2026, 9 )['cogs'], 300.0 );
$spcw_s['cogs_mode']    = 'percent';
$spcw_s['cogs_percent'] = 40;
SPCW_Settings::save( $spcw_s );
spcw_check( 'COGS mode percent (40% of 500)', SPCW_Calculator::month( 2026, 9 )['cogs'], 200.0 );

/* ── Uninstall ───────────────────────────────────────────────── */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'simple-profit-calculator-for-woocommerce/simple-profit-calculator-for-woocommerce.php' );
}
update_post_meta( $spcw_p, '_spcw_cost', '5' );
$spcw_s['delete_on_uninstall'] = false;
SPCW_Settings::save( $spcw_s );
include WP_PLUGIN_DIR . '/simple-profit-calculator-for-woocommerce/uninstall.php';
spcw_check( 'uninstall: keeps data when not opted in', SPCW_DB::table_exists() && false !== get_option( 'spcw_settings' ), true );
$spcw_s['delete_on_uninstall'] = true;
SPCW_Settings::save( $spcw_s );
spcw_uninstall_site();
spcw_check( 'uninstall: table dropped when opted in', SPCW_DB::table_exists(), false );
spcw_check( 'uninstall: settings deleted', get_option( 'spcw_settings' ), false );
spcw_check( 'uninstall: product cost meta deleted', get_post_meta( $spcw_p, '_spcw_cost', true ), '' );
spcw_check( 'uninstall: other plugins\' cost meta untouched', get_post_meta( $spcw_p, '_alg_wc_cog_cost', true ), '41' );

/* ── Report ──────────────────────────────────────────────────── */
$spcw_total = count( $GLOBALS['spcw_out'] );
$GLOBALS['spcw_out'][] = '';
$GLOBALS['spcw_out'][] = $GLOBALS['spcw_fail'] ? "{$GLOBALS['spcw_fail']} of $spcw_total FAILED" : "ALL $spcw_total PASSED";
file_put_contents( '/tests/results.txt', implode( "\n", $GLOBALS['spcw_out'] ) . "\n" );
if ( $GLOBALS['spcw_fail'] ) {
	throw new Exception( "{$GLOBALS['spcw_fail']} test(s) failed. See tests/results.txt." );
}
