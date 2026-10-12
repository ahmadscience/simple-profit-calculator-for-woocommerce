<?php
/**
 * Monthly costs view: the whole year in one form, saved with one button.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameter.
$spcw_year = SPCW_Admin::clamp_year( isset( $_GET['spcw_year'] ) ? absint( $_GET['spcw_year'] ) : 0 );

$spcw_rows    = SPCW_DB::get_year( $spcw_year );
$spcw_columns = array();
if ( SPCW_Settings::COGS_MONTHLY === SPCW_Settings::get( 'cogs_mode' ) ) {
	$spcw_columns[ SPCW_Calculator::COGS_KEY ] = __( 'Product cost (COGS)', 'simple-profit-calculator-for-woocommerce' );
}
$spcw_columns += SPCW_Settings::categories();

$spcw_now_y = (int) current_time( 'Y' );
$spcw_now_m = (int) current_time( 'n' );
?>
<div class="wrap spcw-wrap">
	<h1><?php esc_html_e( 'Monthly costs', 'simple-profit-calculator-for-woocommerce' ); ?></h1>
	<?php SPCW_Admin::render_notices(); ?>

	<p class="spcw-intro">
		<?php esc_html_e( 'Enter what each month really cost you, excluding VAT. Leave a field empty if there was no cost. Change as many months as you like, then click Save once.', 'simple-profit-calculator-for-woocommerce' ); ?>
		<a href="<?php echo esc_url( SPCW_Admin::url( SPCW_Admin::SLUG_SETTINGS ) . '#spcw-categories' ); ?>"><?php esc_html_e( 'Add or rename cost types', 'simple-profit-calculator-for-woocommerce' ); ?></a>
	</p>

	<form method="get" class="spcw-period">
		<input type="hidden" name="page" value="<?php echo esc_attr( SPCW_Admin::SLUG_COSTS ); ?>">
		<label for="spcw-year"><?php esc_html_e( 'Year', 'simple-profit-calculator-for-woocommerce' ); ?></label>
		<select id="spcw-year" name="spcw_year" class="spcw-autosubmit">
			<?php foreach ( SPCW_Admin::year_options() as $spcw_y ) : ?>
				<option value="<?php echo esc_attr( $spcw_y ); ?>" <?php selected( $spcw_y, $spcw_year ); ?>><?php echo esc_html( $spcw_y ); ?></option>
			<?php endforeach; ?>
			<?php if ( $spcw_year > (int) current_time( 'Y' ) ) : ?>
				<option value="<?php echo esc_attr( $spcw_year ); ?>" selected><?php echo esc_html( $spcw_year ); ?></option>
			<?php endif; ?>
		</select>
		<noscript><button type="submit" class="button"><?php esc_html_e( 'Show', 'simple-profit-calculator-for-woocommerce' ); ?></button></noscript>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spcw-track-changes" id="spcw-costs-form">
		<?php wp_nonce_field( 'spcw_save_costs' ); ?>
		<input type="hidden" name="action" value="spcw_save_costs">
		<input type="hidden" name="spcw_year" value="<?php echo esc_attr( $spcw_year ); ?>">

		<div class="spcw-scroll">
			<table class="widefat spcw-costs">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Month', 'simple-profit-calculator-for-woocommerce' ); ?></th>
						<?php foreach ( $spcw_columns as $spcw_label ) : ?>
							<th scope="col"><?php echo esc_html( $spcw_label ); ?></th>
						<?php endforeach; ?>
						<th scope="col" class="spcw-num"><?php esc_html_e( 'Total', 'simple-profit-calculator-for-woocommerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Notes', 'simple-profit-calculator-for-woocommerce' ); ?></th>
						<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'simple-profit-calculator-for-woocommerce' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
				<?php for ( $spcw_m = 1; $spcw_m <= 12; $spcw_m++ ) : ?>
					<?php
					$spcw_row     = $spcw_rows[ $spcw_m ];
					$spcw_classes = array();
					if ( $spcw_year === $spcw_now_y && $spcw_m === $spcw_now_m ) {
						$spcw_classes[] = 'is-current';
					}
					if ( $spcw_year > $spcw_now_y || ( $spcw_year === $spcw_now_y && $spcw_m > $spcw_now_m ) ) {
						$spcw_classes[] = 'is-future';
					}
					$spcw_month_name = SPCW_Admin::month_name( $spcw_m );
					?>
					<tr class="<?php echo esc_attr( implode( ' ', $spcw_classes ) ); ?>" data-month="<?php echo esc_attr( $spcw_m ); ?>">
						<th scope="row"><?php echo esc_html( $spcw_month_name ); ?></th>
						<?php foreach ( $spcw_columns as $spcw_key => $spcw_label ) : ?>
							<td>
								<input type="text" inputmode="decimal" class="spcw-amount"
									name="spcw_costs[<?php echo esc_attr( $spcw_m ); ?>][<?php echo esc_attr( $spcw_key ); ?>]"
									value="<?php echo esc_attr( SPCW_Admin::input_value( $spcw_row['costs'], $spcw_key ) ); ?>"
									placeholder="0"
									aria-label="<?php echo esc_attr( $spcw_label . ' – ' . $spcw_month_name ); ?>">
							</td>
						<?php endforeach; ?>
						<td class="spcw-num spcw-row-total" aria-live="polite"></td>
						<td>
							<input type="text" class="spcw-note" name="spcw_notes[<?php echo esc_attr( $spcw_m ); ?>]"
								value="<?php echo esc_attr( $spcw_row['notes'] ); ?>"
								aria-label="<?php echo esc_attr( __( 'Notes', 'simple-profit-calculator-for-woocommerce' ) . ' – ' . $spcw_month_name ); ?>">
						</td>
						<td>
							<?php if ( $spcw_m > 1 ) : ?>
								<button type="button" class="button-link spcw-copy-prev">
									<?php esc_html_e( 'Copy previous', 'simple-profit-calculator-for-woocommerce' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endfor; ?>
				</tbody>
			</table>
		</div>

		<div class="spcw-savebar">
			<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save all months', 'simple-profit-calculator-for-woocommerce' ); ?></button>
			<span class="spcw-dirty-hint" hidden><?php esc_html_e( 'Unsaved changes', 'simple-profit-calculator-for-woocommerce' ); ?></span>
		</div>
	</form>
</div>
