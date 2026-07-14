<?php
/**
 * Plugin Name: Merchant Feed Booster Lite for WooCommerce
 * Plugin URI:  http://codesolz.net/our-products/wordpress-plugin/merchant-feed-booster-lite-for-woocommerce
 * Description: Know exactly why your WooCommerce products are rejected or underperforming on Google Shopping then fix them. Audits every product against 25 policy rules, assigns a health score (0–100), and provides specific fix hints for every issue, plus a reliable Google Merchant XML feed with automatic refresh.
 * Version:     1.0.0
 * Author:      CodeSolz
 * Author URI:  http://codesolz.net
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: merchant-feed-booster-lite-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CS_MFB_VERSION', '2.0.0' );
define( 'CS_MFB_PLUGIN_FILE', __FILE__ );
define( 'CS_MFB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CS_MFB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CS_MFB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CS_MFB_FEED_DIRNAME', 'codesolz-feeds' );
define( 'CS_MFB_FEED_FILENAME', 'google-products.xml' );
define( 'CS_MFB_CRON_HOOK', 'cs_mfb_refresh_feed_cron' );

// v2.0 constants
define( 'CS_MFB_SCORE_META_KEY', '_cs_mfb_health_score' );
define( 'CS_MFB_SCORE_META_TIMESTAMP', '_cs_mfb_health_score_ts' );
define( 'CS_MFB_IMG_CACHE_META_KEY', '_cs_mfb_img_dims_cache' );
define( 'CS_MFB_SCAN_TRANSIENT', 'cs_mfb_scan_state' );
define( 'CS_MFB_REST_NAMESPACE', 'cs-mfb/v1' );
define( 'CS_MFB_AUDIT_TABLE', 'cs_mfb_audit_cache' );
define( 'CS_MFB_IMG_CACHE_TTL', 86400 );
define( 'CS_MFB_SCAN_BATCH_SIZE', 50 );
define( 'CS_MFB_SCAN_CACHE_TTL', 21600 );

require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-plugin.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-settings.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-product-fields.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-gtin-validator.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-image-checker.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-policy-engine.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-health-score.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-scan-runner.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-rest-api.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-feed-generator.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-feed-preview.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-health.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-cron.php';
require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-admin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once CS_MFB_PLUGIN_DIR . 'includes/class-cs-mfb-cli.php';
}

// Declare WooCommerce HPOS (High-Performance Order Storage) compatibility.
// This plugin only reads/writes products, never orders, so it is fully compatible.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

register_activation_hook( __FILE__, array( 'CodeSolz_MFB_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CodeSolz_MFB_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'CodeSolz_MFB_Plugin', 'boot' ) );
