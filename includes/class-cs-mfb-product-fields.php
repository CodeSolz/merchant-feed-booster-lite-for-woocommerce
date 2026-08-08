<?php
/**
 * Product edit fields: brand, GTIN, MPN, Google category, condition.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Product_Fields {

	const META_BRAND     = '_cs_mfb_brand';
	const META_GTIN      = '_cs_mfb_gtin';
	const META_MPN       = '_cs_mfb_mpn';
	const META_GCAT      = '_cs_mfb_google_category';
	const META_CONDITION = '_cs_mfb_condition';

	const NONCE_ACTION = 'cs_mfb_save_product_fields';
	const NONCE_NAME   = 'cs_mfb_product_nonce';

	/** Holds a GTIN validation notice to show after save redirect. */
	private static $pending_gtin_notice = '';

	/**
	 * Register WooCommerce product data hooks.
	 */
	public static function register() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_via_product_object' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_gtin_notice' ) );
		add_action( 'save_post_product', array( 'CodeSolz_MFB_Health', 'clear_duplicate_index' ) );
	}

	/**
	 * Allowed condition values for the Google feed.
	 *
	 * @return array<string, string>
	 */
	public static function allowed_conditions() {
		return array(
			''            => __( '— Not set —', 'merchant-feed-booster-lite-for-woocommerce' ),
			'new'         => __( 'New', 'merchant-feed-booster-lite-for-woocommerce' ),
			'refurbished' => __( 'Refurbished', 'merchant-feed-booster-lite-for-woocommerce' ),
			'used'        => __( 'Used', 'merchant-feed-booster-lite-for-woocommerce' ),
		);
	}

	/**
	 * Render the merchant-feed fields inside the WooCommerce Product Data box.
	 */
	public static function render_fields() {
		echo '<div class="options_group cs-mfb-product-fields">';
		echo '<p class="form-field"><strong>' . esc_html__( 'CodeSolz Merchant Feed', 'merchant-feed-booster-lite-for-woocommerce' ) . '</strong></p>';

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_BRAND,
				'label'       => __( 'Brand', 'merchant-feed-booster-lite-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'Manufacturer or brand. Used in the Google feed.', 'merchant-feed-booster-lite-for-woocommerce' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_GTIN,
				'label'       => __( 'GTIN', 'merchant-feed-booster-lite-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'Global Trade Item Number (UPC, EAN, JAN, ISBN, ITF-14).', 'merchant-feed-booster-lite-for-woocommerce' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_MPN,
				'label'       => __( 'MPN', 'merchant-feed-booster-lite-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'Manufacturer Part Number. Recommended if GTIN is unavailable.', 'merchant-feed-booster-lite-for-woocommerce' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_GCAT,
				'label'       => __( 'Google product category', 'merchant-feed-booster-lite-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'Google taxonomy path, e.g. "Apparel & Accessories > Clothing > Shirts & Tops".', 'merchant-feed-booster-lite-for-woocommerce' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => self::META_CONDITION,
				'label'       => __( 'Condition', 'merchant-feed-booster-lite-for-woocommerce' ),
				'options'     => self::allowed_conditions(),
				'desc_tip'    => true,
				'description' => __( 'Product condition reported to Google Merchant Center.', 'merchant-feed-booster-lite-for-woocommerce' ),
			)
		);

		echo '</div>';
	}

	/**
	 * Save fields when WooCommerce dispatches woocommerce_admin_process_product_object.
	 *
	 * @param WC_Product $product Product object.
	 */
	public static function save_via_product_object( $product ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		$values = self::collect_sanitized_input();
		self::maybe_set_gtin_notice();
		foreach ( $values as $meta_key => $value ) {
			$product->update_meta_data( $meta_key, $value );
		}
		// WooCommerce will persist meta when the product saves.
	}

	/**
	 * If a GTIN validation error was flagged during collect, store it as a transient
	 * so it survives the WC product save redirect and shows as an admin notice.
	 */
	protected static function maybe_set_gtin_notice() {
		if ( '' === self::$pending_gtin_notice ) {
			return;
		}
		set_transient(
			'cs_mfb_gtin_notice_' . get_current_user_id(),
			self::$pending_gtin_notice,
			120
		);
		self::$pending_gtin_notice = '';
	}

	/**
	 * Display a GTIN validation warning after product save (reads from transient).
	 */
	public static function show_gtin_notice() {
		$key    = 'cs_mfb_gtin_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Merchant Feed Booster:', 'merchant-feed-booster-lite-for-woocommerce' ),
			esc_html( $notice )
		);
	}

	/**
	 * Pull and sanitize the fields from $_POST.
	 *
	 * @return array<string, string>
	 */
	protected static function collect_sanitized_input() {
		$conditions = self::allowed_conditions();

		$brand = isset( $_POST[ self::META_BRAND ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_BRAND ] ) ) : '';
		$gtin  = isset( $_POST[ self::META_GTIN ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_GTIN ] ) ) : '';
		$mpn   = isset( $_POST[ self::META_MPN ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_MPN ] ) ) : '';
		$gcat  = isset( $_POST[ self::META_GCAT ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_GCAT ] ) ) : '';
		$cond  = isset( $_POST[ self::META_CONDITION ] ) ? sanitize_key( wp_unslash( $_POST[ self::META_CONDITION ] ) ) : '';

		if ( ! array_key_exists( $cond, $conditions ) ) {
			$cond = '';
		}

		// Strip anything that isn't 0-9 from GTIN — Google requires numeric.
		$gtin = preg_replace( '/[^0-9]/', '', $gtin );

		// Validate check digit if a non-empty GTIN was provided.
		if ( '' !== $gtin ) {
			$validation = CodeSolz_MFB_GTIN_Validator::validate( $gtin );
			if ( ! $validation['valid'] ) {
				self::$pending_gtin_notice = sprintf(
					/* translators: 1: GTIN digits 2: validation error message */
					__( 'GTIN "%1$s" — %2$s. The value was saved but Google may reject this product.', 'merchant-feed-booster-lite-for-woocommerce' ),
					$gtin,
					$validation['error']
				);
			}
		}

		return array(
			self::META_BRAND     => $brand,
			self::META_GTIN      => $gtin,
			self::META_MPN       => $mpn,
			self::META_GCAT      => $gcat,
			self::META_CONDITION => $cond,
		);
	}
}
