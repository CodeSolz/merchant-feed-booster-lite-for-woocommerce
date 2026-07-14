<?php
/**
 * Policy engine: 25 named Google Merchant Center rule checks.
 *
 * Each rule method returns an issue array or null (pass).
 * Issue array shape: { rule_id, severity, message, fix_hint }
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Policy_Engine {

	/**
	 * Run all 25 rules against a product and return an array of issues.
	 *
	 * @param WC_Product $product  WooCommerce product object.
	 * @param array      $settings Plugin settings array.
	 * @return array Array of issue arrays (empty = no issues).
	 */
	public static function run_all( WC_Product $product, array $settings = array() ) {
		$title       = $product->get_name();
		$description = $product->get_description() ?: $product->get_short_description();
		$image_id    = (int) $product->get_image_id();
		$brand       = get_post_meta( $product->get_id(), '_cs_mfb_brand', true );
		$gtin        = get_post_meta( $product->get_id(), '_cs_mfb_gtin', true );
		$mpn         = get_post_meta( $product->get_id(), '_cs_mfb_mpn', true );
		$default_brand = isset( $settings['default_brand'] ) ? $settings['default_brand'] : '';

		$run_image_checks = ! isset( $settings['enable_image_checks'] ) || ! empty( $settings['enable_image_checks'] );
		$run_gtin_checks  = ! isset( $settings['enforce_gtin_mpn'] )    || ! empty( $settings['enforce_gtin_mpn'] );

		$checks = array(
			// Title
			self::check_title_too_short( $title ),
			self::check_title_too_long( $title ),
			self::check_title_promotional( $title ),
			self::check_title_all_caps( $title ),
			self::check_title_html( $title ),
			self::check_title_price( $title ),
			self::check_title_special_chars( $title ),
			self::check_title_repeated_words( $title ),
			// Image (gated by enable_image_checks)
			self::check_image_missing( $image_id ),
			$run_image_checks ? self::check_image_too_small( $product->get_id(), $image_id ) : null,
			$run_image_checks ? self::check_image_below_recommended( $product->get_id(), $image_id ) : null,
			$run_image_checks ? self::check_image_mime( $product->get_id(), $image_id ) : null,
			$run_image_checks ? self::check_image_url_params( $image_id ) : null,
			// Price
			self::check_price_missing( $product ),
			self::check_price_orphan_sale( $product ),
			self::check_price_sale_gte_regular( $product ),
			self::check_price_format( $product ),
			// Identifiers (gated by enforce_gtin_mpn)
			$run_gtin_checks ? self::check_gtin_digit_count( $gtin ) : null,
			$run_gtin_checks ? self::check_gtin_check_digit( $gtin ) : null,
			$run_gtin_checks ? self::check_identifier_brand_no_gtin_mpn( $brand, $default_brand, $gtin, $mpn ) : null,
			$run_gtin_checks ? self::check_identifier_no_brand( $brand, $default_brand ) : null,
			// Description
			self::check_description_missing( $description ),
			self::check_description_too_short( $description ),
			self::check_description_html( $description ),
			self::check_description_promotional( $description ),
		);

		$issues = array_values( array_filter( $checks ) );

		return apply_filters( 'cs_mfb_policy_rules', $issues, $product );
	}

	// -------------------------------------------------------------------------
	// TITLE RULES
	// -------------------------------------------------------------------------

	/** RULE-T01: Title too short (< 25 chars). */
	public static function check_title_too_short( $title ) {
		$len = mb_strlen( wp_strip_all_tags( $title ) );
		if ( $len >= 25 ) {
			return null;
		}
		return self::issue(
			'RULE-T01', 'error',
			/* translators: %d = character count */
			sprintf( __( 'Title is only %d characters. Google requires at least 25 for meaningful titles.', 'merchant-feed-booster-lite-for-woocommerce' ), $len ),
			__( 'Add product color, material, size, or model number to the title.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-T02: Title too long (> 150 chars). */
	public static function check_title_too_long( $title ) {
		$len = mb_strlen( wp_strip_all_tags( $title ) );
		if ( $len <= 150 ) {
			return null;
		}
		return self::issue(
			'RULE-T02', 'warning',
			/* translators: %d = character count */
			sprintf( __( 'Title is %d characters. Google truncates titles at 150 characters in ads.', 'merchant-feed-booster-lite-for-woocommerce' ), $len ),
			__( 'Trim to 150 characters, keeping brand and key attributes at the front.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-T03: Promotional words in title. */
	public static function check_title_promotional( $title ) {
		$word = self::find_promotional_word( wp_strip_all_tags( $title ) );
		if ( ! $word ) {
			return null;
		}
		return self::issue(
			'RULE-T03', 'error',
			/* translators: %s = the offending word */
			sprintf( __( 'Title contains promotional language: "%s". Google disapproves products with promotional text in titles.', 'merchant-feed-booster-lite-for-woocommerce' ), esc_html( $word ) ),
			__( 'Remove promotional words. Describe the product, not the deal.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-T04: ALL CAPS words in title (≥ 3 alpha chars, ≥ 2 such words). */
	public static function check_title_all_caps( $title ) {
		$words    = preg_split( '/\s+/', wp_strip_all_tags( $title ) );
		$caps     = array();
		$known_ok = array( 'USB', 'LED', 'TV', 'HD', 'UHD', '4K', 'AC', 'DC', 'UV', 'XL', 'XXL', 'UK', 'US', 'EU', 'ID', 'PC', 'LCD', 'CPU', 'GPU', 'SSD', 'RAM' );

		foreach ( $words as $word ) {
			$alpha = preg_replace( '/[^A-Z]/i', '', $word );
			if ( strlen( $alpha ) >= 3 && strtoupper( $alpha ) === $alpha && ! in_array( $alpha, $known_ok, true ) ) {
				$caps[] = $word;
			}
		}

		if ( count( $caps ) < 2 ) {
			return null;
		}

		return self::issue(
			'RULE-T04', 'error',
			/* translators: %s = list of ALL CAPS words */
			sprintf( __( 'Title contains ALL CAPS words: "%s". Google disallows excessive capitalization.', 'merchant-feed-booster-lite-for-woocommerce' ), esc_html( implode( '", "', $caps ) ) ),
			__( 'Use sentence case or title case. Acronyms like USB and LED are allowed.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-T05: HTML tags or entities in title. */
	public static function check_title_html( $title ) {
		if ( $title === strip_tags( $title ) && ! preg_match( '/&[a-zA-Z#][a-zA-Z0-9]+;/', $title ) ) {
			return null;
		}
		return self::issue(
			'RULE-T05', 'error',
			__( 'Title contains HTML tags or entities. Feed titles must be plain text.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Remove all HTML from the product title.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-T06: Price or currency symbols in title. */
	public static function check_title_price( $title ) {
		if ( ! preg_match( '/(\$|£|€|USD|EUR|GBP|\d+\.\d{2})/', $title ) ) {
			return null;
		}
		return self::issue(
			'RULE-T06', 'error',
			__( 'Title appears to contain price information. Prices in titles are prohibited by Google.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Remove the price from the product title.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-T07: Title starts or ends with special characters. */
	public static function check_title_special_chars( $title ) {
		$trimmed = wp_strip_all_tags( trim( $title ) );
		if ( ! preg_match( '/^[_|\-~.,;]|[_|\-~.,;]$/', $trimmed ) ) {
			return null;
		}
		return self::issue(
			'RULE-T07', 'warning',
			__( 'Title starts or ends with a special character. This may cause formatting issues in Google ads.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Remove the leading or trailing special character from the title.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-T08: Repeated word (4+ chars, non-stopword) appears 3+ times. */
	public static function check_title_repeated_words( $title ) {
		$stopwords = array( 'with', 'for', 'and', 'the', 'this', 'that', 'from', 'into', 'over', 'your', 'more' );
		$words     = preg_split( '/\s+/', strtolower( wp_strip_all_tags( $title ) ) );
		$freq      = array();

		foreach ( $words as $word ) {
			$word = preg_replace( '/[^a-z]/', '', $word );
			if ( strlen( $word ) >= 4 && ! in_array( $word, $stopwords, true ) ) {
				$freq[ $word ] = isset( $freq[ $word ] ) ? $freq[ $word ] + 1 : 1;
			}
		}

		foreach ( $freq as $word => $count ) {
			if ( $count >= 3 ) {
				return self::issue(
					'RULE-T08', 'notice',
					/* translators: %1$s = word, %2$d = count */
					sprintf( __( 'Title contains the repeated word "%1$s" (%2$d times). This may appear spammy.', 'merchant-feed-booster-lite-for-woocommerce' ), esc_html( $word ), $count ),
					__( 'Vary your language or remove the repeated word.', 'merchant-feed-booster-lite-for-woocommerce' )
				);
			}
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// IMAGE RULES
	// -------------------------------------------------------------------------

	/** RULE-I01: No featured image. */
	public static function check_image_missing( $image_id ) {
		if ( $image_id > 0 ) {
			return null;
		}
		return self::issue(
			'RULE-I01', 'error',
			__( 'No product image. Google requires at least one image for every product.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Upload a product image via the product edit screen.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-I02: Image < 100×100 px (feed will be rejected). */
	public static function check_image_too_small( $product_id, $image_id ) {
		if ( ! $image_id ) {
			return null;
		}
		$dims = CodeSolz_MFB_Image_Checker::get_dimensions( $product_id );
		if ( ! $dims || ( $dims['width'] >= 100 && $dims['height'] >= 100 ) ) {
			return null;
		}
		return self::issue(
			'RULE-I02', 'error',
			/* translators: %1$d = width, %2$d = height */
			sprintf( __( 'Image is %1$d×%2$d pixels. Google requires at least 100×100 px. Feed submissions will fail.', 'merchant-feed-booster-lite-for-woocommerce' ), $dims['width'], $dims['height'] ),
			__( 'Replace with an image at least 100×100 px. 800×800 px or larger is strongly recommended.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-I03: Image < 250×250 px (below recommended). */
	public static function check_image_below_recommended( $product_id, $image_id ) {
		if ( ! $image_id ) {
			return null;
		}
		$dims = CodeSolz_MFB_Image_Checker::get_dimensions( $product_id );
		if ( ! $dims ) {
			return null;
		}
		// Only warn for images that are >= 100px (RULE-I02 already covers < 100)
		if ( $dims['width'] < 100 || $dims['height'] < 100 ) {
			return null;
		}
		if ( $dims['width'] >= 250 && $dims['height'] >= 250 ) {
			return null;
		}
		return self::issue(
			'RULE-I03', 'warning',
			/* translators: %1$d = width, %2$d = height */
			sprintf( __( 'Image is %1$d×%2$d pixels. Google recommends at least 250×250 px. Smaller images may perform worse in ads.', 'merchant-feed-booster-lite-for-woocommerce' ), $dims['width'], $dims['height'] ),
			__( 'Upload a higher-resolution product image.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-I04: Non-standard image MIME type. */
	public static function check_image_mime( $product_id, $image_id ) {
		if ( ! $image_id ) {
			return null;
		}
		$dims      = CodeSolz_MFB_Image_Checker::get_dimensions( $product_id );
		$allowed   = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		if ( ! $dims || in_array( $dims['mime'], $allowed, true ) ) {
			return null;
		}
		return self::issue(
			'RULE-I04', 'warning',
			/* translators: %s = MIME type */
			sprintf( __( 'Image format is %s. Google prefers JPEG, PNG, GIF, or WebP.', 'merchant-feed-booster-lite-for-woocommerce' ), esc_html( $dims['mime'] ) ),
			__( 'Convert the image to JPEG or PNG format.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-I05: Image URL has tracking query parameters. */
	public static function check_image_url_params( $image_id ) {
		if ( ! $image_id ) {
			return null;
		}
		$url = wp_get_attachment_url( $image_id );
		if ( ! $url || strpos( $url, '?' ) === false ) {
			return null;
		}
		return self::issue(
			'RULE-I05', 'notice',
			__( 'Image URL contains query parameters. This may cause cache-busting issues with Google\'s image crawler.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Regenerate the image attachment to get a clean URL without query strings.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	// -------------------------------------------------------------------------
	// PRICE RULES
	// -------------------------------------------------------------------------

	/** RULE-P01: No price or zero price. */
	public static function check_price_missing( WC_Product $product ) {
		$price = $product->get_regular_price();
		if ( '' === $price ) {
			$price = $product->get_price();
		}
		if ( is_numeric( $price ) && (float) $price > 0 ) {
			return null;
		}
		return self::issue(
			'RULE-P01', 'error',
			__( 'Product has no price or a zero price. Feed items without a valid price are rejected by Google.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Set a regular price greater than zero for this product.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-P02: Sale price exists but regular price is empty. */
	public static function check_price_orphan_sale( WC_Product $product ) {
		$sale    = $product->get_sale_price();
		$regular = $product->get_regular_price();
		if ( '' === $sale || '' !== $regular ) {
			return null;
		}
		return self::issue(
			'RULE-P02', 'warning',
			__( 'Product has a sale price but no regular price. Google requires both when using g:sale_price.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Set a regular price higher than the current sale price.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-P03: Sale price >= regular price. */
	public static function check_price_sale_gte_regular( WC_Product $product ) {
		$sale    = $product->get_sale_price();
		$regular = $product->get_regular_price();
		if ( '' === $sale || '' === $regular ) {
			return null;
		}
		if ( (float) $sale < (float) $regular ) {
			return null;
		}
		return self::issue(
			'RULE-P03', 'warning',
			/* translators: %1$s = sale price, %2$s = regular price */
			sprintf( __( 'Sale price (%1$s) is not less than the regular price (%2$s). This is likely a data entry error.', 'merchant-feed-booster-lite-for-woocommerce' ), esc_html( $sale ), esc_html( $regular ) ),
			__( 'Ensure the sale price is lower than the regular price, or remove the sale price.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-P04: Price value is non-numeric. */
	public static function check_price_format( WC_Product $product ) {
		$price = $product->get_regular_price();
		if ( '' === $price ) {
			return null; // Covered by RULE-P01
		}
		if ( is_numeric( $price ) ) {
			return null;
		}
		return self::issue(
			'RULE-P04', 'error',
			__( 'Regular price contains non-numeric characters and cannot be formatted for the feed.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Edit the product and set the price to a plain decimal number.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	// -------------------------------------------------------------------------
	// IDENTIFIER RULES
	// -------------------------------------------------------------------------

	/** RULE-ID01: GTIN has wrong digit count. */
	public static function check_gtin_digit_count( $gtin ) {
		if ( '' === (string) $gtin ) {
			return null;
		}
		$result = CodeSolz_MFB_GTIN_Validator::validate( $gtin );
		if ( 'wrong_length' !== $result['error'] ) {
			return null;
		}
		return self::issue(
			'RULE-ID01', 'error',
			/* translators: %1$s = gtin value, %2$d = digit count */
			sprintf( __( 'GTIN "%1$s" has %2$d digits. Valid GTINs must be 8, 12, 13, or 14 digits.', 'merchant-feed-booster-lite-for-woocommerce' ), esc_html( $gtin ), $result['digit_count'] ),
			__( 'Check the GTIN on the product packaging. UPC-A = 12 digits, EAN-13 = 13 digits.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-ID02: GTIN fails check digit validation. */
	public static function check_gtin_check_digit( $gtin ) {
		if ( '' === (string) $gtin ) {
			return null;
		}
		$result = CodeSolz_MFB_GTIN_Validator::validate( $gtin );
		if ( 'invalid_check_digit' !== $result['error'] ) {
			return null;
		}
		return self::issue(
			'RULE-ID02', 'error',
			/* translators: %s = gtin value */
			sprintf( __( 'GTIN "%s" has an invalid check digit. It may have been copied incorrectly.', 'merchant-feed-booster-lite-for-woocommerce' ), esc_html( $gtin ) ),
			__( 'Verify the GTIN from the original product packaging or the GS1 database.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-ID03: Brand is set but no GTIN or MPN. */
	public static function check_identifier_brand_no_gtin_mpn( $brand, $default_brand, $gtin, $mpn ) {
		$has_brand = ( '' !== trim( (string) $brand ) || '' !== trim( (string) $default_brand ) );
		$has_id    = ( '' !== trim( (string) $gtin ) || '' !== trim( (string) $mpn ) );
		if ( ! $has_brand || $has_id ) {
			return null;
		}
		return self::issue(
			'RULE-ID03', 'warning',
			__( 'Brand is set but no GTIN or MPN is provided. The feed will output identifier_exists=no, which may reduce ad visibility.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Add a GTIN or MPN for this product. If no identifier exists, this warning can be ignored.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-ID04: No brand and no default brand. */
	public static function check_identifier_no_brand( $brand, $default_brand ) {
		if ( '' !== trim( (string) $brand ) || '' !== trim( (string) $default_brand ) ) {
			return null;
		}
		return self::issue(
			'RULE-ID04', 'error',
			__( 'No brand set for this product and no default brand configured. Brand is required for most Google product categories.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Add a brand to this product, or set a default brand in Merchant Feed → Settings.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	// -------------------------------------------------------------------------
	// DESCRIPTION RULES
	// -------------------------------------------------------------------------

	/** RULE-D01: No description. */
	public static function check_description_missing( $description ) {
		if ( '' !== trim( wp_strip_all_tags( (string) $description ) ) ) {
			return null;
		}
		return self::issue(
			'RULE-D01', 'warning',
			__( 'No product description. Google uses the description for quality scoring and ad relevance.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Add a product description with key features, materials, and intended use.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-D02: Description shorter than 50 characters. */
	public static function check_description_too_short( $description ) {
		$text = wp_strip_all_tags( (string) $description );
		$len  = mb_strlen( $text );
		if ( '' === $text || $len >= 50 ) {
			return null;
		}
		return self::issue(
			'RULE-D02', 'notice',
			/* translators: %d = character count */
			sprintf( __( 'Description is only %d characters. Richer descriptions improve ad targeting.', 'merchant-feed-booster-lite-for-woocommerce' ), $len ),
			__( 'Expand the description to include product features, materials, and intended use (aim for 150+ characters).', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-D03: HTML tags in description. */
	public static function check_description_html( $description ) {
		if ( (string) $description === strip_tags( (string) $description ) ) {
			return null;
		}
		return self::issue(
			'RULE-D03', 'warning',
			__( 'Product description contains HTML tags. Feed descriptions should be plain text.', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Remove HTML formatting, or use the Short Description field (plain text) as the feed description.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/** RULE-D04: Promotional language in description. */
	public static function check_description_promotional( $description ) {
		$word = self::find_promotional_word( wp_strip_all_tags( (string) $description ) );
		if ( ! $word ) {
			return null;
		}
		return self::issue(
			'RULE-D04', 'warning',
			/* translators: %s = the offending word */
			sprintf( __( 'Description contains promotional language: "%s". Google may penalise this.', 'merchant-feed-booster-lite-for-woocommerce' ), esc_html( $word ) ),
			__( 'Focus on product features and specifications, not sales language.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	// -------------------------------------------------------------------------
	// HELPERS
	// -------------------------------------------------------------------------

	/**
	 * Find the first promotional word in a string.
	 *
	 * @param string $text Plain text.
	 * @return string|null The matched word, or null if none found.
	 */
	protected static function find_promotional_word( $text ) {
		$words = apply_filters( 'cs_mfb_promotional_words', array(
			'free', 'sale', 'discount', 'cheap', 'clearance', 'limited time',
			'act now', 'order now', 'buy now', 'best price', 'lowest price',
			'special offer', 'promo', 'bonus', 'gift', 'save', 'off',
			'percent off', '% off', 'deal', 'bargain', 'flash sale',
		) );

		$lower = strtolower( $text );
		foreach ( $words as $word ) {
			if ( false !== strpos( $lower, strtolower( $word ) ) ) {
				return $word;
			}
		}

		return null;
	}

	/**
	 * Build a structured issue array.
	 *
	 * @param string $rule_id  Rule identifier (e.g. 'RULE-T03').
	 * @param string $severity 'error' | 'warning' | 'notice'
	 * @param string $message  Human-readable problem description.
	 * @param string $fix_hint Actionable fix guidance.
	 * @return array
	 */
	protected static function issue( $rule_id, $severity, $message, $fix_hint ) {
		return array(
			'rule_id'  => $rule_id,
			'severity' => $severity,
			'message'  => $message,
			'fix_hint' => $fix_hint,
		);
	}
}
