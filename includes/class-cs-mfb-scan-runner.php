<?php
/**
 * Background batch scan runner using transient state.
 *
 * JS polls the REST progress endpoint; each call processes the next batch.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Scan_Runner {

	/**
	 * Initialise a new scan. Clears any existing transient state.
	 *
	 * Stores only a small offset counter in the transient — not the full ID list —
	 * to avoid memory spikes on large catalogs.
	 *
	 * @return array { batch_id: string, total_products: int }
	 */
	public static function start() {
		$count_query = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		) );

		$total    = (int) $count_query->found_posts;
		$batch_id = wp_generate_uuid4();

		$state = array(
			'batch_id'  => $batch_id,
			'total'     => $total,
			'offset'    => 0,
			'processed' => 0,
			'status'    => 'running',
		);

		set_transient( CS_MFB_SCAN_TRANSIENT, $state, HOUR_IN_SECONDS );

		return array(
			'batch_id'       => $batch_id,
			'total_products' => $total,
		);
	}

	/**
	 * Process the next batch of products and update transient state.
	 *
	 * Uses offset-based DB pagination so the transient never holds ID arrays.
	 *
	 * @return array { processed: int, total: int, pct: int, status: string, batch_results: array }
	 */
	public static function next_batch() {
		$state = get_transient( CS_MFB_SCAN_TRANSIENT );

		if ( ! is_array( $state ) || 'running' !== $state['status'] ) {
			return array(
				'processed'     => 0,
				'total'         => 0,
				'pct'           => 100,
				'status'        => 'done',
				'batch_results' => array(),
			);
		}

		$settings   = CodeSolz_MFB_Settings::all();
		$dup_index  = CodeSolz_MFB_Health::build_duplicate_index();
		$batch_size = CS_MFB_SCAN_BATCH_SIZE;

		$query = new WP_Query( array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $batch_size,
			'offset'                 => $state['offset'],
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		) );

		$product_ids = $query->posts;
		$results     = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$state['processed']++;
				continue;
			}

			$hash   = CodeSolz_MFB_Health_Score::product_hash( $product );
			$cached = CodeSolz_MFB_Health_Score::get_stored( $product_id, $hash );

			if ( $cached ) {
				$score  = $cached['score'];
				$issues = $cached['issues'];
			} else {
				$issues = CodeSolz_MFB_Policy_Engine::run_all( $product, $settings, $dup_index );
				$score  = CodeSolz_MFB_Health_Score::calculate( $product, $issues, $settings );
				CodeSolz_MFB_Health_Score::store( $product_id, $score, $issues, $hash );
			}

			do_action( 'cs_mfb_after_product_scored', $product_id, $score, $issues );

			$results[] = array(
				'id'     => $product_id,
				'title'  => $product->get_name(),
				'sku'    => $product->get_sku(),
				'score'  => $score,
				'tier'   => CodeSolz_MFB_Health_Score::get_tier( $score ),
				'issues' => $issues,
				'edit'   => get_edit_post_link( $product_id, 'raw' ),
				'link'   => $product->get_permalink(),
			);

			$state['processed']++;
		}

		$fetched         = count( $product_ids );
		$state['offset'] += $fetched;
		$done            = $fetched < $batch_size || $state['offset'] >= $state['total'];
		$state['status'] = $done ? 'done' : 'running';

		set_transient( CS_MFB_SCAN_TRANSIENT, $state, HOUR_IN_SECONDS );

		if ( $done && class_exists( 'CodeSolz_MFB_Admin' ) ) {
			CodeSolz_MFB_Admin::log_activity(
				'scan',
				sprintf(
					/* translators: %d: number of products scanned */
					__( 'Feed health scan complete — %d products scanned.', 'merchant-feed-booster-lite-for-woocommerce' ),
					(int) $state['total']
				)
			);
		}

		$pct = $state['total'] > 0 ? (int) round( $state['processed'] / $state['total'] * 100 ) : 100;

		return array(
			'processed'     => $state['processed'],
			'total'         => $state['total'],
			'pct'           => $pct,
			'status'        => $state['status'],
			'batch_results' => $results,
		);
	}

	/**
	 * Return the current transient state without advancing it.
	 *
	 * @return array|false
	 */
	public static function get_state() {
		return get_transient( CS_MFB_SCAN_TRANSIENT );
	}

	/**
	 * Clear scan transient and audit cache table.
	 */
	public static function clear() {
		global $wpdb;
		delete_transient( CS_MFB_SCAN_TRANSIENT );
		$table = $wpdb->prefix . CS_MFB_AUDIT_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "TRUNCATE TABLE `{$table}`" );
	}

	/**
	 * Check whether a single product's cached row is stale.
	 *
	 * @param WC_Product $product Product object.
	 * @return bool True if stale (needs rescan).
	 */
	public static function is_stale( WC_Product $product ) {
		$hash   = CodeSolz_MFB_Health_Score::product_hash( $product );
		$cached = CodeSolz_MFB_Health_Score::get_stored( $product->get_id(), $hash );
		return null === $cached;
	}
}
