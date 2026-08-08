<?php
/**
 * Google Merchant XML feed generator. Writes uploads/codesolz-feeds/google-products.xml.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Feed_Generator {

	const GOOGLE_NS    = 'http://base.google.com/ns/1.0';
	const BATCH_SIZE   = 200;
	const LAST_RUN_KEY = 'cs_mfb_last_run';

	/**
	 * Register hooks.
	 */
	public static function register() {
		// No public hooks; called from admin actions and cron.
	}

	/**
	 * Resolve the uploads path used for the feed directory.
	 *
	 * @return array{basedir:string, baseurl:string}|null
	 */
	protected static function uploads() {
		$uploads = wp_upload_dir( null, false );
		if ( ! is_array( $uploads ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return null;
		}
		return array(
			'basedir' => $uploads['basedir'],
			'baseurl' => $uploads['baseurl'],
		);
	}

	/**
	 * Absolute path to the feed file (may not yet exist).
	 *
	 * @return string|null
	 */
	public static function feed_file_path() {
		$u = self::uploads();
		if ( ! $u ) {
			return null;
		}
		return trailingslashit( $u['basedir'] ) . CS_MFB_FEED_DIRNAME . '/' . CS_MFB_FEED_FILENAME;
	}

	/**
	 * Public URL to the feed file (does not check existence).
	 *
	 * @return string|null
	 */
	public static function public_feed_url() {
		$u = self::uploads();
		if ( ! $u ) {
			return null;
		}
		return trailingslashit( $u['baseurl'] ) . CS_MFB_FEED_DIRNAME . '/' . CS_MFB_FEED_FILENAME;
	}

	/**
	 * Check whether the public feed URL actually responds over HTTP.
	 *
	 * Mirrors WordPress core's own loopback-request pattern (see
	 * WP_Site_Health::get_test_loopback_requests()) — same-origin check, so SSL
	 * verification is skipped to avoid false negatives on local/self-signed setups.
	 * Result is cached for 15 minutes so the dashboard doesn't fire a request per page load.
	 *
	 * @param string $url Public feed URL.
	 * @return bool
	 */
	public static function is_publicly_accessible( $url ) {
		if ( ! $url ) {
			return false;
		}

		$cache_key = 'cs_mfb_feed_reachable';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return 'yes' === $cached;
		}

		$args = array(
			'timeout'   => 5,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);

		$response = wp_remote_head( $url, $args );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Some servers reject HEAD requests; fall back to a lightweight GET.
			$response = wp_remote_get( $url, $args );
		}

		$accessible = ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );

		set_transient( $cache_key, $accessible ? 'yes' : 'no', 15 * MINUTE_IN_SECONDS );

		return $accessible;
	}

	/**
	 * Clear the cached feed-accessibility result so the next dashboard load re-checks it.
	 */
	public static function clear_accessibility_cache() {
		delete_transient( 'cs_mfb_feed_reachable' );
	}

	/**
	 * Make sure the feed directory exists and contains a privacy index.html.
	 *
	 * @return string|WP_Error Directory path or error.
	 */
	public static function ensure_feed_dir() {
		$u = self::uploads();
		if ( ! $u ) {
			return new WP_Error( 'cs_mfb_no_uploads', __( 'Could not resolve uploads directory.', 'merchant-feed-booster-lite-for-woocommerce' ) );
		}
		$dir = trailingslashit( $u['basedir'] ) . CS_MFB_FEED_DIRNAME;

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'cs_mfb_mkdir_failed', __( 'Could not create feed directory.', 'merchant-feed-booster-lite-for-woocommerce' ) );
		}

		// Prevent directory listing.
		$index = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			// Best-effort, ignore errors.
			@file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions, Generic.PHP.NoSilencedErrors
		}

		return $dir;
	}

	/**
	 * Generate the feed file.
	 *
	 * @return array{written:int, skipped_oos:int, file:string}|WP_Error
	 */
	public static function generate() {
		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			return new WP_Error( 'cs_mfb_wc_missing', __( 'WooCommerce is not active.', 'merchant-feed-booster-lite-for-woocommerce' ) );
		}

		$settings = CodeSolz_MFB_Settings::all();
		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'cs_mfb_disabled', __( 'Feed generation is disabled in settings.', 'merchant-feed-booster-lite-for-woocommerce' ) );
		}

		$dir = self::ensure_feed_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$file = self::feed_file_path();
		if ( ! $file ) {
			return new WP_Error( 'cs_mfb_no_file', __( 'Feed file path could not be resolved.', 'merchant-feed-booster-lite-for-woocommerce' ) );
		}

		$tmp = $file . '.tmp';
		$fh  = @fopen( $tmp, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions, Generic.PHP.NoSilencedErrors
		if ( ! $fh ) {
			return new WP_Error( 'cs_mfb_fopen_failed', __( 'Could not open feed file for writing.', 'merchant-feed-booster-lite-for-woocommerce' ) );
		}

		$title       = $settings['feed_title'] ? $settings['feed_title'] : get_bloginfo( 'name' );
		$site_url    = home_url( '/' );
		$description = sprintf(
			/* translators: %s: site name */
			__( 'Google Merchant product feed for %s.', 'merchant-feed-booster-lite-for-woocommerce' ),
			get_bloginfo( 'name' )
		);

		fwrite( $fh, '<?xml version="1.0" encoding="UTF-8"?>' . "\n" );
		fwrite( $fh, '<rss version="2.0" xmlns:g="' . self::GOOGLE_NS . '">' . "\n" );
		fwrite( $fh, '<channel>' . "\n" );
		fwrite( $fh, '  <title>' . self::xml_text( $title ) . '</title>' . "\n" );
		fwrite( $fh, '  <link>' . self::xml_text( $site_url ) . '</link>' . "\n" );
		fwrite( $fh, '  <description>' . self::xml_text( $description ) . '</description>' . "\n" );

		$page         = 1;
		$written      = 0;
		$skipped_oos  = 0;
		$include_oos  = ! empty( $settings['include_oos'] );

		do {
			$query = new WP_Query(
				array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'posts_per_page'         => self::BATCH_SIZE,
					'paged'                  => $page,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( ! $product || ! is_object( $product ) ) {
					continue;
				}
				if ( ! $product->is_visible() && ! apply_filters( 'cs_mfb_include_hidden', false, $product ) ) {
					continue;
				}
				if ( ! $include_oos && ! $product->is_in_stock() ) {
					$skipped_oos++;
					continue;
				}

				$include = apply_filters( 'cs_mfb_should_include_product', true, $product, $settings );
				if ( ! $include ) {
					continue;
				}

				$xml = self::build_item_xml( $product, $settings );
				if ( $xml ) {
					fwrite( $fh, $xml );
					$written++;
				}
			}

			$page++;
		} while ( count( $query->posts ) === self::BATCH_SIZE );

		fwrite( $fh, '</channel>' . "\n" );
		fwrite( $fh, '</rss>' . "\n" );
		fclose( $fh );

		if ( ! @rename( $tmp, $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions, Generic.PHP.NoSilencedErrors
			@unlink( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions, Generic.PHP.NoSilencedErrors
			return new WP_Error( 'cs_mfb_rename_failed', __( 'Could not finalize feed file.', 'merchant-feed-booster-lite-for-woocommerce' ) );
		}

		self::clear_accessibility_cache();

		update_option(
			self::LAST_RUN_KEY,
			array(
				'time'         => time(),
				'written'      => $written,
				'skipped_oos'  => $skipped_oos,
			),
			false
		);

		do_action( 'cs_mfb_feed_generated', 'google', $written, $skipped_oos, $file );

		return array(
			'written'     => $written,
			'skipped_oos' => $skipped_oos,
			'file'        => $file,
		);
	}

	/**
	 * Build the <item> XML for a product.
	 *
	 * @param WC_Product $product  Product.
	 * @param array      $settings Settings array.
	 * @return string
	 */
	protected static function build_item_xml( $product, $settings ) {
		$id          = $product->get_id();
		$title_raw   = $product->get_name();
		$prefix      = isset( $settings['feed_prefix'] ) ? trim( (string) $settings['feed_prefix'] ) : '';
		$title       = $prefix !== '' ? trim( $prefix . ' ' . $title_raw ) : $title_raw;
		$clean_override = get_post_meta( $id, '_cs_mfb_clean_description', true );
		if ( '' !== $clean_override ) {
			$desc = wp_strip_all_tags( (string) $clean_override );
		} else {
			$desc_raw = $product->get_description();
			if ( '' === trim( wp_strip_all_tags( (string) $desc_raw ) ) ) {
				$desc_raw = $product->get_short_description();
			}
			$desc = wp_strip_all_tags( (string) $desc_raw );
		}
		$link        = get_permalink( $id );
		$image_id    = $product->get_image_id();
		$image_url   = $image_id ? wp_get_attachment_url( $image_id ) : '';

		$availability = $product->is_in_stock() ? 'in_stock' : 'out_of_stock';

		$price_amount = $product->get_regular_price();
		if ( '' === $price_amount || null === $price_amount ) {
			$price_amount = $product->get_price();
		}
		$sale_price   = $product->get_sale_price();
		$currency     = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';

		$brand     = (string) get_post_meta( $id, CodeSolz_MFB_Product_Fields::META_BRAND, true );
		if ( '' === $brand ) {
			$brand = (string) $settings['default_brand'];
		}
		$gtin      = (string) get_post_meta( $id, CodeSolz_MFB_Product_Fields::META_GTIN, true );
		$mpn       = (string) get_post_meta( $id, CodeSolz_MFB_Product_Fields::META_MPN, true );
		$gcat      = (string) get_post_meta( $id, CodeSolz_MFB_Product_Fields::META_GCAT, true );
		if ( '' === $gcat ) {
			$gcat = (string) $settings['default_google_category'];
		}
		$condition = (string) get_post_meta( $id, CodeSolz_MFB_Product_Fields::META_CONDITION, true );
		if ( '' === $condition ) {
			$condition = 'new';
		}
		$sku = $product->get_sku();
		if ( '' === $sku ) {
			$sku = 'wc-' . $id;
		}

		$identifier_exists = ( $brand && ( $gtin || $mpn ) ) ? 'yes' : 'no';

		// Build structured item data array so filters can modify before XML output.
		$item_data = array(
			'id'                    => $sku,
			'title'                 => $title,
			'description'           => $desc,
			'link'                  => $link,
			'image_link'            => $image_url,
			'availability'          => $availability,
			'condition'             => $condition,
			'price'                 => $price_amount,
			'sale_price'            => $sale_price,
			'currency'              => $currency,
			'brand'                 => $brand,
			'gtin'                  => $gtin,
			'mpn'                   => $mpn,
			'google_product_category' => $gcat,
			'identifier_exists'     => $identifier_exists,
		);

		$item_data = apply_filters( 'cs_mfb_item_data', $item_data, $product, $settings );
		do_action( 'cs_mfb_before_item_written', $item_data, $product );

		$out  = "  <item>\n";
		$out .= self::xml_node( 'g:id', $item_data['id'], true );
		$out .= self::xml_node( 'title', $item_data['title'] );
		if ( '' !== (string) $item_data['description'] ) {
			$out .= self::xml_node( 'description', $item_data['description'] );
		}
		$out .= self::xml_node( 'link', $item_data['link'] );
		if ( $item_data['image_link'] ) {
			$out .= self::xml_node( 'g:image_link', $item_data['image_link'] );
		}
		$out .= self::xml_node( 'g:availability', $item_data['availability'], true );
		$out .= self::xml_node( 'g:condition', $item_data['condition'], true );

		if ( '' !== (string) $item_data['price'] && is_numeric( $item_data['price'] ) ) {
			$price_value = number_format( (float) $item_data['price'], 2, '.', '' );
			$out        .= self::xml_node( 'g:price', $price_value . ' ' . $item_data['currency'], true );
		}
		if ( '' !== (string) $item_data['sale_price'] && is_numeric( $item_data['sale_price'] ) ) {
			$sale_value = number_format( (float) $item_data['sale_price'], 2, '.', '' );
			$out       .= self::xml_node( 'g:sale_price', $sale_value . ' ' . $item_data['currency'], true );
		}

		if ( $item_data['brand'] ) {
			$out .= self::xml_node( 'g:brand', $item_data['brand'] );
		}
		if ( $item_data['gtin'] ) {
			$out .= self::xml_node( 'g:gtin', $item_data['gtin'], true );
		}
		if ( $item_data['mpn'] ) {
			$out .= self::xml_node( 'g:mpn', $item_data['mpn'] );
		}
		if ( $item_data['google_product_category'] ) {
			$out .= self::xml_node( 'g:google_product_category', $item_data['google_product_category'] );
		}
		$out .= self::xml_node( 'g:identifier_exists', $item_data['identifier_exists'], true );

		// Extra fields from Pro or third-party hooks.
		$extra_fields = apply_filters( 'cs_mfb_extra_item_fields', array(), $product, $settings );
		foreach ( $extra_fields as $field_tag => $field_value ) {
			$out .= self::xml_node( $field_tag, $field_value );
		}

		$out .= "  </item>\n";

		return $out;
	}

	/**
	 * Serialize a tag with CDATA-safe text content.
	 *
	 * @param string $tag     XML tag.
	 * @param string $value   Value.
	 * @param bool   $plain   If true, use plain text (no CDATA), suitable for known-safe values.
	 * @return string
	 */
	protected static function xml_node( $tag, $value, $plain = false ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		if ( $plain ) {
			return '    <' . $tag . '>' . self::xml_text( $value ) . '</' . $tag . '>' . "\n";
		}
		return '    <' . $tag . '><![CDATA[' . self::cdata_safe( $value ) . ']]></' . $tag . '>' . "\n";
	}

	/**
	 * Escape text for plain XML node bodies / attributes.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected static function xml_text( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Make a value safe to nest inside a CDATA section.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected static function cdata_safe( $value ) {
		return str_replace( ']]>', ']]]]><![CDATA[>', (string) $value );
	}
}
