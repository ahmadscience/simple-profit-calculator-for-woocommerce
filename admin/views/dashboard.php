<?php
/**
 * Dashboard view: monthly breakdown or yearly overview.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only navigation parameters.
$spcw_view  = isset( $_GET['spcw_view'] ) && 'yearly' === sanitize_key( wp_unslash( $_GET['spcw_view'] ) ) ? 'yearly' : 'monthly';
$spcw_year  = SPCW_Admin::clamp_year( isset( $_GET['spcw_year'] ) ? absint( $_GET['spcw_year'] ) : 0 );
$spcw_month = SPCW_Admin::clamp_month( isset( $_GET['spcw_month'] ) ? absint( $_GET['spcw_month'] ) : 0 );
// phpcs:enable

$spcw_cats      = SPCW_Settings::categories();
$spcw_cogs_mode = (string) SPCW_Settings::get( 'cogs_mode' );
$spcw_costs_url = SPCW_Admin::url( SPCW_Admin::SLUG_COSTS, array( 'spcw_year' => $spcw_year ) );
?>
<div class="wrap spcw-wrap">
	<h1><?php esc_html_e( 'Profit dashboard', 'simple-profit-calculator-for-woocommerce' ); ?></h1>
	<?php SPCW_Admin::render_notices(); ?>

	<div class="spcw-toolbar">
		<nav class="spcw-tabs" aria-label="<?php esc_attr_e( 'View', 'simple-profit-calculator-for-woocommerce' ); ?>">
			<a href="
			<?php
			echo esc_url(
				SPCW_Admin::url(
					SPCW_Admin::SLUG_DASHBOARD,
					array(
						'spcw_year'  => $spcw_year,
						'spcw_month' => $spcw_month,
					)
				)
			);
			?>
			" class="spcw-tab<?php echo 'monthly' === $spcw_view ? ' is-active' : ''; ?>"><?php esc_html_e( 'Month', 'simple-profit-calculator-for-woocommerce' ); ?></a>
			<a href="
			<?php
			echo esc_url(
				SPCW_Admin::url(
					SPCW_Admin::SLUG_DASHBOARD,
					array(
						'spcw_view' => 'yearly',
						'spcw_year' => $spcw_year,
					)
				)
			);
			?>
			" class="spcw-tab<?php echo 'yearly' === $spcw_view ? ' is-active' : ''; ?>"><?php esc_html_e( 'Year', 'simple-profit-calculator-for-woocommerce' ); ?></a>
		</nav>

		<form method="get" class="spcw-period">
			<input type="hidden" name="page" value="<?php echo esc_attr( SPCW_Admin::SLUG_DASHBOARD ); ?>">
			<?php if ( 'yearly' === $spcw_view ) : ?>
				<input type="hidden" name="spcw_view" value="yearly">
			<?php else : ?>
				<label class="screen-reader-text" for="spcw-month"><?php esc_html_e( 'Month', 'simple-profit-calculator-for-woocommerce' ); ?></label>
				<select id="spcw-month" name="spcw_month" class="spcw-autosubmit">
					<?php for ( $spcw_m = 1; $spcw_m <= 12; $spcw_m++ ) : ?>
						<option value="<?php echo esc_attr( $spcw_m ); ?>" <?php selected( $spcw_m, $spcw_month ); ?>><?php echo esc_html( SPCW_Admin::month_name( $spcw_m ) ); ?></option>
					<?php endfor; ?>
				</select>
			<?php endif; ?>
			<label class="screen-reader-text" for="spcw-year"><?php esc_html_e( 'Year', 'simple-profit-calculator-for-woocommerce' ); ?></label>
			<select id="spcw-year" name="spcw_year" class="spcw-autosubmit">
				<?php foreach ( SPCW_Admin::year_options() as $spcw_y ) : ?>
					<option value="<?php echo esc_attr( $spcw_y ); ?>" <?php selected( $spcw_y, $spcw_year ); ?>><?php echo esc_html( $spcw_y ); ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'Show', 'simple-profit-calculator-for-woocommerce' ); ?></button></noscript>
		</form>

		<div class="spcw-toolbar-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'spcw_refresh' ); ?>
				<input type="hidden" name="action" value="spcw_refresh">
				<input type="hidden" name="spcw_view" value="<?php echo esc_attr( $spcw_view ); ?>">
				<input type="hidden" name="spcw_year" value="<?php echo esc_attr( $spcw_year ); ?>">
				<input type="hidden" name="spcw_month" value="<?php echo esc_attr( $spcw_month ); ?>">
				<button type="submit" class="button" title="<?php esc_attr_e( 'Recalculate from WooCommerce orders', 'simple-profit-calculator-for-woocommerce' ); ?>"><?php esc_html_e( 'Refresh data', 'simple-profit-calculator-for-woocommerce' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'spcw_export' ); ?>
				<input type="hidden" name="action" value="spcw_export">
				<input type="hidden" name="spcw_year" value="<?php echo esc_attr( $spcw_year ); ?>">
				<button type="submit" class="button">
					<?php
					/* translators: %d: year */
					echo esc_html( sprintf( __( 'Export %d (CSV)', 'simple-profit-calculator-for-woocommerce' ), $spcw_year ) );
					?>
				</button>
			</form>
		</div>
	</div>

<?php if ( 'monthly' === $spcw_view ) : ?>
	<?php
	$spcw_d      = SPCW_Calculator::month( $spcw_year, $spcw_month );
	$spcw_manual = SPCW_DB::get_month( $spcw_year, $spcw_month );
	?>
	<h2 class="spcw-period-title">
		<?php echo esc_html( SPCW_Admin::month_name( $spcw_month ) . ' ' . $spcw_year ); ?>
		<span class="spcw-pill">
			<?php
			/* translators: %s: number of orders */
			echo esc_html( sprintf( _n( '%s order', '%s orders', $spcw_d['order_count'], 'simple-profit-calculator-for-woocommerce' ), number_format_i18n( $spcw_d['order_count'] ) ) );
			?>
		</span>
	</h2>

	<?php if ( $spcw_d['items_missing_cost'] > 0 ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				/* translators: %s: number of items */
				echo esc_html( sprintf( _n( '%s item sold this month has no cost price, so profit is higher than it really is.', '%s items sold this month have no cost price, so profit is higher than it really is.', $spcw_d['items_missing_cost'], 'simple-profit-calculator-for-woocommerce' ), number_format_i18n( $spcw_d['items_missing_cost'] ) ) );
				?>
				<a href="<?php echo esc_url( SPCW_Admin::url( SPCW_Admin::SLUG_SETTINGS ) . '#spcw-cogs' ); ?>"><?php esc_html_e( 'Product cost settings', 'simple-profit-calculator-for-woocommerce' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<div class="spcw-cards">
		<div class="spcw-card">
			<div class="spcw-card-label"><?php esc_html_e( 'Net sales', 'simple-profit-calculator-for-woocommerce' ); ?></div>
			<div class="spcw-card-value"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['net_sales'] ) ); ?></div>
			<div class="spcw-card-sub"><?php esc_html_e( 'after refunds and tax', 'simple-profit-calculator-for-woocommerce' ); ?></div>
		</div>
		<div class="spcw-card spcw-card--costs">
			<div class="spcw-card-label"><?php esc_html_e( 'Total costs', 'simple-profit-calculator-for-woocommerce' ); ?></div>
			<div class="spcw-card-value"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['total_costs'] ) ); ?></div>
			<div class="spcw-card-sub"><?php esc_html_e( 'products, fees and expenses', 'simple-profit-calculator-for-woocommerce' ); ?></div>
		</div>
		<div class="spcw-card <?php echo $spcw_d['profit'] >= 0 ? 'spcw-card--profit' : 'spcw-card--loss'; ?>">
			<div class="spcw-card-label"><?php esc_html_e( 'Profit', 'simple-profit-calculator-for-woocommerce' ); ?></div>
			<div class="spcw-card-value"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['profit'] ) ); ?></div>
			<div class="spcw-card-sub">
				<?php
				/* translators: %s: profit margin, e.g. 23.5% */
				echo esc_html( sprintf( __( 'Margin %s', 'simple-profit-calculator-for-woocommerce' ), SPCW_Admin::margin_text( $spcw_d['margin'] ) ) );
				?>
			</div>
		</div>
	</div>

	<div class="spcw-columns">
		<table class="widefat spcw-breakdown">
			<tbody>
				<tr class="spcw-section"><th colspan="2"><?php esc_html_e( 'Sales', 'simple-profit-calculator-for-woocommerce' ); ?></th></tr>
				<tr><td><?php esc_html_e( 'Gross sales', 'simple-profit-calculator-for-woocommerce' ); ?> <span class="description"><?php esc_html_e( '(incl. tax and shipping)', 'simple-profit-calculator-for-woocommerce' ); ?></span></td><td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['gross'] ) ); ?></td></tr>
				<?php if ( $spcw_d['refunds'] > 0 ) : ?>
				<tr><td><?php esc_html_e( 'Refunds', 'simple-profit-calculator-for-woocommerce' ); ?></td><td class="spcw-num spcw-neg">−<?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['refunds'] ) ); ?></td></tr>
				<?php endif; ?>
				<tr><td><?php esc_html_e( 'Tax (VAT)', 'simple-profit-calculator-for-woocommerce' ); ?></td><td class="spcw-num spcw-neg">−<?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['tax'] ) ); ?></td></tr>
				<tr class="spcw-subtotal"><td><?php esc_html_e( 'Net sales', 'simple-profit-calculator-for-woocommerce' ); ?></td><td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['net_sales'] ) ); ?></td></tr>

				<tr class="spcw-section"><th colspan="2"><?php esc_html_e( 'Costs', 'simple-profit-calculator-for-woocommerce' ); ?></th></tr>
				<tr>
					<td>
						<?php esc_html_e( 'Product cost (COGS)', 'simple-profit-calculator-for-woocommerce' ); ?>
						<span class="description">
							<?php
							if ( SPCW_Settings::COGS_MONTHLY === $spcw_cogs_mode ) {
								esc_html_e( '(monthly amount you entered)', 'simple-profit-calculator-for-woocommerce' );
							} elseif ( SPCW_Settings::COGS_PERCENT === $spcw_cogs_mode ) {
								/* translators: %s: percentage */
								echo esc_html( sprintf( __( '(estimated at %s%% of product sales)', 'simple-profit-calculator-for-woocommerce' ), number_format_i18n( (float) SPCW_Settings::get( 'cogs_percent' ), 1 ) ) );
							} else {
								esc_html_e( '(from product cost prices)', 'simple-profit-calculator-for-woocommerce' );
							}
							?>
						</span>
					</td>
					<td class="spcw-num spcw-neg">−<?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['cogs'] ) ); ?></td>
				</tr>
				<tr>
					<td>
						<?php esc_html_e( 'Payment fees', 'simple-profit-calculator-for-woocommerce' ); ?>
						<?php if ( $spcw_d['fee_breakdown'] ) : ?>
							<ul class="spcw-fee-list">
								<?php foreach ( $spcw_d['fee_breakdown'] as $spcw_fee ) : ?>
									<li>
										<?php
										echo esc_html( $spcw_fee['label'] ) . ': ';
										echo wp_kses_post( SPCW_Admin::money( $spcw_fee['amount'] ) );
										if ( $spcw_fee['unset'] ) {
											echo ' <a href="' . esc_url( SPCW_Admin::url( SPCW_Admin::SLUG_SETTINGS ) . '#spcw-fees' ) . '">' . esc_html__( 'set fee', 'simple-profit-calculator-for-woocommerce' ) . '</a>';
										}
										?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
					<td class="spcw-num spcw-neg">−<?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['payment_fees'] ) ); ?></td>
				</tr>
				<?php foreach ( $spcw_cats as $spcw_key => $spcw_label ) : ?>
				<tr>
					<td>
						<?php echo esc_html( $spcw_label ); ?>
						<?php if ( SPCW_Settings::SHIPPING_CATEGORY === $spcw_key && $spcw_d['shipping_charged'] > 0 ) : ?>
							<span class="description">
								<?php
								$spcw_ship_margin = $spcw_d['shipping_charged'] - $spcw_d['expenses'][ $spcw_key ];
								/* translators: 1: shipping charged to customers, 2: difference vs actual shipping cost */
								echo wp_kses_post( sprintf( esc_html__( '(customers paid %1$s, difference %2$s)', 'simple-profit-calculator-for-woocommerce' ), SPCW_Admin::money( $spcw_d['shipping_charged'] ), SPCW_Admin::money( $spcw_ship_margin ) ) );
								?>
							</span>
						<?php endif; ?>
					</td>
					<td class="spcw-num spcw-neg">−<?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['expenses'][ $spcw_key ] ) ); ?></td>
				</tr>
				<?php endforeach; ?>
				<tr class="spcw-subtotal"><td><?php esc_html_e( 'Total costs', 'simple-profit-calculator-for-woocommerce' ); ?></td><td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['total_costs'] ) ); ?></td></tr>

				<tr class="spcw-result <?php echo $spcw_d['profit'] >= 0 ? 'is-profit' : 'is-loss'; ?>">
					<td><?php esc_html_e( 'Profit', 'simple-profit-calculator-for-woocommerce' ); ?></td>
					<td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['profit'] ) ); ?></td>
				</tr>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spcw-panel spcw-track-changes">
			<h3>
				<?php
				/* translators: %s: month and year */
				echo esc_html( sprintf( __( 'Costs for %s', 'simple-profit-calculator-for-woocommerce' ), SPCW_Admin::month_name( $spcw_month ) . ' ' . $spcw_year ) );
				?>
			</h3>
			<?php wp_nonce_field( 'spcw_save_costs' ); ?>
			<input type="hidden" name="action" value="spcw_save_costs">
			<input type="hidden" name="spcw_year" value="<?php echo esc_attr( $spcw_year ); ?>">
			<input type="hidden" name="spcw_month" value="<?php echo esc_attr( $spcw_month ); ?>">
			<input type="hidden" name="spcw_redirect" value="dashboard">

			<?php if ( SPCW_Settings::COGS_MONTHLY === $spcw_cogs_mode ) : ?>
				<p>
					<label for="spcw-q-cogs"><?php esc_html_e( 'Product cost (COGS)', 'simple-profit-calculator-for-woocommerce' ); ?></label>
					<input type="text" inputmode="decimal" id="spcw-q-cogs" name="spcw_costs[<?php echo esc_attr( $spcw_month ); ?>][<?php echo esc_attr( SPCW_Calculator::COGS_KEY ); ?>]" value="<?php echo esc_attr( SPCW_Admin::input_value( $spcw_manual['costs'], SPCW_Calculator::COGS_KEY ) ); ?>" placeholder="0">
				</p>
			<?php endif; ?>
			<?php foreach ( $spcw_cats as $spcw_key => $spcw_label ) : ?>
				<p>
					<label for="spcw-q-<?php echo esc_attr( $spcw_key ); ?>"><?php echo esc_html( $spcw_label ); ?></label>
					<input type="text" inputmode="decimal" id="spcw-q-<?php echo esc_attr( $spcw_key ); ?>" name="spcw_costs[<?php echo esc_attr( $spcw_month ); ?>][<?php echo esc_attr( $spcw_key ); ?>]" value="<?php echo esc_attr( SPCW_Admin::input_value( $spcw_manual['costs'], $spcw_key ) ); ?>" placeholder="0">
				</p>
			<?php endforeach; ?>
			<p>
				<label for="spcw-q-notes"><?php esc_html_e( 'Notes', 'simple-profit-calculator-for-woocommerce' ); ?></label>
				<textarea id="spcw-q-notes" name="spcw_notes[<?php echo esc_attr( $spcw_month ); ?>]" rows="2"><?php echo esc_textarea( $spcw_manual['notes'] ); ?></textarea>
			</p>
			<p class="spcw-panel-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save costs', 'simple-profit-calculator-for-woocommerce' ); ?></button>
				<a href="<?php echo esc_url( $spcw_costs_url ); ?>"><?php esc_html_e( 'Edit all months', 'simple-profit-calculator-for-woocommerce' ); ?></a>
			</p>
		</form>
	</div>

<?php else : ?>
	<?php
	$spcw_data   = SPCW_Calculator::year( $spcw_year );
	$spcw_totals = $spcw_data['totals'];
	?>
	<h2 class="spcw-period-title">
		<?php echo esc_html( $spcw_year ); ?>
		<span class="spcw-pill">
			<?php
			/* translators: %s: number of orders */
			echo esc_html( sprintf( _n( '%s order', '%s orders', $spcw_totals['order_count'], 'simple-profit-calculator-for-woocommerce' ), number_format_i18n( $spcw_totals['order_count'] ) ) );
			?>
		</span>
	</h2>

	<?php if ( $spcw_totals['items_missing_cost'] > 0 ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php
				/* translators: %s: number of items */
				echo esc_html( sprintf( _n( '%s item sold this year has no cost price.', '%s items sold this year have no cost price.', $spcw_totals['items_missing_cost'], 'simple-profit-calculator-for-woocommerce' ), number_format_i18n( $spcw_totals['items_missing_cost'] ) ) );
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="spcw-cards">
		<div class="spcw-card">
			<div class="spcw-card-label"><?php esc_html_e( 'Net sales', 'simple-profit-calculator-for-woocommerce' ); ?></div>
			<div class="spcw-card-value"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_totals['net_sales'] ) ); ?></div>
		</div>
		<div class="spcw-card spcw-card--costs">
			<div class="spcw-card-label"><?php esc_html_e( 'Total costs', 'simple-profit-calculator-for-woocommerce' ); ?></div>
			<div class="spcw-card-value"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_totals['total_costs'] ) ); ?></div>
		</div>
		<div class="spcw-card <?php echo $spcw_totals['profit'] >= 0 ? 'spcw-card--profit' : 'spcw-card--loss'; ?>">
			<div class="spcw-card-label"><?php esc_html_e( 'Profit', 'simple-profit-calculator-for-woocommerce' ); ?></div>
			<div class="spcw-card-value"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_totals['profit'] ) ); ?></div>
			<div class="spcw-card-sub">
				<?php
				/* translators: %s: profit margin */
				echo esc_html( sprintf( __( 'Margin %s', 'simple-profit-calculator-for-woocommerce' ), SPCW_Admin::margin_text( $spcw_totals['margin'] ) ) );
				?>
			</div>
		</div>
	</div>

	<div class="spcw-scroll">
		<table class="widefat striped spcw-year">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Month', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<th scope="col" class="spcw-num"><?php esc_html_e( 'Orders', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<th scope="col" class="spcw-num"><?php esc_html_e( 'Net sales', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<th scope="col" class="spcw-num"><?php esc_html_e( 'Product cost', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<th scope="col" class="spcw-num"><?php esc_html_e( 'Payment fees', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<?php foreach ( $spcw_cats as $spcw_label ) : ?>
						<th scope="col" class="spcw-num"><?php echo esc_html( $spcw_label ); ?></th>
					<?php endforeach; ?>
					<th scope="col" class="spcw-num"><?php esc_html_e( 'Profit', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<th scope="col" class="spcw-num"><?php esc_html_e( 'Margin', 'simple-profit-calculator-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $spcw_data['months'] as $spcw_m => $spcw_d ) : ?>
				<tr class="<?php echo 0 === $spcw_d['order_count'] && 0.0 === (float) $spcw_d['total_costs'] ? 'spcw-muted' : ''; ?>">
					<th scope="row"><a href="
					<?php
					echo esc_url(
						SPCW_Admin::url(
							SPCW_Admin::SLUG_DASHBOARD,
							array(
								'spcw_year'  => $spcw_year,
								'spcw_month' => $spcw_m,
							)
						)
					);
					?>
												"><?php echo esc_html( SPCW_Admin::month_name( $spcw_m ) ); ?></a></th>
					<td class="spcw-num"><?php echo esc_html( number_format_i18n( $spcw_d['order_count'] ) ); ?></td>
					<td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['net_sales'] ) ); ?></td>
					<td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['cogs'] ) ); ?></td>
					<td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['payment_fees'] ) ); ?></td>
					<?php foreach ( array_keys( $spcw_cats ) as $spcw_key ) : ?>
						<td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['expenses'][ $spcw_key ] ) ); ?></td>
					<?php endforeach; ?>
					<td class="spcw-num <?php echo $spcw_d['profit'] >= 0 ? 'spcw-pos' : 'spcw-neg'; ?>"><strong><?php echo wp_kses_post( SPCW_Admin::money( $spcw_d['profit'] ) ); ?></strong></td>
					<td class="spcw-num"><?php echo esc_html( SPCW_Admin::margin_text( $spcw_d['margin'] ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="row"><?php esc_html_e( 'Total', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<td class="spcw-num"><?php echo esc_html( number_format_i18n( $spcw_totals['order_count'] ) ); ?></td>
					<td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_totals['net_sales'] ) ); ?></td>
					<td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_totals['cogs'] ) ); ?></td>
					<td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_totals['payment_fees'] ) ); ?></td>
					<?php foreach ( array_keys( $spcw_cats ) as $spcw_key ) : ?>
						<td class="spcw-num"><?php echo wp_kses_post( SPCW_Admin::money( $spcw_totals['expenses'][ $spcw_key ] ) ); ?></td>
					<?php endforeach; ?>
					<td class="spcw-num <?php echo $spcw_totals['profit'] >= 0 ? 'spcw-pos' : 'spcw-neg'; ?>"><strong><?php echo wp_kses_post( SPCW_Admin::money( $spcw_totals['profit'] ) ); ?></strong></td>
					<td class="spcw-num"><?php echo esc_html( SPCW_Admin::margin_text( $spcw_totals['margin'] ) ); ?></td>
				</tr>
			</tfoot>
		</table>
	</div>
<?php endif; ?>
</div>
