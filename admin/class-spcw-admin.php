<?php
/**
 * Admin screens and form handlers.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
class SPCW_Admin {

	const SLUG_DASHBOARD = 'spcw-dashboard';
	const SLUG_COSTS     = 'spcw-costs';
	const SLUG_SETTINGS  = 'spcw-settings';

	/**
	 * Hook suffixes of our pages.
	 *
	 * @var string[]
	 */
	private static $hooks = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_spcw_save_costs', array( __CLASS__, 'handle_save_costs' ) );
		add_action( 'admin_post_spcw_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_spcw_refresh', array( __CLASS__, 'handle_refresh' ) );
		add_action( 'admin_post_spcw_export', array( __CLASS__, 'handle_export' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SPCW_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/* ── Menu & assets ──────────────────────────────────────────── */

	/**
	 * Admin menu.
	 */
	public static function register_menu() {
		$cap = SPCW_Settings::capability();

		self::$hooks[] = add_menu_page(
			__( 'Profit Calculator', 'simple-profit-calculator-for-woocommerce' ),
			__( 'Profit', 'simple-profit-calculator-for-woocommerce' ),
			$cap,
			self::SLUG_DASHBOARD,
			array( __CLASS__, 'page_dashboard' ),
			'dashicons-chart-line',
			56
		);
		self::$hooks[] = add_submenu_page( self::SLUG_DASHBOARD, __( 'Profit dashboard', 'simple-profit-calculator-for-woocommerce' ), __( 'Dashboard', 'simple-profit-calculator-for-woocommerce' ), $cap, self::SLUG_DASHBOARD, array( __CLASS__, 'page_dashboard' ) );
		self::$hooks[] = add_submenu_page( self::SLUG_DASHBOARD, __( 'Monthly costs', 'simple-profit-calculator-for-woocommerce' ), __( 'Monthly costs', 'simple-profit-calculator-for-woocommerce' ), $cap, self::SLUG_COSTS, array( __CLASS__, 'page_costs' ) );
		self::$hooks[] = add_submenu_page( self::SLUG_DASHBOARD, __( 'Profit settings', 'simple-profit-calculator-for-woocommerce' ), __( 'Settings', 'simple-profit-calculator-for-woocommerce' ), $cap, self::SLUG_SETTINGS, array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( self::SLUG_SETTINGS ) ) . '">' . esc_html__( 'Settings', 'simple-profit-calculator-for-woocommerce' ) . '</a>' );
		return $links;
	}

	/**
	 * CSS/JS on our pages only.
	 *
	 * @param string $hook Current page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, self::$hooks, true ) ) {
			return;
		}
		wp_enqueue_style( 'spcw-admin', SPCW_URL . 'assets/css/admin.css', array(), SPCW_VERSION );
		wp_enqueue_script( 'spcw-admin', SPCW_URL . 'assets/js/admin.js', array(), SPCW_VERSION, true );
		wp_localize_script(
			'spcw-admin',
			'spcwAdmin',
			array(
				'decimal'  => wc_get_price_decimal_separator(),
				'decimals' => wc_get_price_decimals(),
				'unsaved'  => __( 'You have unsaved changes. Leave anyway?', 'simple-profit-calculator-for-woocommerce' ),
			)
		);
	}

	/* ── Pages ──────────────────────────────────────────────────── */

	/**
	 * Dashboard page.
	 */
	public static function page_dashboard() {
		self::render( 'dashboard' );
	}

	/**
	 * Monthly costs page.
	 */
	public static function page_costs() {
		self::render( 'costs' );
	}

	/**
	 * Settings page.
	 */
	public static function page_settings() {
		self::render( 'settings' );
	}

	/**
	 * Include a view after a capability check.
	 *
	 * @param string $view View name.
	 */
	private static function render( string $view ) {
		if ( ! current_user_can( SPCW_Settings::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'simple-profit-calculator-for-woocommerce' ) );
		}
		include SPCW_DIR . 'admin/views/' . $view . '.php';
	}

	/* ── Handlers ───────────────────────────────────────────────── */

	/**
	 * Read a posted field, unslashed and sanitised (arrays are sanitised recursively).
	 * Only called from handlers that have already verified their nonce.
	 *
	 * @param string $key      Field name.
	 * @param mixed  $fallback Value when the field is missing.
	 * @param string $sanitize Sanitising callback.
	 * @return mixed
	 */
	private static function posted( string $key, $fallback = '', string $sanitize = 'sanitize_text_field' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by the calling handler.
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by the caller; sanitised by map_deep() with the given callback.
		return map_deep( wp_unslash( $_POST[ $key ] ), $sanitize );
	}

	/**
	 * Stop users without the capability. Each handler also checks its nonce.
	 */
	private static function check_capability() {
		if ( ! current_user_can( SPCW_Settings::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'simple-profit-calculator-for-woocommerce' ), 403 );
		}
	}

	/**
	 * Save monthly costs. Handles any number of months in one submit, so the
	 * whole year table is saved with a single button.
	 */
	public static function handle_save_costs() {
		self::check_capability();
		check_admin_referer( 'spcw_save_costs' );

		$year  = self::clamp_year( absint( self::posted( 'spcw_year', 0 ) ) );
		$costs = self::posted( 'spcw_costs', array() );
		$notes = self::posted( 'spcw_notes', array(), 'sanitize_textarea_field' );
		$costs = is_array( $costs ) ? $costs : array();
		$notes = is_array( $notes ) ? $notes : array();

		$allowed = array_keys( SPCW_Settings::categories() );
		if ( SPCW_Settings::COGS_MONTHLY === SPCW_Settings::get( 'cogs_mode' ) ) {
			$allowed[] = SPCW_Calculator::COGS_KEY;
		}

		$saved  = 0;
		$failed = 0;
		$months = array_unique( array_merge( array_keys( $costs ), array_keys( $notes ) ) );
		foreach ( $months as $month ) {
			$month = (int) $month;
			if ( $month < 1 || $month > 12 ) {
				continue;
			}
			$existing = SPCW_DB::get_month( $year, $month );

			// Start from what is stored so categories not shown in this form
			// (e.g. removed categories, COGS in another mode) are kept.
			$new = $existing['costs'];
			foreach ( $allowed as $key ) {
				if ( ! isset( $costs[ $month ] ) || ! is_array( $costs[ $month ] ) || ! array_key_exists( $key, $costs[ $month ] ) ) {
					continue;
				}
				$value = SPCW_Settings::parse_amount( $costs[ $month ][ $key ] );
				if ( null === $value ) {
					unset( $new[ $key ] );
				} else {
					$new[ $key ] = $value;
				}
			}
			$note = isset( $notes[ $month ] ) && is_string( $notes[ $month ] ) ? $notes[ $month ] : $existing['notes'];

			ksort( $new );
			$old = $existing['costs'];
			ksort( $old );
			if ( $new === $old && $note === $existing['notes'] ) {
				continue; // Unchanged.
			}
			if ( SPCW_DB::save_month( $year, $month, $new, $note ) ) {
				++$saved;
			} else {
				++$failed;
			}
		}

		$redirect = 'dashboard' === self::posted( 'spcw_redirect' ) ? 'dashboard' : 'costs';
		$args     = array(
			'spcw_year'   => $year,
			'spcw_notice' => $failed ? 'save_failed' : 'saved',
			'spcw_count'  => $saved,
		);
		if ( 'dashboard' === $redirect ) {
			$args['spcw_month'] = self::clamp_month( absint( self::posted( 'spcw_month', 0 ) ) );
			$page               = self::SLUG_DASHBOARD;
		} else {
			$page = self::SLUG_COSTS;
		}
		wp_safe_redirect( self::url( $page, $args ) );
		exit;
	}

	/**
	 * Save settings.
	 */
	public static function handle_save_settings() {
		self::check_capability();
		check_admin_referer( 'spcw_save_settings' );
		$settings = SPCW_Settings::all();

		// Order statuses: only real WooCommerce statuses.
		$valid                      = array_keys( wc_get_order_statuses() );
		$statuses                   = array_values( array_intersect( (array) self::posted( 'spcw_statuses', array() ), $valid ) );
		$settings['order_statuses'] = $statuses ? $statuses : array( 'wc-completed', 'wc-processing' );

		// Product cost.
		$mode                      = sanitize_key( (string) self::posted( 'spcw_cogs_mode' ) );
		$settings['cogs_mode']     = in_array( $mode, array( SPCW_Settings::COGS_PRODUCT, SPCW_Settings::COGS_MONTHLY, SPCW_Settings::COGS_PERCENT ), true ) ? $mode : SPCW_Settings::COGS_PRODUCT;
		$source                    = sanitize_key( (string) self::posted( 'spcw_cogs_source', 'auto' ) );
		$settings['cogs_source']   = array_key_exists( $source, SPCW_Cogs::source_labels() ) ? $source : 'auto';
		$meta_key                  = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) self::posted( 'spcw_cogs_meta_key' ) );
		$settings['cogs_meta_key'] = substr( $meta_key, 0, 190 );
		if ( 'custom' === $settings['cogs_source'] && '' === $settings['cogs_meta_key'] ) {
			$settings['cogs_source'] = 'auto';
		}
		$percent                  = SPCW_Settings::parse_amount( self::posted( 'spcw_cogs_percent' ) );
		$settings['cogs_percent'] = min( 100, max( 0, (float) $percent ) );

		// Payment fees.
		$gateways = array();
		$rows     = (array) self::posted( 'spcw_gw', array() );
		foreach ( $rows as $id => $row ) {
			$id = self::sanitize_gateway_id( $id );
			if ( '' === $id || ! is_array( $row ) || ! empty( $row['remove'] ) || count( $gateways ) >= SPCW_Settings::MAX_GATEWAYS ) {
				continue;
			}
			$gateways[ $id ] = self::sanitize_gateway_row( $row );
		}
		$new_row = (array) self::posted( 'spcw_gw_new', array() );
		$new_id  = isset( $new_row['id'] ) ? self::sanitize_gateway_id( $new_row['id'] ) : '';
		if ( '' !== $new_id && ! isset( $gateways[ $new_id ] ) && count( $gateways ) < SPCW_Settings::MAX_GATEWAYS ) {
			$gateways[ $new_id ] = self::sanitize_gateway_row( $new_row );
		}
		$settings['gateways'] = $gateways;

		// Expense categories.
		$categories = array();
		$rows       = (array) self::posted( 'spcw_cat', array() );
		foreach ( $rows as $key => $row ) {
			$key = sanitize_key( $key );
			if ( '' === $key || ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? (string) $row['label'] : '';
			if ( SPCW_Settings::SHIPPING_CATEGORY !== $key && ( ! empty( $row['remove'] ) || '' === $label || count( $categories ) >= SPCW_Settings::MAX_CATEGORIES ) ) {
				continue;
			}
			$categories[ $key ] = '' !== $label ? mb_substr( $label, 0, SPCW_Settings::MAX_LABEL_CHARS ) : __( 'Shipping / postage', 'simple-profit-calculator-for-woocommerce' );
		}
		$new_labels = (array) self::posted( 'spcw_cat_new', array() );
		// mbstring is optional in WordPress; WordPress only polyfills mb_substr().
		$lower = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
		foreach ( $new_labels as $label ) {
			$label    = mb_substr( (string) $label, 0, SPCW_Settings::MAX_LABEL_CHARS );
			$existing = array_map( $lower, $categories );
			if ( '' === $label || in_array( $lower( $label ), $existing, true ) || count( $categories ) >= SPCW_Settings::MAX_CATEGORIES ) {
				continue; // Empty, or a type with this name already exists.
			}
			$base = trim( sanitize_key( str_replace( ' ', '_', remove_accents( $label ) ) ), '_' );
			$base = '' !== $base ? substr( $base, 0, 30 ) : 'cost';
			$key  = $base;
			for ( $i = 2; isset( $categories[ $key ] ); $i++ ) {
				$key = $base . '_' . $i;
			}
			$categories[ $key ] = $label;
		}
		if ( ! isset( $categories[ SPCW_Settings::SHIPPING_CATEGORY ] ) ) {
			$categories = array( SPCW_Settings::SHIPPING_CATEGORY => __( 'Shipping / postage', 'simple-profit-calculator-for-woocommerce' ) ) + $categories;
		}
		$settings['categories'] = $categories;

		// Only administrators may opt in to deleting data; Shop Managers keep the current value.
		if ( SPCW_Settings::can_manage_data() ) {
			$settings['delete_on_uninstall'] = '' !== (string) self::posted( 'spcw_delete_on_uninstall' );
		}

		SPCW_Settings::save( $settings );
		SPCW_Settings::bump_cache_version();

		wp_safe_redirect( self::url( self::SLUG_SETTINGS, array( 'spcw_notice' => 'settings_saved' ) ) );
		exit;
	}

	/**
	 * Clear all cached sales data.
	 */
	public static function handle_refresh() {
		self::check_capability();
		check_admin_referer( 'spcw_refresh' );
		SPCW_Settings::bump_cache_version();
		delete_transient( 'spcw_first_order_year' );

		$args               = array( 'spcw_notice' => 'refreshed' );
		$args['spcw_year']  = self::clamp_year( absint( self::posted( 'spcw_year', 0 ) ) );
		$args['spcw_month'] = self::clamp_month( absint( self::posted( 'spcw_month', 0 ) ) );
		if ( 'yearly' === self::posted( 'spcw_view' ) ) {
			$args['spcw_view'] = 'yearly';
		}
		wp_safe_redirect( self::url( self::SLUG_DASHBOARD, $args ) );
		exit;
	}

	/**
	 * Download a CSV of a year.
	 */
	public static function handle_export() {
		self::check_capability();
		check_admin_referer( 'spcw_export' );
		$year = self::clamp_year( absint( self::posted( 'spcw_year', 0 ) ) );
		$data = SPCW_Calculator::year( $year );
		$cats = SPCW_Settings::categories();

		$header = array(
			__( 'Month', 'simple-profit-calculator-for-woocommerce' ),
			__( 'Orders', 'simple-profit-calculator-for-woocommerce' ),
			__( 'Gross sales', 'simple-profit-calculator-for-woocommerce' ),
			__( 'Refunds', 'simple-profit-calculator-for-woocommerce' ),
			__( 'Tax', 'simple-profit-calculator-for-woocommerce' ),
			__( 'Net sales', 'simple-profit-calculator-for-woocommerce' ),
			__( 'Shipping charged', 'simple-profit-calculator-for-woocommerce' ),
			__( 'Product cost', 'simple-profit-calculator-for-woocommerce' ),
			__( 'Payment fees', 'simple-profit-calculator-for-woocommerce' ),
		);
		foreach ( $cats as $label ) {
			$header[] = $label;
		}
		$header[] = __( 'Total costs', 'simple-profit-calculator-for-woocommerce' );
		$header[] = __( 'Profit', 'simple-profit-calculator-for-woocommerce' );
		$header[] = __( 'Margin %', 'simple-profit-calculator-for-woocommerce' );
		$header[] = __( 'Notes', 'simple-profit-calculator-for-woocommerce' );

		$rows = array( $header );
		foreach ( $data['months'] as $m => $d ) {
			$rows[] = self::csv_row( sprintf( '%04d-%02d', $year, $m ), $d, $cats, $d['notes'] );
		}
		$rows[] = self::csv_row( (string) $year, $data['totals'], $cats, '' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="profit-' . $year . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so Excel reads æøå correctly.
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( array( __CLASS__, 'csv_safe' ), $row ), ',', '"', '\\' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * One CSV line.
	 *
	 * @param string $label Row label.
	 * @param array  $d     Month or totals data.
	 * @param array  $cats  Categories.
	 * @param string $notes Notes.
	 */
	private static function csv_row( string $label, array $d, array $cats, string $notes ): array {
		$n   = function ( $v ) {
			return wc_format_decimal( $v, 2 );
		};
		$row = array( $label, $d['order_count'], $n( $d['gross'] ), $n( $d['refunds'] ), $n( $d['tax'] ), $n( $d['net_sales'] ), $n( $d['shipping_charged'] ), $n( $d['cogs'] ), $n( $d['payment_fees'] ) );
		foreach ( array_keys( $cats ) as $key ) {
			$row[] = $n( $d['expenses'][ $key ] ?? 0 );
		}
		$row[] = $n( $d['total_costs'] );
		$row[] = $n( $d['profit'] );
		$row[] = null === $d['margin'] ? '' : $d['margin'];
		$row[] = $notes;
		return $row;
	}

	/**
	 * Stop spreadsheet formula injection (CSV injection) in text cells.
	 *
	 * Spreadsheet apps treat cells starting with = + - @ (also after leading
	 * whitespace, tabs or line breaks) as formulas. Such cells get a leading
	 * apostrophe, which spreadsheets display as plain text. Real numbers are
	 * left alone so they stay numeric.
	 *
	 * @param mixed $cell Cell value.
	 */
	public static function csv_safe( $cell ) {
		$cell = (string) $cell;
		if ( '' === $cell || is_numeric( $cell ) ) {
			return $cell;
		}
		$first = substr( ltrim( $cell, " \t\r\n\0\x0B" ), 0, 1 );
		if ( in_array( $first, array( '=', '+', '-', '@', '|', '%' ), true ) || in_array( $cell[0], array( "\t", "\r", "\n" ), true ) ) {
			return "'" . $cell;
		}
		return $cell;
	}

	/* ── Helpers used by the views ──────────────────────────────── */

	/**
	 * Admin URL of one of our pages.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra query args.
	 */
	public static function url( string $page, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Year within a sensible range.
	 *
	 * @param int $year Requested year (0 = current).
	 */
	public static function clamp_year( int $year ): int {
		$current = (int) current_time( 'Y' );
		if ( $year < 2000 || $year > $current + 1 ) {
			return $current;
		}
		return $year;
	}

	/**
	 * Month 1-12 (0 = current).
	 *
	 * @param int $month Requested month.
	 */
	public static function clamp_month( int $month ): int {
		return ( $month >= 1 && $month <= 12 ) ? $month : (int) current_time( 'n' );
	}

	/**
	 * Years available in the selectors, newest first.
	 */
	public static function year_options(): array {
		return range( (int) current_time( 'Y' ), SPCW_Orders::first_order_year() );
	}

	/**
	 * Localised month name.
	 *
	 * @param int  $month  Month.
	 * @param bool $abbrev Short form.
	 */
	public static function month_name( int $month, bool $abbrev = false ): string {
		global $wp_locale;
		$name = $wp_locale->get_month( $month );
		return $abbrev ? $wp_locale->get_month_abbrev( $name ) : $name;
	}

	/**
	 * Money formatted by WooCommerce (HTML). Escape with wp_kses_post() when echoing.
	 *
	 * @param float $amount Amount.
	 */
	public static function money( $amount ): string {
		return wc_price( (float) $amount );
	}

	/**
	 * Number for an input field ("" when not set).
	 *
	 * @param array  $costs Stored costs.
	 * @param string $key   Category key.
	 */
	public static function input_value( array $costs, string $key ): string {
		if ( ! array_key_exists( $key, $costs ) ) {
			return '';
		}
		return wc_format_localized_decimal( wc_format_decimal( $costs[ $key ], wc_get_price_decimals() ) );
	}

	/**
	 * Margin as text.
	 *
	 * @param float|null $margin Margin.
	 */
	public static function margin_text( $margin ): string {
		return null === $margin ? '–' : number_format_i18n( $margin, 1 ) . '%';
	}

	/**
	 * Gateway id as stored by WooCommerce (case kept, no spaces).
	 *
	 * @param mixed $id Raw id.
	 */
	private static function sanitize_gateway_id( $id ): string {
		return substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id ), 0, 100 );
	}

	/**
	 * Sanitise a payment fee row.
	 *
	 * @param array $row Posted row.
	 */
	private static function sanitize_gateway_row( array $row ): array {
		return array(
			'label'   => isset( $row['label'] ) ? mb_substr( sanitize_text_field( (string) $row['label'] ), 0, SPCW_Settings::MAX_LABEL_CHARS ) : '',
			'percent' => min( 100, max( 0, (float) SPCW_Settings::parse_amount( $row['percent'] ?? '' ) ) ),
			'fixed'   => max( 0, (float) SPCW_Settings::parse_amount( $row['fixed'] ?? '' ) ),
		);
	}

	/**
	 * Notices after redirects and warnings.
	 */
	public static function render_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$notice = isset( $_GET['spcw_notice'] ) ? sanitize_key( wp_unslash( $_GET['spcw_notice'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$count = isset( $_GET['spcw_count'] ) ? absint( $_GET['spcw_count'] ) : 0;

		$messages = array(
			/* translators: %d: number of months saved */
			'saved'          => array( 'success', 0 === $count ? __( 'No changes to save.', 'simple-profit-calculator-for-woocommerce' ) : sprintf( _n( 'Saved %d month.', 'Saved %d months.', $count, 'simple-profit-calculator-for-woocommerce' ), $count ) ),
			'save_failed'    => array( 'error', __( 'Some months could not be saved. Please try again.', 'simple-profit-calculator-for-woocommerce' ) ),
			'settings_saved' => array( 'success', __( 'Settings saved.', 'simple-profit-calculator-for-woocommerce' ) ),
			'refreshed'      => array( 'success', __( 'Sales data refreshed.', 'simple-profit-calculator-for-woocommerce' ) ),
		);
		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
		}

		if ( SPCW_Migration::v1_active() ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Your data from the old "Profit Calculator" plugin has been copied here. You can now deactivate and delete the old plugin.', 'simple-profit-calculator-for-woocommerce' ) . '</p></div>';
		}
		if ( ! SPCW_DB::table_exists() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The database table for monthly costs is missing. Deactivate and reactivate the plugin to create it.', 'simple-profit-calculator-for-woocommerce' ) . '</p></div>';
		}
	}
}
