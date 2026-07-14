<?php
/**
 * Feed preview: parse the generated XML and render as an HTML table.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Feed_Preview {

	/** Maximum items to render in the preview table. */
	const MAX_ITEMS = 20;

	/**
	 * Read the current feed file and return an array of item data.
	 *
	 * @return array[] Array of item arrays, or empty array if feed not found.
	 */
	public static function get_items() {
		$file = CodeSolz_MFB_Feed_Generator::feed_file_path();
		if ( ! file_exists( $file ) ) {
			return array();
		}

		libxml_use_internal_errors( true );
		$xml = simplexml_load_file( $file );
		libxml_clear_errors();

		if ( ! $xml ) {
			return array();
		}

		$ns    = 'http://base.google.com/ns/1.0';
		$items = array();
		$count = 0;

		foreach ( $xml->channel->item as $item ) {
			if ( $count >= self::MAX_ITEMS ) {
				break;
			}

			$g = $item->children( $ns );

			$items[] = array(
				'id'           => (string) $g->id,
				'title'        => (string) $item->title,
				'link'         => (string) $item->link,
				'image_link'   => (string) $g->image_link,
				'availability' => (string) $g->availability,
				'condition'    => (string) $g->condition,
				'price'        => (string) $g->price,
				'sale_price'   => (string) $g->sale_price,
				'brand'        => (string) $g->brand,
				'gtin'         => (string) $g->gtin,
				'mpn'          => (string) $g->mpn,
				'category'     => (string) $g->google_product_category,
				'description'  => mb_strimwidth( wp_strip_all_tags( (string) $item->description ), 0, 120, '…' ),
			);

			$count++;
		}

		return $items;
	}

	/**
	 * Enrich items with per-item issue flags from the audit cache.
	 *
	 * For each item, looks up the audit cache by product SKU/ID match and
	 * attaches a 'issues' key for the preview table to use as cell highlights.
	 *
	 * @param array[] $items Raw items from get_items().
	 * @return array[]
	 */
	public static function enrich_with_issues( array $items ) {
		global $wpdb;
		$table = $wpdb->prefix . CS_MFB_AUDIT_TABLE;

		foreach ( $items as &$item ) {
			// Try to match by SKU (g:id is the SKU or wc-{id})
			$product = null;
			if ( is_numeric( $item['id'] ) ) {
				$product = wc_get_product( (int) $item['id'] );
			}
			if ( ! $product ) {
				$product_id = wc_get_product_id_by_sku( $item['id'] );
				if ( $product_id ) {
					$product = wc_get_product( $product_id );
				}
			}
			// fallback: strip 'wc-' prefix
			if ( ! $product && 0 === strpos( $item['id'], 'wc-' ) ) {
				$product = wc_get_product( (int) substr( $item['id'], 3 ) );
			}

			if ( ! $product ) {
				$item['issues']        = array();
				$item['score']         = null;
				$item['issue_rule_ids'] = array();
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$row = $wpdb->get_row(
				$wpdb->prepare( "SELECT score, issues_json FROM {$table} WHERE product_id = %d", $product->get_id() ),
				ARRAY_A
			);

			$issues = $row ? ( json_decode( $row['issues_json'], true ) ?: array() ) : array();
			$item['score']          = $row ? (int) $row['score'] : null;
			$item['issues']         = $issues;
			$item['issue_rule_ids'] = wp_list_pluck( $issues, 'rule_id' );
		}
		unset( $item );

		return $items;
	}

	/**
	 * Determine which feed fields are affected by a given rule ID.
	 *
	 * Used to highlight cells in the preview table.
	 *
	 * @param string $rule_id E.g. 'RULE-T03'.
	 * @return string[] Array of field keys affected.
	 */
	public static function rule_to_fields( $rule_id ) {
		$map = array(
			'RULE-T01' => array( 'title' ),
			'RULE-T02' => array( 'title' ),
			'RULE-T03' => array( 'title' ),
			'RULE-T04' => array( 'title' ),
			'RULE-T05' => array( 'title' ),
			'RULE-T06' => array( 'title' ),
			'RULE-T07' => array( 'title' ),
			'RULE-T08' => array( 'title' ),
			'RULE-I01' => array( 'image_link' ),
			'RULE-I02' => array( 'image_link' ),
			'RULE-I03' => array( 'image_link' ),
			'RULE-I04' => array( 'image_link' ),
			'RULE-I05' => array( 'image_link' ),
			'RULE-P01' => array( 'price' ),
			'RULE-P02' => array( 'price', 'sale_price' ),
			'RULE-P03' => array( 'price', 'sale_price' ),
			'RULE-P04' => array( 'price' ),
			'RULE-ID01' => array( 'gtin' ),
			'RULE-ID02' => array( 'gtin' ),
			'RULE-ID03' => array( 'gtin', 'mpn' ),
			'RULE-ID04' => array( 'brand' ),
			'RULE-D01'  => array( 'description' ),
			'RULE-D02'  => array( 'description' ),
			'RULE-D03'  => array( 'description' ),
			'RULE-D04'  => array( 'description' ),
		);

		return isset( $map[ $rule_id ] ) ? $map[ $rule_id ] : array();
	}
}
