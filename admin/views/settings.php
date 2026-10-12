<?php
/**
 * Settings view.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

$spcw_settings = SPCW_Settings::all();
$spcw_statuses = SPCW_Settings::order_statuses();
$spcw_detected = SPCW_Cogs::detect();
$spcw_sources  = SPCW_Cogs::source_labels();
$spcw_saved_gw = SPCW_Settings::gateways();

// Every payment method WooCommerce knows about, plus any saved ids that are no longer installed.
$spcw_gateways = array();
foreach ( WC()->payment_gateways()->payment_gateways() as $spcw_gw_id => $spcw_gw ) {
	$spcw_conf                    = SPCW_Calculator::match_gateway( (string) $spcw_gw_id, $spcw_saved_gw );
	$spcw_gateways[ $spcw_gw_id ] = array(
		'name'      => wp_strip_all_tags( $spcw_gw->get_method_title() ? $spcw_gw->get_method_title() : $spcw_gw->get_title() ),
		'enabled'   => 'yes' === $spcw_gw->enabled,
		'installed' => true,
		'conf'      => $spcw_conf ? $spcw_conf : array(
			'label'   => '',
			'percent' => 0,
			'fixed'   => 0,
		),
	);
}
foreach ( $spcw_saved_gw as $spcw_gw_id => $spcw_conf ) {
	$spcw_gw_id = (string) $spcw_gw_id;
	if ( isset( $spcw_gateways[ $spcw_gw_id ] ) ) {
		continue;
	}
	// v1 ids like "klarna" are folded into the installed gateway they matched.
	$spcw_folded = false;
	foreach ( array_keys( $spcw_gateways ) as $spcw_installed ) {
		if ( false !== strpos( (string) $spcw_installed, $spcw_gw_id ) ) {
			$spcw_folded = true;
			break;
		}
	}
	if ( $spcw_folded ) {
		continue;
	}
	$spcw_gateways[ $spcw_gw_id ] = array(
		'name'      => $spcw_gw_id,
		'enabled'   => false,
		'installed' => false,
		'conf'      => SPCW_Calculator::match_gateway( $spcw_gw_id, $spcw_saved_gw ),
	);
}
uasort(
	$spcw_gateways,
	function ( $a, $b ) {
		return (int) $b['enabled'] - (int) $a['enabled'];
	}
);
?>
<div class="wrap spcw-wrap">
	<h1><?php esc_html_e( 'Profit settings', 'simple-profit-calculator-for-woocommerce' ); ?></h1>
	<?php SPCW_Admin::render_notices(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="spcw-settings spcw-track-changes">
		<?php wp_nonce_field( 'spcw_save_settings' ); ?>
		<input type="hidden" name="action" value="spcw_save_settings">

		<!-- Product cost -->
		<h2 id="spcw-cogs"><?php esc_html_e( 'Product cost (cost of goods sold)', 'simple-profit-calculator-for-woocommerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'How should the plugin know what the products you sold cost you?', 'simple-profit-calculator-for-woocommerce' ); ?></p>

		<fieldset class="spcw-choices">
			<legend class="screen-reader-text"><?php esc_html_e( 'Product cost method', 'simple-profit-calculator-for-woocommerce' ); ?></legend>

			<label class="spcw-choice">
				<input type="radio" name="spcw_cogs_mode" value="<?php echo esc_attr( SPCW_Settings::COGS_PRODUCT ); ?>" <?php checked( SPCW_Settings::COGS_PRODUCT, $spcw_settings['cogs_mode'] ); ?>>
				<span>
					<strong><?php esc_html_e( 'Cost price per product', 'simple-profit-calculator-for-woocommerce' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Most accurate. Each sold item is multiplied by its cost price. Refunded items are left out.', 'simple-profit-calculator-for-woocommerce' ); ?></span>
				</span>
			</label>
			<div class="spcw-choice-detail" data-show-for="<?php echo esc_attr( SPCW_Settings::COGS_PRODUCT ); ?>">
				<p>
					<label for="spcw-cogs-source"><?php esc_html_e( 'Where cost prices come from', 'simple-profit-calculator-for-woocommerce' ); ?></label><br>
					<select id="spcw-cogs-source" name="spcw_cogs_source">
						<?php foreach ( $spcw_sources as $spcw_src => $spcw_label ) : ?>
							<option value="<?php echo esc_attr( $spcw_src ); ?>" <?php selected( $spcw_src, $spcw_settings['cogs_source'] ); ?>>
								<?php
								echo esc_html( $spcw_label );
								if ( in_array( $spcw_src, $spcw_detected, true ) ) {
									echo ' — ' . esc_html__( 'found in your store', 'simple-profit-calculator-for-woocommerce' );
								}
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="description">
					<?php
					if ( $spcw_detected ) {
						esc_html_e( 'Automatic uses the cost prices already stored by the plugin(s) found above.', 'simple-profit-calculator-for-woocommerce' );
					} else {
						esc_html_e( 'No Cost of Goods plugin was found. With Automatic, a "Cost price" field is added to every product and variation (Products → Edit → General). You don\'t need any other plugin.', 'simple-profit-calculator-for-woocommerce' );
					}
					?>
				</p>
				<p data-show-for-source="custom">
					<label for="spcw-cogs-meta"><?php esc_html_e( 'Custom meta key', 'simple-profit-calculator-for-woocommerce' ); ?></label><br>
					<input type="text" id="spcw-cogs-meta" name="spcw_cogs_meta_key" value="<?php echo esc_attr( $spcw_settings['cogs_meta_key'] ); ?>" class="regular-text code" placeholder="_my_cost_field">
				</p>
			</div>

			<label class="spcw-choice">
				<input type="radio" name="spcw_cogs_mode" value="<?php echo esc_attr( SPCW_Settings::COGS_MONTHLY ); ?>" <?php checked( SPCW_Settings::COGS_MONTHLY, $spcw_settings['cogs_mode'] ); ?>>
				<span>
					<strong><?php esc_html_e( 'One amount per month', 'simple-profit-calculator-for-woocommerce' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Enter a lump sum for goods each month on the Monthly costs page, for example the total of your supplier invoices.', 'simple-profit-calculator-for-woocommerce' ); ?></span>
				</span>
			</label>

			<label class="spcw-choice">
				<input type="radio" name="spcw_cogs_mode" value="<?php echo esc_attr( SPCW_Settings::COGS_PERCENT ); ?>" <?php checked( SPCW_Settings::COGS_PERCENT, $spcw_settings['cogs_mode'] ); ?>>
				<span>
					<strong><?php esc_html_e( 'Percentage of sales', 'simple-profit-calculator-for-woocommerce' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Quick estimate: product cost is a fixed share of product sales (excluding tax and shipping).', 'simple-profit-calculator-for-woocommerce' ); ?></span>
				</span>
			</label>
			<div class="spcw-choice-detail" data-show-for="<?php echo esc_attr( SPCW_Settings::COGS_PERCENT ); ?>">
				<label for="spcw-cogs-percent"><?php esc_html_e( 'Product cost as % of sales', 'simple-profit-calculator-for-woocommerce' ); ?></label><br>
				<input type="text" inputmode="decimal" id="spcw-cogs-percent" name="spcw_cogs_percent" value="<?php echo esc_attr( wc_format_localized_decimal( $spcw_settings['cogs_percent'] ) ); ?>" class="small-text"> %
			</div>
		</fieldset>

		<!-- Payment fees -->
		<h2 id="spcw-fees"><?php esc_html_e( 'Payment fees', 'simple-profit-calculator-for-woocommerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'What each payment provider charges you. The percentage is taken from the full amount the customer paid; the fixed fee is added per order. Use 0 for methods without fees, such as bank transfer.', 'simple-profit-calculator-for-woocommerce' ); ?></p>

		<table class="widefat spcw-fees">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Payment method', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Name in reports', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Fee %', 'simple-profit-calculator-for-woocommerce' ); ?></th>
					<th scope="col">
						<?php
						/* translators: %s: currency symbol */
						echo esc_html( sprintf( __( 'Fixed fee per order (%s)', 'simple-profit-calculator-for-woocommerce' ), get_woocommerce_currency_symbol() ) );
						?>
					</th>
					<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'simple-profit-calculator-for-woocommerce' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $spcw_gateways as $spcw_gw_id => $spcw_gw ) : ?>
					<?php $spcw_field = 'spcw_gw[' . $spcw_gw_id . ']'; ?>
				<tr class="<?php echo $spcw_gw['enabled'] ? '' : 'spcw-muted'; ?>">
					<td>
						<strong><?php echo esc_html( $spcw_gw['name'] ); ?></strong><br>
						<code><?php echo esc_html( $spcw_gw_id ); ?></code>
						<?php if ( ! $spcw_gw['installed'] ) : ?>
							<span class="description"><?php esc_html_e( '(not installed)', 'simple-profit-calculator-for-woocommerce' ); ?></span>
						<?php elseif ( ! $spcw_gw['enabled'] ) : ?>
							<span class="description"><?php esc_html_e( '(disabled)', 'simple-profit-calculator-for-woocommerce' ); ?></span>
						<?php endif; ?>
					</td>
					<td><input type="text" name="<?php echo esc_attr( $spcw_field ); ?>[label]" value="<?php echo esc_attr( $spcw_gw['conf']['label'] ); ?>" placeholder="<?php echo esc_attr( $spcw_gw['name'] ); ?>"></td>
					<td><input type="text" inputmode="decimal" class="small-text" name="<?php echo esc_attr( $spcw_field ); ?>[percent]" value="<?php echo esc_attr( wc_format_localized_decimal( $spcw_gw['conf']['percent'] ) ); ?>"></td>
					<td><input type="text" inputmode="decimal" class="small-text" name="<?php echo esc_attr( $spcw_field ); ?>[fixed]" value="<?php echo esc_attr( wc_format_localized_price( wc_format_decimal( $spcw_gw['conf']['fixed'], wc_get_price_decimals() ) ) ); ?>"></td>
					<td>
						<?php if ( ! $spcw_gw['installed'] ) : ?>
							<label><input type="checkbox" name="<?php echo esc_attr( $spcw_field ); ?>[remove]" value="1"> <?php esc_html_e( 'Remove', 'simple-profit-calculator-for-woocommerce' ); ?></label>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				<tr class="spcw-new-row">
					<td>
						<label class="screen-reader-text" for="spcw-gw-new-id"><?php esc_html_e( 'Payment method id', 'simple-profit-calculator-for-woocommerce' ); ?></label>
						<input type="text" id="spcw-gw-new-id" name="spcw_gw_new[id]" placeholder="<?php esc_attr_e( 'Other payment method id', 'simple-profit-calculator-for-woocommerce' ); ?>">
					</td>
					<td><input type="text" name="spcw_gw_new[label]" placeholder="<?php esc_attr_e( 'Name', 'simple-profit-calculator-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Name', 'simple-profit-calculator-for-woocommerce' ); ?>"></td>
					<td><input type="text" inputmode="decimal" class="small-text" name="spcw_gw_new[percent]" aria-label="<?php esc_attr_e( 'Fee %', 'simple-profit-calculator-for-woocommerce' ); ?>"></td>
					<td><input type="text" inputmode="decimal" class="small-text" name="spcw_gw_new[fixed]" aria-label="<?php esc_attr_e( 'Fixed fee per order', 'simple-profit-calculator-for-woocommerce' ); ?>"></td>
					<td></td>
				</tr>
			</tbody>
		</table>

		<!-- Expense categories -->
		<h2 id="spcw-categories"><?php esc_html_e( 'Monthly cost types', 'simple-profit-calculator-for-woocommerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The columns on the Monthly costs page. Rename them to match your business. Removing a type stops its amounts from being counted in reports.', 'simple-profit-calculator-for-woocommerce' ); ?></p>
		<table class="widefat spcw-cats">
			<tbody>
				<?php foreach ( SPCW_Settings::categories() as $spcw_key => $spcw_label ) : ?>
				<tr>
					<td>
						<input type="text" class="regular-text" name="spcw_cat[<?php echo esc_attr( $spcw_key ); ?>][label]" value="<?php echo esc_attr( $spcw_label ); ?>" aria-label="<?php esc_attr_e( 'Cost type name', 'simple-profit-calculator-for-woocommerce' ); ?>">
						<?php if ( SPCW_Settings::SHIPPING_CATEGORY === $spcw_key ) : ?>
							<span class="description"><?php esc_html_e( 'Compared with shipping charged to customers.', 'simple-profit-calculator-for-woocommerce' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( SPCW_Settings::SHIPPING_CATEGORY !== $spcw_key ) : ?>
							<label><input type="checkbox" name="spcw_cat[<?php echo esc_attr( $spcw_key ); ?>][remove]" value="1"> <?php esc_html_e( 'Remove', 'simple-profit-calculator-for-woocommerce' ); ?></label>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php for ( $spcw_i = 0; $spcw_i < 2; $spcw_i++ ) : ?>
				<tr class="spcw-new-row">
					<td colspan="2"><input type="text" class="regular-text" name="spcw_cat_new[]" placeholder="<?php esc_attr_e( 'New cost type, e.g. Rent', 'simple-profit-calculator-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'New cost type', 'simple-profit-calculator-for-woocommerce' ); ?>"></td>
				</tr>
				<?php endfor; ?>
			</tbody>
		</table>

		<!-- Orders -->
		<h2><?php esc_html_e( 'Which orders count as sales', 'simple-profit-calculator-for-woocommerce' ); ?></h2>
		<fieldset class="spcw-statuses">
			<legend class="screen-reader-text"><?php esc_html_e( 'Order statuses', 'simple-profit-calculator-for-woocommerce' ); ?></legend>
			<?php foreach ( wc_get_order_statuses() as $spcw_slug => $spcw_label ) : ?>
				<label><input type="checkbox" name="spcw_statuses[]" value="<?php echo esc_attr( $spcw_slug ); ?>" <?php checked( in_array( $spcw_slug, $spcw_statuses, true ) ); ?>> <?php echo esc_html( $spcw_label ); ?></label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Usually Processing and Completed. Partial refunds on these orders are subtracted automatically.', 'simple-profit-calculator-for-woocommerce' ); ?></p>

		<!-- Data -->
		<h2><?php esc_html_e( 'Data', 'simple-profit-calculator-for-woocommerce' ); ?></h2>
		<label><input type="checkbox" name="spcw_delete_on_uninstall" value="1" <?php checked( ! empty( $spcw_settings['delete_on_uninstall'] ) ); ?> <?php disabled( ! SPCW_Settings::can_manage_data() ); ?>> <?php esc_html_e( 'Delete all monthly costs, product cost prices and settings when the plugin is deleted', 'simple-profit-calculator-for-woocommerce' ); ?></label>
		<?php if ( ! SPCW_Settings::can_manage_data() ) : ?>
			<p class="description"><?php esc_html_e( 'Only site administrators can change this setting.', 'simple-profit-calculator-for-woocommerce' ); ?></p>
		<?php endif; ?>

		<?php submit_button( __( 'Save settings', 'simple-profit-calculator-for-woocommerce' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'System status', 'simple-profit-calculator-for-woocommerce' ); ?></h2>
	<table class="widefat striped spcw-status">
		<tbody>
			<tr><th scope="row"><?php esc_html_e( 'Plugin version', 'simple-profit-calculator-for-woocommerce' ); ?></th><td><?php echo esc_html( SPCW_VERSION ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'WooCommerce version', 'simple-profit-calculator-for-woocommerce' ); ?></th><td><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '?' ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Order storage', 'simple-profit-calculator-for-woocommerce' ); ?></th><td><?php echo SPCW_Orders::hpos_enabled() ? esc_html__( 'High-performance order storage (HPOS)', 'simple-profit-calculator-for-woocommerce' ) : esc_html__( 'WordPress posts (legacy)', 'simple-profit-calculator-for-woocommerce' ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Cost prices found from', 'simple-profit-calculator-for-woocommerce' ); ?></th><td><?php echo $spcw_detected ? esc_html( implode( ', ', array_intersect_key( $spcw_sources, array_flip( $spcw_detected ) ) ) ) : esc_html__( 'None yet', 'simple-profit-calculator-for-woocommerce' ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Monthly costs table', 'simple-profit-calculator-for-woocommerce' ); ?></th><td><?php echo SPCW_DB::table_exists() ? esc_html__( 'OK', 'simple-profit-calculator-for-woocommerce' ) : esc_html__( 'Missing', 'simple-profit-calculator-for-woocommerce' ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Store timezone', 'simple-profit-calculator-for-woocommerce' ); ?></th><td><?php echo esc_html( wp_timezone_string() ); ?></td></tr>
		</tbody>
	</table>
</div>
