<?php
/**
 * Product cost (cost of goods) lookup and the built-in product cost field.
 *
 * @package SimpleProfitCalculator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads per-unit product cost from whichever source the store uses.
 */
class SPCW_Cogs {

	/** Meta key of this plugin's own cost field. */
	const OWN_META_KEY = '_spcw_cost';

	/** Known third-party meta keys. */
	const META_KEYS = array(
		'wpfactory' => '_alg_wc_cog_cost', // "Cost of Goods: Product Cost & Profit Calculator" by WPFactory.
		'skyverge'  => '_wc_cog_cost',     // "Cost of Goods" by SkyVerge / WooCommerce.com.
	);

	/**
	 * Request-level cache for detect().
	 *
	 * @var array|null
	 */
	private static $detected = null;

	/**
	 * Whether the cost-meta bump already ran this request.
	 *
	 * @var bool
	 */
	private static $bumped = false;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'render_product_field' ) );
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'render_variation_field' ), 10, 3 );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product_field' ) );
		add_action( 'woocommerce_admin_process_variation_object', array( __CLASS__, 'save_variation_field' ), 10, 2 );

		// A cost change affects COGS in every cached month.
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_bump_cache' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_bump_cache' ), 10, 3 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'maybe_bump_cache' ), 10, 3 );
	}

	/**
	 * Human-readable names of the sources.
	 */
	public static function source_labels(): array {
		return array(
			'auto'      => __( 'Automatic (use whatever is available)', 'simple-profit-calculator-for-woocommerce' ),
			'wc_core'   => __( 'WooCommerce built-in Cost of Goods', 'simple-profit-calculator-for-woocommerce' ),
			'wpfactory' => __( 'Cost of Goods plugin by WPFactory', 'simple-profit-calculator-for-woocommerce' ),
			'skyverge'  => __( 'Cost of Goods plugin by SkyVerge', 'simple-profit-calculator-for-woocommerce' ),
			'own'       => __( 'This plugin\'s "Cost price" product field', 'simple-profit-calculator-for-woocommerce' ),
			'custom'    => __( 'Custom product meta key', 'simple-profit-calculator-for-woocommerce' ),
		);
	}

	/**
	 * Whether WooCommerce's own Cost of Goods feature is enabled.
	 */
	public static function wc_core_enabled(): bool {
		return class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' )
			&& method_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil', 'feature_is_enabled' )
			&& \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' )
			&& method_exists( 'WC_Product', 'get_cogs_value' );
	}

	/**
	 * Third-party sources that have data in this store, in priority order.
	 *
	 * @return string[] Source ids.
	 */
	public static function detect(): array {
		if ( null !== self::$detected ) {
			return self::$detected;
		}
		global $wpdb;
		$found = array();
		foreach ( self::META_KEYS as $source => $key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' LIMIT 1", $key ) ) ) {
				$found[] = $source;
			}
		}
		if ( self::wc_core_enabled() ) {
			$found[] = 'wc_core';
		}
		self::$detected = $found;
		return $found;
	}

	/**
	 * Sources to try, in order, for the current settings.
	 */
	public static function active_sources(): array {
		$source = (string) SPCW_Settings::get( 'cogs_source' );
		if ( 'auto' === $source ) {
			$sources   = self::detect();
			$sources[] = 'own';
			return $sources;
		}
		return array( $source );
	}

	/**
	 * Whether to show this plugin's own cost field on products.
	 */
	public static function own_field_enabled(): bool {
		return SPCW_Settings::COGS_PRODUCT === SPCW_Settings::get( 'cogs_mode' )
			&& in_array( 'own', self::active_sources(), true )
			&& ( 'own' === SPCW_Settings::get( 'cogs_source' ) || ! self::detect() );
	}

	/**
	 * Per-unit cost of a product, or null when no cost is set.
	 *
	 * @param WC_Product $product Product or variation.
	 */
	public static function unit_cost( WC_Product $product ) {
		foreach ( self::active_sources() as $source ) {
			$cost = self::cost_from_source( $product, $source );
			if ( null !== $cost ) {
				return $cost;
			}
		}
		return null;
	}

	/**
	 * Cost from one source.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $source  Source id.
	 * @return float|null
	 */
	private static function cost_from_source( WC_Product $product, string $source ) {
		if ( 'wc_core' === $source ) {
			if ( ! self::wc_core_enabled() ) {
				return null;
			}
			$value = method_exists( $product, 'get_cogs_effective_value' ) ? $product->get_cogs_effective_value() : $product->get_cogs_value();
			// WooCommerce stores 0 when no cost was entered, so treat 0 as "not set".
			return ( is_numeric( $value ) && (float) $value > 0 ) ? (float) $value : null;
		}

		if ( 'own' === $source ) {
			$key = self::OWN_META_KEY;
		} elseif ( 'custom' === $source ) {
			$key = (string) SPCW_Settings::get( 'cogs_meta_key' );
		} else {
			$key = self::META_KEYS[ $source ] ?? '';
		}
		if ( '' === $key ) {
			return null;
		}

		$value = self::read_meta( $product->get_id(), $key );
		if ( null === $value && $product->is_type( 'variation' ) ) {
			$value = self::read_meta( $product->get_parent_id(), $key );
		}
		return $value;
	}

	/**
	 * Numeric post meta or null.
	 *
	 * @param int    $post_id Post id.
	 * @param string $key     Meta key.
	 * @return float|null
	 */
	private static function read_meta( int $post_id, string $key ) {
		if ( $post_id <= 0 ) {
			return null;
		}
		$raw = get_post_meta( $post_id, $key, true );
		if ( '' === $raw || null === $raw || false === $raw || is_array( $raw ) ) {
			return null;
		}
		$value = SPCW_Settings::parse_amount( $raw );
		return null === $value ? null : max( 0.0, $value );
	}

	/**
	 * All meta keys that hold product costs (used for cache invalidation).
	 */
	private static function watched_keys(): array {
		$keys   = array_values( self::META_KEYS );
		$keys[] = self::OWN_META_KEY;
		$keys[] = '_cogs_total_value'; // WooCommerce core.
		$custom = (string) SPCW_Settings::get( 'cogs_meta_key' );
		if ( '' !== $custom ) {
			$keys[] = $custom;
		}
		return $keys;
	}

	/**
	 * Invalidate cached months when a product cost changes.
	 *
	 * @param mixed  $meta_id   Meta id(s).
	 * @param int    $object_id Post id.
	 * @param string $meta_key  Meta key.
	 */
	public static function maybe_bump_cache( $meta_id, $object_id, $meta_key ) {
		if ( self::$bumped || ! is_string( $meta_key ) || ! in_array( $meta_key, self::watched_keys(), true ) ) {
			return;
		}
		self::$bumped = true;
		SPCW_Settings::bump_cache_version();
	}

	/* ── Built-in product cost field ───────────────────────────── */

	/**
	 * Field label with currency symbol.
	 */
	private static function field_label(): string {
		/* translators: %s: currency symbol */
		return sprintf( __( 'Cost price (%s)', 'simple-profit-calculator-for-woocommerce' ), get_woocommerce_currency_symbol() );
	}

	/**
	 * Field help text.
	 */
	private static function field_help(): string {
		return __( 'What one unit costs you to buy or make, excluding VAT. Used by Simple Profit Calculator.', 'simple-profit-calculator-for-woocommerce' );
	}

	/**
	 * Cost field on the General tab of simple products.
	 */
	public static function render_product_field() {
		if ( ! self::own_field_enabled() ) {
			return;
		}
		global $product_object;
		$value = $product_object instanceof WC_Product ? $product_object->get_meta( self::OWN_META_KEY ) : '';
		woocommerce_wp_text_input(
			array(
				'id'          => self::OWN_META_KEY,
				'value'       => '' === $value ? '' : wc_format_localized_price( $value ),
				'label'       => self::field_label(),
				'data_type'   => 'price',
				'desc_tip'    => true,
				'description' => self::field_help(),
			)
		);
	}

	/**
	 * Cost field on each variation.
	 *
	 * @param int     $loop           Variation index.
	 * @param array   $variation_data Unused.
	 * @param WP_Post $variation      Variation post.
	 */
	public static function render_variation_field( $loop, $variation_data, $variation ) {
		if ( ! self::own_field_enabled() ) {
			return;
		}
		$value = get_post_meta( $variation->ID, self::OWN_META_KEY, true );
		woocommerce_wp_text_input(
			array(
				'id'            => self::OWN_META_KEY . '_' . $loop,
				'name'          => self::OWN_META_KEY . '[' . $loop . ']',
				'value'         => '' === $value ? '' : wc_format_localized_price( $value ),
				'label'         => self::field_label(),
				'data_type'     => 'price',
				'desc_tip'      => true,
				'description'   => __( 'Leave empty to use the parent product\'s cost price.', 'simple-profit-calculator-for-woocommerce' ),
				'wrapper_class' => 'form-row form-row-first',
			)
		);
	}

	/**
	 * Save simple product cost. WooCommerce has verified the nonce and capability.
	 *
	 * @param WC_Product $product Product being saved.
	 */
	public static function save_product_field( $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product-save nonce.
		if ( ! isset( $_POST[ self::OWN_META_KEY ] ) || is_array( $_POST[ self::OWN_META_KEY ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the product-save nonce.
		self::store_cost( $product, sanitize_text_field( wp_unslash( $_POST[ self::OWN_META_KEY ] ) ) );
	}

	/**
	 * Save variation cost. WooCommerce has verified the nonce and capability.
	 *
	 * @param WC_Product_Variation $variation Variation being saved.
	 * @param int                  $i         Index in the posted arrays.
	 */
	public static function save_variation_field( $variation, $i ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the save-variations nonce.
		if ( ! isset( $_POST[ self::OWN_META_KEY ][ $i ] ) || ! is_scalar( $_POST[ self::OWN_META_KEY ][ $i ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the save-variations nonce.
		self::store_cost( $variation, sanitize_text_field( wp_unslash( $_POST[ self::OWN_META_KEY ][ $i ] ) ) );
	}

	/**
	 * Parse and store a posted cost.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $raw     Posted value.
	 */
	private static function store_cost( $product, $raw ) {
		$value = SPCW_Settings::parse_amount( $raw );
		if ( null === $value ) {
			$product->delete_meta_data( self::OWN_META_KEY );
		} else {
			$product->update_meta_data( self::OWN_META_KEY, wc_format_decimal( max( 0.0, $value ), 2 ) );
		}
	}
}
