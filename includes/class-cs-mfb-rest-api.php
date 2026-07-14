<?php
/**
 * REST API endpoints for feed management and health scanning.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_REST_API {

	/**
	 * Register REST routes.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all route definitions.
	 */
	public static function register_routes() {
		$ns = CS_MFB_REST_NAMESPACE;

		register_rest_route( $ns, '/scan/start', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'handle_scan_start' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
		) );

		register_rest_route( $ns, '/scan/progress', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'handle_scan_progress' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
		) );

		register_rest_route( $ns, '/product/(?P<id>\d+)/score', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'handle_product_score' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
			'args'                => array(
				'id' => array(
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'absint',
				),
			),
		) );

		register_rest_route( $ns, '/feed/regenerate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'handle_feed_regenerate' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
		) );

		register_rest_route( $ns, '/cache/clear', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'handle_cache_clear' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
		) );

		register_rest_route( $ns, '/settings/reset', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'handle_settings_reset' ),
			'permission_callback' => array( __CLASS__, 'check_permission' ),
		) );
	}

	/**
	 * Shared permission callback: manage_woocommerce capability.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_permission() {
		$cap = apply_filters( 'cs_mfb_required_capability', 'manage_woocommerce' );
		if ( ! current_user_can( $cap ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to perform this action.', 'merchant-feed-booster-lite-for-woocommerce' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Start a new background scan.
	 *
	 * GET /cs-mfb/v1/scan/start
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_scan_start() {
		$result = CodeSolz_MFB_Scan_Runner::start();
		return rest_ensure_response( $result );
	}

	/**
	 * Return progress of the running scan and advance it by one batch.
	 *
	 * GET /cs-mfb/v1/scan/progress
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_scan_progress() {
		$result = CodeSolz_MFB_Scan_Runner::next_batch();
		return rest_ensure_response( $result );
	}

	/**
	 * Get the health score for a single product.
	 *
	 * GET /cs-mfb/v1/product/{id}/score
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_product_score( WP_REST_Request $request ) {
		$product_id = (int) $request->get_param( 'id' );
		$product    = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'merchant-feed-booster-lite-for-woocommerce' ), array( 'status' => 404 ) );
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

		return rest_ensure_response( array(
			'product_id' => $product_id,
			'score'      => $score,
			'tier'       => CodeSolz_MFB_Health_Score::get_tier( $score ),
			'tier_label' => CodeSolz_MFB_Health_Score::get_tier_label( CodeSolz_MFB_Health_Score::get_tier( $score ) ),
			'issues'     => $issues,
		) );
	}

	/**
	 * Clear the audit cache transient and DB rows.
	 *
	 * POST /cs-mfb/v1/cache/clear
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_cache_clear() {
		CodeSolz_MFB_Scan_Runner::clear();
		CodeSolz_MFB_Admin::log_activity( 'cache', __( 'Score cache cleared. Products will be re-scored on next scan.', 'merchant-feed-booster-lite-for-woocommerce' ) );
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Reset settings to defaults.
	 *
	 * POST /cs-mfb/v1/settings/reset
	 *
	 * @return WP_REST_Response
	 */
	public static function handle_settings_reset() {
		$defaults = CodeSolz_MFB_Settings::defaults();
		update_option( CodeSolz_MFB_Settings::OPTION_KEY, $defaults );
		return rest_ensure_response( array( 'success' => true, 'settings' => $defaults ) );
	}

	/**
	 * Trigger synchronous feed regeneration.
	 *
	 * POST /cs-mfb/v1/feed/regenerate
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_feed_regenerate() {
		$start  = microtime( true );
		$result = CodeSolz_MFB_Feed_Generator::generate();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		CodeSolz_MFB_Admin::log_activity(
			'feed',
			sprintf(
				/* translators: %d: number of products written to the feed */
				__( '%d products written to feed.', 'merchant-feed-booster-lite-for-woocommerce' ),
				(int) $result['written']
			)
		);

		return rest_ensure_response( array(
			'written'     => $result['written'],
			'skipped_oos' => $result['skipped_oos'],
			'time_ms'     => (int) ( ( microtime( true ) - $start ) * 1000 ),
		) );
	}
}
