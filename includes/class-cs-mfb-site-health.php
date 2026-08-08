<?php
/**
 * WordPress Site Health integration: surfaces feed/health status as a
 * native Site Health test so problems show up alongside core WP checks.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Site_Health {

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_filter( 'site_status_tests', array( __CLASS__, 'add_test' ) );
	}

	/**
	 * Add a direct (synchronous) Site Health test.
	 *
	 * @param array $tests Existing tests.
	 * @return array
	 */
	public static function add_test( $tests ) {
		$tests['direct']['cs_mfb_feed_health'] = array(
			'label' => __( 'Google Merchant feed health', 'merchant-feed-booster-lite-for-woocommerce' ),
			'test'  => array( __CLASS__, 'run_test' ),
		);
		return $tests;
	}

	/**
	 * Run the test and return a Site Health result array.
	 *
	 * @return array
	 */
	public static function run_test() {
		$base = array(
			'test'  => 'cs_mfb_feed_health',
			'badge' => array(
				'label' => __( 'Merchant Feed Booster', 'merchant-feed-booster-lite-for-woocommerce' ),
				'color' => 'blue',
			),
			'actions' => sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'admin.php?page=' . CodeSolz_MFB_Admin::MENU_SLUG ) ),
				esc_html__( 'View Feed Booster dashboard', 'merchant-feed-booster-lite-for-woocommerce' )
			),
		);

		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			return array_merge( $base, array(
				'label'       => __( 'Merchant Feed Booster is inactive (WooCommerce required)', 'merchant-feed-booster-lite-for-woocommerce' ),
				'status'      => 'recommended',
				'description' => '<p>' . esc_html__( 'WooCommerce is not active, so Merchant Feed Booster is not generating a Google Shopping feed.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>',
			) );
		}

		$settings = CodeSolz_MFB_Settings::all();
		if ( empty( $settings['enabled'] ) ) {
			return array_merge( $base, array(
				'label'       => __( 'Google Merchant feed generation is disabled', 'merchant-feed-booster-lite-for-woocommerce' ),
				'status'      => 'recommended',
				'description' => '<p>' . esc_html__( 'Turn on the feed in Feed Booster → Settings so Google can fetch your products.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>',
			) );
		}

		$file = CodeSolz_MFB_Feed_Generator::feed_file_path();
		if ( ! $file || ! file_exists( $file ) ) {
			return array_merge( $base, array(
				'label'       => __( 'No Google Merchant feed has been generated yet', 'merchant-feed-booster-lite-for-woocommerce' ),
				'status'      => 'critical',
				'description' => '<p>' . esc_html__( 'Generate your feed from the Feed Booster dashboard so Google Merchant Center has something to fetch.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>',
			) );
		}

		$url = CodeSolz_MFB_Feed_Generator::public_feed_url();
		if ( ! CodeSolz_MFB_Feed_Generator::is_publicly_accessible( $url ) ) {
			return array_merge( $base, array(
				'label'       => __( 'Your Google Merchant feed URL is not publicly reachable', 'merchant-feed-booster-lite-for-woocommerce' ),
				'status'      => 'critical',
				'description' => '<p>' . esc_html__( 'Google Merchant Center cannot fetch a feed URL your own server cannot reach. Check uploads directory permissions or any firewall/CDN rule blocking it.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>',
			) );
		}

		$scan = CodeSolz_MFB_Health::scan();
		if ( 0 === $scan['totals']['scanned'] ) {
			return array_merge( $base, array(
				'label'       => __( 'No products have been scanned for feed health yet', 'merchant-feed-booster-lite-for-woocommerce' ),
				'status'      => 'recommended',
				'description' => '<p>' . esc_html__( 'Run a scan from the Feed Health page to see which products need attention before Google does.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>',
			) );
		}

		$avg            = (int) $scan['avg_score'];
		$issue_products = (int) $scan['totals']['issues'];

		if ( $avg < 50 ) {
			$status = 'critical';
		} elseif ( $avg < 70 || $issue_products > 0 ) {
			$status = 'recommended';
		} else {
			$status = 'good';
		}

		$label = 'good' === $status
			? __( 'Your Google Merchant feed is healthy', 'merchant-feed-booster-lite-for-woocommerce' )
			: sprintf(
				/* translators: %d = number of products with at least one feed issue */
				_n( '%d product in your Google Merchant feed needs attention', '%d products in your Google Merchant feed need attention', max( 1, $issue_products ), 'merchant-feed-booster-lite-for-woocommerce' ),
				$issue_products
			);

		return array_merge( $base, array(
			'label'       => $label,
			'status'      => $status,
			'description' => sprintf(
				'<p>' . esc_html__( 'Store-wide feed health score: %1$d/100, based on %2$d scanned products.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>',
				$avg,
				(int) $scan['totals']['scanned']
			),
		) );
	}
}
