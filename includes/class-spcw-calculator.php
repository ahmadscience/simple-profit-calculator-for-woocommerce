<?php
/**
 * Combines sales, product costs, payment fees and manual expenses into profit.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Profit maths. All amounts are in the store currency.
 *
 * Formulas:
 *   net_sales    = gross − refunds − tax
 *   total_costs  = cogs + payment_fees + Σ expenses
 *   profit       = net_sales − total_costs
 */
class SPCW_Calculator {

	/** Key in the monthly cost data that holds the lump-sum COGS. */
	const COGS_KEY = '_cogs';

	/**
	 * Profit for one month.
	 *
	 * @param int $year  Year.
	 * @param int $month Month 1-12.
	 */
	public static function month( int $year, int $month ): array {
		$sales  = SPCW_Orders::get_month( $year, $month );
		$manual = SPCW_DB::get_month( $year, $month );

		$net_sales = round( $sales['gross'] - $sales['refunds'] - $sales['tax'], 2 );

		// Product cost.
		$mode = (string) SPCW_Settings::get( 'cogs_mode' );
		if ( SPCW_Settings::COGS_MONTHLY === $mode ) {
			$cogs = (float) ( $manual['costs'][ self::COGS_KEY ] ?? 0 );
		} elseif ( SPCW_Settings::COGS_PERCENT === $mode ) {
			$product_sales = max( 0.0, $net_sales - $sales['shipping'] );
			$cogs          = round( $product_sales * (float) SPCW_Settings::get( 'cogs_percent' ) / 100, 2 );
		} else {
			$cogs = (float) $sales['cogs'];
		}

		// Payment fees: percentage of the amount paid + fixed fee per order.
		$fee_settings = SPCW_Settings::gateways();
		$fees_total   = 0.0;
		$fees         = array();
		foreach ( $sales['gateways'] as $id => $gw ) {
			$conf    = self::match_gateway( (string) $id, $fee_settings );
			$percent = $conf ? (float) $conf['percent'] : 0.0;
			$fixed   = $conf ? (float) $conf['fixed'] : 0.0;
			$amount  = round( $gw['gross'] * $percent / 100 + $fixed * $gw['orders'], 2 );

			$fees_total += $amount;
			$fees[ $id ] = array(
				'label'   => ( $conf && '' !== $conf['label'] ) ? $conf['label'] : $gw['label'],
				'gross'   => $gw['gross'],
				'orders'  => $gw['orders'],
				'percent' => $percent,
				'fixed'   => $fixed,
				'amount'  => $amount,
				// Payment method has a real id but no fee settings yet.
				'unset'   => '' !== (string) $id && null === $conf,
			);
		}

		// Manual expenses for the configured categories.
		$expenses = array();
		foreach ( SPCW_Settings::categories() as $key => $label ) {
			$expenses[ $key ] = (float) ( $manual['costs'][ $key ] ?? 0 );
		}
		$expenses_total = array_sum( $expenses );

		$total_costs = round( $cogs + $fees_total + $expenses_total, 2 );
		$profit      = round( $net_sales - $total_costs, 2 );

		$result = array(
			'order_count'        => (int) $sales['order_count'],
			'gross'              => (float) $sales['gross'],
			'refunds'            => (float) $sales['refunds'],
			'tax'                => (float) $sales['tax'],
			'net_sales'          => $net_sales,
			'shipping_charged'   => (float) $sales['shipping'],
			'cogs'               => round( $cogs, 2 ),
			'items_missing_cost' => SPCW_Settings::COGS_PRODUCT === $mode ? (int) $sales['items_missing_cost'] : 0,
			'payment_fees'       => round( $fees_total, 2 ),
			'fee_breakdown'      => $fees,
			'expenses'           => $expenses,
			'expenses_total'     => round( $expenses_total, 2 ),
			'total_costs'        => $total_costs,
			'profit'             => $profit,
			'margin'             => self::margin( $profit, $net_sales ),
			'notes'              => $manual['notes'],
		);

		/**
		 * Filters the calculated figures for one month (also used for the yearly totals and CSV).
		 * If you change amounts, keep 'total_costs', 'profit' and 'margin' consistent.
		 *
		 * @since 2.0.1
		 *
		 * @param array $result Figures, see SPCW_Calculator::month().
		 * @param int   $year   Year.
		 * @param int   $month  Month 1-12.
		 */
		return (array) apply_filters( 'spcw_month_result', $result, $year, $month );
	}

	/**
	 * Profit for every month of a year, plus totals.
	 *
	 * @param int $year Year.
	 */
	public static function year( int $year ): array {
		$months = array();
		$sum    = array( 'order_count', 'gross', 'refunds', 'tax', 'net_sales', 'shipping_charged', 'cogs', 'items_missing_cost', 'payment_fees', 'expenses_total', 'total_costs', 'profit' );
		$totals = array_fill_keys( $sum, 0 );

		$totals['expenses'] = array_fill_keys( array_keys( SPCW_Settings::categories() ), 0.0 );

		for ( $m = 1; $m <= 12; $m++ ) {
			$d            = self::month( $year, $m );
			$months[ $m ] = $d;
			foreach ( $sum as $k ) {
				$totals[ $k ] += $d[ $k ];
			}
			foreach ( $d['expenses'] as $k => $v ) {
				$totals['expenses'][ $k ] += $v;
			}
		}
		foreach ( $sum as $k ) {
			if ( is_float( $totals[ $k ] ) ) {
				$totals[ $k ] = round( $totals[ $k ], 2 );
			}
		}
		$totals['margin'] = self::margin( $totals['profit'], $totals['net_sales'] );

		return array(
			'months' => $months,
			'totals' => $totals,
		);
	}

	/**
	 * Profit as % of net sales, or null when there were no sales.
	 *
	 * @param float $profit    Profit.
	 * @param float $net_sales Net sales.
	 * @return float|null
	 */
	public static function margin( $profit, $net_sales ) {
		return abs( (float) $net_sales ) < 0.005 ? null : round( $profit / $net_sales * 100, 1 );
	}

	/**
	 * Find the fee settings for a WooCommerce payment method id.
	 *
	 * Exact id first. Otherwise the longest configured id contained in the
	 * method id (so "klarna" still matches "klarna_payments" for settings saved
	 * by version 1). Orders without a payment method never match.
	 *
	 * @param string $method   Payment method id from the order.
	 * @param array  $settings Configured gateways.
	 * @return array|null
	 */
	public static function match_gateway( string $method, array $settings ) {
		if ( '' === $method ) {
			return null;
		}
		if ( isset( $settings[ $method ] ) ) {
			return self::normalise_gateway( $settings[ $method ] );
		}
		$best = null;
		foreach ( $settings as $id => $conf ) {
			$id = (string) $id;
			if ( '' !== $id && false !== strpos( $method, $id ) && ( null === $best || strlen( $id ) > strlen( $best ) ) ) {
				$best = $id;
			}
		}
		return null === $best ? null : self::normalise_gateway( $settings[ $best ] );
	}

	/**
	 * Fill missing gateway fields.
	 *
	 * @param mixed $conf Stored config.
	 */
	private static function normalise_gateway( $conf ): array {
		$conf = is_array( $conf ) ? $conf : array();
		return array(
			'label'   => isset( $conf['label'] ) ? (string) $conf['label'] : '',
			'percent' => isset( $conf['percent'] ) ? (float) $conf['percent'] : 0.0,
			'fixed'   => isset( $conf['fixed'] ) ? (float) $conf['fixed'] : 0.0,
		);
	}
}
