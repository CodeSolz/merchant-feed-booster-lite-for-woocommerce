<?php
/**
 * Product health score calculator (0–100).
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Health_Score {

	/** Score tier definitions. */
	const TIERS = array(
		'excellent'   => array( 'min' => 85, 'color' => '#22c55e', 'label' => 'Excellent' ),
		'good'        => array( 'min' => 70, 'color' => '#3b82f6', 'label' => 'Good' ),
		'needs-work'  => array( 'min' => 50, 'color' => '#f59e0b', 'label' => 'Needs Work' ),
		'poor'        => array( 'min' => 30, 'color' => '#f97316', 'label' => 'Poor' ),
		'critical'    => array( 'min' => 0,  'color' => '#ef4444', 'label' => 'Critical' ),
	);

	/**
	 * Calculate health score for a product from its policy issue list.
	 *
	 * Rather than re-running rules, accepts the issue array already produced by
	 * CS_MFB_Policy_Engine::run_all() so callers do not duplicate work.
	 *
	 * @param WC_Product $product WooCommerce product object.
	 * @param array      $issues  Array of issue arrays from the policy engine.
	 * @param array      $settings Plugin settings.
	 * @return int 0–100
	 */
	public static function calculate( WC_Product $product, array $issues, array $settings = array() ) {
		$score = 0;

		// ---- BASE POINTS ----
		$title       = $product->get_name();
		$description = $product->get_description() ?: $product->get_short_description();
		$price       = $product->get_regular_price() ?: $product->get_price();
		$image_id    = (int) $product->get_image_id();
		$brand       = get_post_meta( $product->get_id(), '_cs_mfb_brand', true );
		$gtin        = get_post_meta( $product->get_id(), '_cs_mfb_gtin', true );
		$mpn         = get_post_meta( $product->get_id(), '_cs_mfb_mpn', true );
		$condition   = get_post_meta( $product->get_id(), '_cs_mfb_condition', true );
		$google_cat  = get_post_meta( $product->get_id(), '_cs_mfb_google_category', true );
		$def_brand   = isset( $settings['default_brand'] ) ? $settings['default_brand'] : '';
		$def_cat     = isset( $settings['default_google_category'] ) ? $settings['default_google_category'] : '';

		$title_len = mb_strlen( wp_strip_all_tags( $title ) );
		$desc_len  = mb_strlen( wp_strip_all_tags( $description ) );

		// Title
		if ( $title_len > 0 ) {
			$score += 10;
		}
		if ( $title_len >= 25 && $title_len <= 150 ) {
			$score += 8;
		}

		// Image
		if ( $image_id > 0 ) {
			$score += 10;
			$dims   = CodeSolz_MFB_Image_Checker::get_dimensions( $product->get_id() );
			if ( $dims ) {
				if ( $dims['width'] >= 250 && $dims['height'] >= 250 ) {
					$score += 5;
				}
				if ( $dims['width'] >= 800 && $dims['height'] >= 800 ) {
					$score += 3;
				}
			}
		}

		// Price
		if ( is_numeric( $price ) && (float) $price > 0 ) {
			$score += 10;
			// Price consistency: no orphan sale
			$sale = $product->get_sale_price();
			if ( '' === $sale || '' !== $product->get_regular_price() ) {
				$score += 3;
			}
		}

		// Brand
		if ( '' !== trim( (string) $brand ) || '' !== trim( (string) $def_brand ) ) {
			$score += 8;
		}

		// Google category
		if ( '' !== trim( (string) $google_cat ) || '' !== trim( (string) $def_cat ) ) {
			$score += 6;
		}

		// GTIN
		if ( '' !== trim( (string) $gtin ) ) {
			$validation = CodeSolz_MFB_GTIN_Validator::validate( $gtin );
			if ( $validation['valid'] ) {
				$score += 8;
			}
		}

		// MPN (when no GTIN)
		if ( '' === trim( (string) $gtin ) && '' !== trim( (string) $mpn ) ) {
			$score += 5;
		}

		// Condition
		if ( '' !== trim( (string) $condition ) ) {
			$score += 4;
		}

		// Description
		if ( $desc_len > 0 ) {
			$score += 8;
		}
		if ( $desc_len >= 50 ) {
			$score += 3;
		}
		if ( $desc_len >= 150 ) {
			$score += 3;
		}

		// In stock
		if ( $product->is_in_stock() ) {
			$score += 3;
		}

		// Permalink
		if ( '' !== $product->get_permalink() ) {
			$score += 2;
		}

		// ---- DEDUCTIONS (per triggered policy rules) ----
		$deductions = array(
			'RULE-T03' => 10,
			'RULE-T04' => 8,
			'RULE-T05' => 8,
			'RULE-T01' => 15,
			'RULE-T06' => 10,
			'RULE-I02' => 20,
			'RULE-ID02' => 10,
			'RULE-ID01' => 8,
			'RULE-D03' => 6,
			'RULE-D04' => 5,
			'RULE-P01' => 15,
			'RULE-P03' => 5,
		);

		$deductions = apply_filters( 'cs_mfb_score_deductions', $deductions );

		foreach ( $issues as $issue ) {
			if ( isset( $deductions[ $issue['rule_id'] ] ) ) {
				$score -= $deductions[ $issue['rule_id'] ];
			}
		}

		// Floor at 0 before bonuses.
		$score = max( 0, $score );

		// ---- BONUS POINTS ----
		$bonus = 0;

		$effective_brand = '' !== trim( (string) $brand ) ? $brand : $def_brand;
		if ( '' !== $effective_brand && false !== stripos( $title, $effective_brand ) ) {
			$bonus += 2;
		}

		// Check if color or size WC attribute is in title
		$attributes = $product->get_attributes();
		foreach ( array( 'color', 'colour', 'size', 'material' ) as $attr_name ) {
			foreach ( $attributes as $attr ) {
				$attr_label = is_object( $attr ) ? $attr->get_name() : '';
				if ( false !== stripos( $attr_label, $attr_name ) ) {
					$terms = wc_get_product_terms( $product->get_id(), $attr_label, array( 'fields' => 'names' ) );
					foreach ( $terms as $term_name ) {
						if ( false !== stripos( $title, $term_name ) ) {
							$bonus += 2;
							break 2;
						}
					}
				}
			}
		}

		if ( $image_id > 0 ) {
			$dims = CodeSolz_MFB_Image_Checker::get_dimensions( $product->get_id() );
			if ( $dims && $dims['width'] >= 1200 && $dims['height'] >= 1200 ) {
				$bonus += 2;
			}
		}

		if ( $desc_len >= 500 ) {
			$bonus += 2;
		}

		if ( '' !== trim( (string) $gtin ) && '' !== trim( (string) $mpn ) ) {
			$bonus += 2;
		}

		$score = min( 100, $score + $bonus );

		return (int) $score;
	}

	/**
	 * Get the tier slug for a given score.
	 *
	 * @param int $score 0–100.
	 * @return string Tier slug.
	 */
	public static function get_tier( $score ) {
		foreach ( self::TIERS as $slug => $def ) {
			if ( $score >= $def['min'] ) {
				return $slug;
			}
		}
		return 'critical';
	}

	/**
	 * Get the translatable tier label.
	 *
	 * @param string $tier Tier slug.
	 * @return string
	 */
	public static function get_tier_label( $tier ) {
		$labels = array(
			'excellent'  => __( 'Excellent', 'merchant-feed-booster-lite-for-woocommerce' ),
			'good'       => __( 'Good', 'merchant-feed-booster-lite-for-woocommerce' ),
			'needs-work' => __( 'Needs Work', 'merchant-feed-booster-lite-for-woocommerce' ),
			'poor'       => __( 'Poor', 'merchant-feed-booster-lite-for-woocommerce' ),
			'critical'   => __( 'Critical', 'merchant-feed-booster-lite-for-woocommerce' ),
		);
		return isset( $labels[ $tier ] ) ? $labels[ $tier ] : $tier;
	}

	/**
	 * Get a short description for a tier.
	 *
	 * @param string $tier Tier slug.
	 * @return string
	 */
	public static function get_tier_description( $tier ) {
		$desc = array(
			'excellent'  => __( 'Feed is optimized — keep it up!', 'merchant-feed-booster-lite-for-woocommerce' ),
			'good'       => __( 'Good quality. Minor improvements possible.', 'merchant-feed-booster-lite-for-woocommerce' ),
			'needs-work' => __( 'Several issues to fix for better performance.', 'merchant-feed-booster-lite-for-woocommerce' ),
			'poor'       => __( 'Multiple issues limiting your visibility.', 'merchant-feed-booster-lite-for-woocommerce' ),
			'critical'   => __( 'Critical issues — products may be disapproved.', 'merchant-feed-booster-lite-for-woocommerce' ),
		);
		return isset( $desc[ $tier ] ) ? $desc[ $tier ] : '';
	}

	/**
	 * Get the hex color for a tier slug.
	 *
	 * @param string $tier Tier slug.
	 * @return string Hex color.
	 */
	public static function get_tier_color( $tier ) {
		return isset( self::TIERS[ $tier ] ) ? self::TIERS[ $tier ]['color'] : '#ef4444';
	}

	/**
	 * Compute the product hash used for staleness detection.
	 *
	 * @param WC_Product $product Product object.
	 * @return string MD5 hash.
	 */
	public static function product_hash( WC_Product $product ) {
		return md5( implode( '|', array(
			$product->get_name(),
			$product->get_price(),
			(int) $product->get_image_id(),
			(string) get_post_meta( $product->get_id(), '_cs_mfb_brand', true ),
			(string) get_post_meta( $product->get_id(), '_cs_mfb_gtin', true ),
			(string) $product->get_date_modified(),
		) ) );
	}

	/**
	 * Store score and issues in the audit cache table and post meta.
	 *
	 * @param int    $product_id Product post ID.
	 * @param int    $score      Computed score.
	 * @param array  $issues     Issues array from policy engine.
	 * @param string $hash       Product hash used for staleness detection.
	 */
	public static function store( $product_id, $score, array $issues, $hash ) {
		global $wpdb;

		update_post_meta( $product_id, CS_MFB_SCORE_META_KEY, $score );
		update_post_meta( $product_id, CS_MFB_SCORE_META_TIMESTAMP, time() );

		$table = $wpdb->prefix . CS_MFB_AUDIT_TABLE;
		$wpdb->replace(
			$table,
			array(
				'product_id'   => $product_id,
				'score'        => $score,
				'issues_json'  => wp_json_encode( $issues ),
				'scanned_at'   => current_time( 'mysql' ),
				'product_hash' => $hash,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Load score and issues from the audit cache table if still valid.
	 *
	 * @param int    $product_id   Product post ID.
	 * @param string $current_hash Current product hash.
	 * @return array|null Cached data or null if stale/missing.
	 */
	public static function get_stored( $product_id, $current_hash ) {
		global $wpdb;
		$table = $wpdb->prefix . CS_MFB_AUDIT_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		if ( $row['product_hash'] !== $current_hash ) {
			return null;
		}

		$age = time() - strtotime( $row['scanned_at'] );
		if ( $age > CS_MFB_SCAN_CACHE_TTL ) {
			return null;
		}

		return array(
			'score'  => (int) $row['score'],
			'issues' => json_decode( $row['issues_json'], true ) ?: array(),
		);
	}
}
