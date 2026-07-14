<?php
/**
 * Feed health scanner: runs Policy Engine checks per product, stores scores.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Health {

	const SCAN_LIMIT    = 5000;
	const DISPLAY_LIMIT = 50; // per page in UI

	/**
	 * Synchronous scan used for small catalogs (<= SCAN_LIMIT).
	 * For large catalogs, use CS_MFB_Scan_Runner (background batch).
	 *
	 * @param array $args Optional overrides: page, per_page, issue_type_filter, issues_only.
	 * @return array{
	 *   totals: array,
	 *   rows: array,
	 *   total_rows: int,
	 *   page: int,
	 *   per_page: int,
	 *   truncated: bool
	 * }
	 */
	public static function scan( array $args = array() ) {
		$settings    = CodeSolz_MFB_Settings::all();
		$include_oos = ! empty( $settings['include_oos'] );

		$current_page      = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page          = (int) ( $args['per_page'] ?? self::DISPLAY_LIMIT );
		$issue_type_filter = sanitize_key( $args['issue_type_filter'] ?? '' );
		$issues_only       = ! empty( $args['issues_only'] );

		$totals = array(
			'scanned'          => 0,
			'issues'           => 0,
			'skipped_oos'      => 0,
			'errors'           => 0,
			'warnings'         => 0,
			'notices'          => 0,
			'missing_image'    => 0,
			'missing_price'    => 0,
			'missing_title'    => 0,
			'missing_brand'    => 0,
			'missing_gtin_mpn' => 0,
			'score_sum'        => 0,
		);

		$all_rows  = array();
		$truncated = false;
		$seen      = 0;
		$page      = 1;
		$per       = 200;

		do {
			$query = new WP_Query( array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $per,
				'paged'                  => $page,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			) );

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $product_id ) {
				$seen++;
				if ( $seen > self::SCAN_LIMIT ) {
					$truncated = true;
					break 2;
				}

				$product = wc_get_product( $product_id );
				if ( ! $product || ! is_object( $product ) ) {
					continue;
				}

				if ( ! $include_oos && ! $product->is_in_stock() ) {
					$totals['skipped_oos']++;
					continue;
				}

				$totals['scanned']++;

				$hash   = CodeSolz_MFB_Health_Score::product_hash( $product );
				$cached = CodeSolz_MFB_Health_Score::get_stored( $product_id, $hash );

				if ( $cached ) {
					$score  = $cached['score'];
					$issues = $cached['issues'];
				} else {
					$issues = CodeSolz_MFB_Policy_Engine::run_all( $product, $settings );
					$score  = CodeSolz_MFB_Health_Score::calculate( $product, $issues, $settings );
					CodeSolz_MFB_Health_Score::store( $product_id, $score, $issues, $hash );
				}

				do_action( 'cs_mfb_after_product_scored', $product_id, $score, $issues );

				// Accumulate totals.
				$totals['score_sum'] += $score;
				$has_issues = ! empty( $issues );

				if ( $has_issues ) {
					$totals['issues']++;
				}

				foreach ( $issues as $issue ) {
					$totals[ $issue['severity'] . 's' ]++;

					if ( 'RULE-I01' === $issue['rule_id'] || 'RULE-I02' === $issue['rule_id'] ) {
						$totals['missing_image']++;
					}
					if ( 'RULE-P01' === $issue['rule_id'] ) {
						$totals['missing_price']++;
					}
					if ( 'RULE-T01' === $issue['rule_id'] ) {
						$totals['missing_title']++;
					}
					if ( 'RULE-ID04' === $issue['rule_id'] ) {
						$totals['missing_brand']++;
					}
					if ( 'RULE-ID03' === $issue['rule_id'] ) {
						$totals['missing_gtin_mpn']++;
					}
				}

				// Apply issue_type_filter.
				if ( $issue_type_filter && ! self::row_matches_filter( $issues, $issue_type_filter ) ) {
					continue;
				}

				if ( $issues_only && empty( $issues ) ) {
					continue;
				}

				$row = array(
					'id'     => $product_id,
					'title'  => $product->get_name(),
					'sku'    => $product->get_sku(),
					'score'  => $score,
					'tier'   => CodeSolz_MFB_Health_Score::get_tier( $score ),
					'issues' => $issues,
					'edit'   => get_edit_post_link( $product_id, 'raw' ),
					'link'   => $product->get_permalink(),
				);

				$row = apply_filters( 'cs_mfb_health_table_row', $row, $product_id, $score, $issues );

				$all_rows[] = $row;
			}

			$page++;
		} while ( count( $query->posts ) === $per );

		// Pagination.
		$total_rows  = count( $all_rows );
		$offset      = ( $current_page - 1 ) * $per_page;
		$paged_rows  = array_slice( $all_rows, $offset, $per_page );

		$avg_score = $totals['scanned'] > 0 ? (int) round( $totals['score_sum'] / $totals['scanned'] ) : 0;

		return array(
			'totals'     => $totals,
			'avg_score'  => $avg_score,
			'rows'       => $paged_rows,
			'total_rows' => $total_rows,
			'page'       => $current_page,
			'per_page'   => $per_page,
			'truncated'  => $truncated,
		);
	}

	/**
	 * Check if a product's issue list matches a sidebar filter.
	 *
	 * @param array  $issues      Product issues.
	 * @param string $filter_key  Filter slug: title|image|price|identifier|description.
	 * @return bool
	 */
	protected static function row_matches_filter( array $issues, $filter_key ) {
		$prefix_map = array(
			'title'      => 'RULE-T',
			'image'      => 'RULE-I',
			'price'      => 'RULE-P',
			'identifier' => 'RULE-ID',
			'description' => 'RULE-D',
		);

		if ( ! isset( $prefix_map[ $filter_key ] ) ) {
			return true;
		}

		$prefix = $prefix_map[ $filter_key ];
		foreach ( $issues as $issue ) {
			if ( 0 === strpos( $issue['rule_id'], $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Stream an enhanced CSV (25-rule columns) to the browser and exit.
	 */
	public static function stream_csv() {
		$settings    = CodeSolz_MFB_Settings::all();
		$include_oos = ! empty( $settings['include_oos'] );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cs-mfb-feed-issues-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$fh = fopen( 'php://output', 'w' );
		if ( ! $fh ) {
			return;
		}

		$rule_ids = array(
			'RULE-T01','RULE-T02','RULE-T03','RULE-T04','RULE-T05','RULE-T06','RULE-T07','RULE-T08',
			'RULE-I01','RULE-I02','RULE-I03','RULE-I04','RULE-I05',
			'RULE-P01','RULE-P02','RULE-P03','RULE-P04',
			'RULE-ID01','RULE-ID02','RULE-ID03','RULE-ID04',
			'RULE-D01','RULE-D02','RULE-D03','RULE-D04',
		);

		fputcsv( $fh, array_merge(
			array( 'product_id', 'sku', 'title', 'permalink', 'health_score', 'score_tier' ),
			$rule_ids,
			array( 'issues_summary' )
		) );

		$page = 1;
		$per  = 200;
		$seen = 0;

		do {
			$query = new WP_Query( array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'posts_per_page'         => $per,
				'paged'                  => $page,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			) );

			if ( empty( $query->posts ) ) {
				break;
			}

			foreach ( $query->posts as $product_id ) {
				$seen++;
				if ( $seen > self::SCAN_LIMIT ) {
					break 2;
				}

				$product = wc_get_product( $product_id );
				if ( ! $product || ! is_object( $product ) ) {
					continue;
				}
				if ( ! $include_oos && ! $product->is_in_stock() ) {
					continue;
				}

				$hash   = CodeSolz_MFB_Health_Score::product_hash( $product );
				$cached = CodeSolz_MFB_Health_Score::get_stored( $product_id, $hash );

				if ( $cached ) {
					$score  = $cached['score'];
					$issues = $cached['issues'];
				} else {
					$issues = CodeSolz_MFB_Policy_Engine::run_all( $product, $settings );
					$score  = CodeSolz_MFB_Health_Score::calculate( $product, $issues, $settings );
					CodeSolz_MFB_Health_Score::store( $product_id, $score, $issues, $hash );
				}

				$issue_map = array();
				foreach ( $issues as $issue ) {
					$issue_map[ $issue['rule_id'] ] = $issue['severity'];
				}

				$rule_cols    = array();
				$issue_labels = array();
				foreach ( $rule_ids as $rid ) {
					$rule_cols[] = isset( $issue_map[ $rid ] ) ? $issue_map[ $rid ] : '';
					if ( isset( $issue_map[ $rid ] ) ) {
						$issue_labels[] = $rid . ':' . $issue_map[ $rid ];
					}
				}

				fputcsv( $fh, array_merge(
					array(
						$product->get_id(),
						$product->get_sku(),
						$product->get_name(),
						get_permalink( $product->get_id() ),
						$score,
						CodeSolz_MFB_Health_Score::get_tier( $score ),
					),
					$rule_cols,
					array( implode( '|', $issue_labels ) )
				) );
			}
			$page++;
		} while ( count( $query->posts ) === $per );

		fclose( $fh );
	}
}
