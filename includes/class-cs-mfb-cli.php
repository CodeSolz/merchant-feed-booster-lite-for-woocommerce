<?php
/**
 * WP-CLI commands for Merchant Feed Booster.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) ) {
	exit;
}

/**
 * Manage the CodeSolz Merchant Feed Booster from the command line.
 */
class CodeSolz_MFB_CLI {

	/**
	 * Regenerate the Google Merchant feed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cs-mfb generate
	 *
	 * @subcommand generate
	 */
	public function generate() {
		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			WP_CLI::error( 'WooCommerce is not active.' );
		}

		WP_CLI::log( 'Generating feed…' );
		$result = CodeSolz_MFB_Feed_Generator::generate();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Feed generated: %d products written, %d skipped (OOS).', $result['written'], $result['skipped_oos'] ) );
		WP_CLI::log( 'File: ' . $result['file'] );
	}

	/**
	 * Run a full health scan and display results.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : Maximum products to scan. Default: 500.
	 *
	 * [--format=<format>]
	 * : Output format (table|csv|json). Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cs-mfb health
	 *     wp cs-mfb health --limit=100 --format=csv
	 *
	 * @subcommand health
	 */
	public function health( $args, $assoc_args ) {
		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			WP_CLI::error( 'WooCommerce is not active.' );
		}

		$limit  = (int) ( $assoc_args['limit'] ?? 500 );
		$format = $assoc_args['format'] ?? 'table';

		WP_CLI::log( "Scanning up to {$limit} products…" );

		$settings    = CodeSolz_MFB_Settings::all();
		$product_ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		) );

		$rows = array();
		$bar  = WP_CLI\Utils\make_progress_bar( 'Scanning', count( $product_ids ) );

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$bar->tick();
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

			$errors   = count( array_filter( $issues, fn( $i ) => $i['severity'] === 'error' ) );
			$warnings = count( array_filter( $issues, fn( $i ) => $i['severity'] === 'warning' ) );

			$rows[] = array(
				'ID'       => $product_id,
				'SKU'      => $product->get_sku() ?: '—',
				'Title'    => mb_strimwidth( $product->get_name(), 0, 40, '…' ),
				'Score'    => $score,
				'Tier'     => CodeSolz_MFB_Health_Score::get_tier_label( CodeSolz_MFB_Health_Score::get_tier( $score ) ),
				'Errors'   => $errors,
				'Warnings' => $warnings,
			);

			$bar->tick();
		}

		$bar->finish();

		WP_CLI\Utils\format_items( $format, $rows, array( 'ID', 'SKU', 'Title', 'Score', 'Tier', 'Errors', 'Warnings' ) );
	}

	/**
	 * Show health score breakdown for a single product.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Product ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cs-mfb score 42
	 *
	 * @subcommand score
	 */
	public function score( $args ) {
		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			WP_CLI::error( 'WooCommerce is not active.' );
		}

		$product_id = (int) ( $args[0] ?? 0 );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			WP_CLI::error( "Product ID {$product_id} not found." );
		}

		$settings = CodeSolz_MFB_Settings::all();
		$hash     = CodeSolz_MFB_Health_Score::product_hash( $product );
		$cached   = CodeSolz_MFB_Health_Score::get_stored( $product_id, $hash );

		if ( $cached ) {
			$score  = $cached['score'];
			$issues = $cached['issues'];
		} else {
			$issues = CodeSolz_MFB_Policy_Engine::run_all( $product, $settings );
			$score  = CodeSolz_MFB_Health_Score::calculate( $product, $issues, $settings );
			CodeSolz_MFB_Health_Score::store( $product_id, $score, $issues, $hash );
		}

		$tier = CodeSolz_MFB_Health_Score::get_tier( $score );
		WP_CLI::log( sprintf( 'Product: %s (ID: %d)', $product->get_name(), $product_id ) );
		WP_CLI::log( sprintf( 'Score:   %d / 100 — %s', $score, CodeSolz_MFB_Health_Score::get_tier_label( $tier ) ) );
		WP_CLI::log( '' );

		if ( empty( $issues ) ) {
			WP_CLI::success( 'No issues found.' );
			return;
		}

		$rows = array();
		foreach ( $issues as $issue ) {
			$rows[] = array(
				'Rule'     => $issue['rule_id'],
				'Severity' => strtoupper( $issue['severity'] ),
				'Message'  => mb_strimwidth( $issue['message'], 0, 60, '…' ),
				'Fix'      => mb_strimwidth( $issue['fix_hint'], 0, 60, '…' ),
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'Rule', 'Severity', 'Message', 'Fix' ) );
	}

	/**
	 * Clear the audit cache table and re-scan transient.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cs-mfb clear-cache
	 *
	 * @subcommand clear-cache
	 */
	public function clear_cache() {
		CodeSolz_MFB_Scan_Runner::clear();
		WP_CLI::success( 'Audit cache cleared.' );
	}
}

WP_CLI::add_command( 'cs-mfb', 'CodeSolz_MFB_CLI' );
