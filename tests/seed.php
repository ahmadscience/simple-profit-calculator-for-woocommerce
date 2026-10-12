<?php
/**
 * Test fixtures: v1 data (for the import), products and orders.
 *
 * @package SimpleProfitCalculator
 */

require_once '/wordpress/wp-load.php';
global $wpdb;

// v1 ("Profit Calculator") data, to test the one-time import.
$spcw_t = $wpdb->prefix . 'profit_monthly_data';
$wpdb->query( "CREATE TABLE IF NOT EXISTS `$spcw_t` (`id` bigint(20) NOT NULL AUTO_INCREMENT,`year` smallint(4) NOT NULL,`month` tinyint(2) NOT NULL,`postal_cost` decimal(12,2) NOT NULL DEFAULT 0.00,`ads_spend` decimal(12,2) NOT NULL DEFAULT 0.00,`accountant_cost` decimal(12,2) NOT NULL DEFAULT 0.00,`packing_cost` decimal(12,2) NOT NULL DEFAULT 0.00,`saas_cost` decimal(12,2) NOT NULL DEFAULT 0.00,`notes` text,`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `year_month` (`year`,`month`))" );
$wpdb->query( "INSERT INTO `$spcw_t` (`year`,`month`,postal_cost,ads_spend,accountant_cost,packing_cost,saas_cost,notes) VALUES (2026,9,450.00,1200.00,0,80,299,'Sept legacy'),(2026,8,300,500,1500,0,299,'')" );
update_option( 'pc_settings', array( 'vat_rate' => 25, 'cost_meta_key' => '_alg_wc_cog_cost', 'order_statuses' => array( 'wc-completed', 'wc-processing' ) ) );
update_option( 'pc_gateway_fees', array( 'bacs' => array( 'label' => 'Bank', 'fee' => 0 ), 'cheque' => array( 'label' => 'Cheque legacy', 'fee' => 2.5 ), 'stripe' => array( 'label' => 'Stripe', 'fee' => 1.4 ) ) );

/**
 * Create a simple product.
 */
function spcw_test_product( $name, $price, $meta ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( $price );
	$p->set_status( 'publish' );
	foreach ( $meta as $k => $v ) {
		$p->update_meta_data( $k, $v );
	}
	return $p->save();
}

/**
 * Create an order with one line.
 */
function spcw_test_order( $pid, $qty, $status, $date, $pm, $pmt ) {
	$o = wc_create_order();
	$o->add_product( wc_get_product( $pid ), $qty );
	$o->set_payment_method( $pm );
	$o->set_payment_method_title( $pmt );
	$o->calculate_totals();
	$o->set_date_created( $date );
	$o->set_status( $status );
	$o->save();
	return $o;
}

$spcw_a = spcw_test_product( 'Tester A', '125', array( '_alg_wc_cog_cost' => '40' ) ); // Cost from the WPFactory key.
$spcw_b = spcw_test_product( 'Tester B', '250', array() );                           // No cost price.
update_option( 'spcw_seed_products', array( $spcw_a, $spcw_b ) );

$spcw_o1 = spcw_test_order( $spcw_a, 2, 'completed', '2026-09-10 12:00:00', 'cheque', 'Check payments' );
spcw_test_order( $spcw_b, 1, 'processing', '2026-09-15 12:00:00', 'bacs', 'Bank transfer' );
spcw_test_order( $spcw_a, 1, 'completed', '2026-09-20 12:00:00', '', '' );                  // No payment method.
spcw_test_order( $spcw_a, 1, 'completed', '2026-08-31 23:30:00', 'cheque', 'Check payments' ); // Last minutes of August.
spcw_test_order( $spcw_a, 3, 'cancelled', '2026-09-05 12:00:00', 'cheque', 'Check payments' ); // Not counted.

// Partial refund: 1 of the 2 items in order 1.
$spcw_items = $spcw_o1->get_items();
wc_create_refund(
	array(
		'order_id'   => $spcw_o1->get_id(),
		'amount'     => 125,
		'line_items' => array( array_key_first( $spcw_items ) => array( 'qty' => 1, 'refund_total' => 125 ) ),
	)
);
