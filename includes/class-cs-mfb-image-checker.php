<?php
/**
 * Image dimension checker with post-meta caching.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Image_Checker {

	/**
	 * Register hooks.
	 */
	public static function register() {
		// Invalidate cache when a product's featured image changes.
		add_action( 'updated_post_meta', array( __CLASS__, 'maybe_clear_cache_on_thumbnail_change' ), 10, 4 );
		add_action( 'added_post_meta', array( __CLASS__, 'maybe_clear_cache_on_thumbnail_change' ), 10, 4 );
	}

	/**
	 * Clear image dimension cache when a product's featured image is changed.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $product_id Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value (new image attachment ID).
	 */
	public static function maybe_clear_cache_on_thumbnail_change( $meta_id, $product_id, $meta_key, $meta_value ) {
		if ( '_thumbnail_id' !== $meta_key ) {
			return;
		}
		delete_post_meta( $product_id, CS_MFB_IMG_CACHE_META_KEY );
	}

	/**
	 * Get image dimensions for a product's featured image.
	 *
	 * Returns cached value if available and not expired.
	 * Returns null if no image or dimensions cannot be determined.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array|null { width: int, height: int, mime: string, cached_at: int } or null.
	 */
	public static function get_dimensions( $product_id ) {
		$image_id = get_post_thumbnail_id( $product_id );
		if ( ! $image_id ) {
			return null;
		}

		$cached = get_post_meta( $product_id, CS_MFB_IMG_CACHE_META_KEY, true );
		if ( is_array( $cached ) && isset( $cached['cached_at'] ) && self::is_cache_valid( $cached ) ) {
			return $cached;
		}

		$url = wp_get_attachment_url( $image_id );
		if ( ! $url ) {
			return null;
		}

		// Use the local file path when possible to avoid HTTP overhead.
		$file = get_attached_file( $image_id );
		$source = ( $file && file_exists( $file ) ) ? $file : $url;

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$info = @getimagesize( $source );
		if ( ! $info ) {
			return null;
		}

		$data = array(
			'width'     => (int) $info[0],
			'height'    => (int) $info[1],
			'mime'      => $info['mime'],
			'cached_at' => time(),
		);

		update_post_meta( $product_id, CS_MFB_IMG_CACHE_META_KEY, $data );

		return $data;
	}

	/**
	 * Check whether the cached dimensions are still within the TTL.
	 *
	 * @param array $cached Cached data with 'cached_at' key.
	 * @return bool
	 */
	public static function is_cache_valid( array $cached ) {
		return isset( $cached['cached_at'] ) && ( time() - (int) $cached['cached_at'] ) < CS_MFB_IMG_CACHE_TTL;
	}

	/**
	 * Force-clear the cache for a specific product.
	 *
	 * @param int $product_id Product post ID.
	 */
	public static function clear_cache( $product_id ) {
		delete_post_meta( $product_id, CS_MFB_IMG_CACHE_META_KEY );
	}
}
