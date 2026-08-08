<?php
/**
 * Main plugin class: bootstraps subsystems and handles activation/deactivation.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Plugin {

	/**
	 * Whether WooCommerce is active and minimally compatible.
	 *
	 * @var bool
	 */
	protected static $wc_ready = false;

	/**
	 * Activation hook: ensure defaults and feed directory exist, schedule cron, create DB tables.
	 */
	public static function activate() {
		CodeSolz_MFB_Settings::ensure_defaults();
		CodeSolz_MFB_Feed_Generator::ensure_feed_dir();
		CodeSolz_MFB_Cron::reschedule();
		self::create_tables();
		update_option( 'cs_mfb_db_version', CS_MFB_VERSION );
	}

	/**
	 * Create or upgrade plugin DB tables.
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table = $wpdb->prefix . CS_MFB_AUDIT_TABLE;

		$sql = "CREATE TABLE {$table} (
			id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id   BIGINT UNSIGNED NOT NULL,
			score        TINYINT UNSIGNED NOT NULL DEFAULT 0,
			issues_json  LONGTEXT NOT NULL,
			scanned_at   DATETIME NOT NULL,
			product_hash VARCHAR(64) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY   product_id (product_id),
			KEY          score (score),
			KEY          scanned_at (scanned_at)
		) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Deactivation hook: clear scheduled feed refresh.
	 */
	public static function deactivate() {
		CodeSolz_MFB_Cron::clear();
	}

	/**
	 * Boot the plugin once WordPress has loaded the active plugins.
	 */
	public static function boot() {
		load_plugin_textdomain( 'merchant-feed-booster-lite-for-woocommerce', false, dirname( CS_MFB_PLUGIN_BASENAME ) . '/languages' );

		self::$wc_ready = self::woocommerce_active();

		if ( ! self::$wc_ready ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_wc_missing_notice' ) );
			// Still register the admin menu so users see the plugin, but feed actions are disabled.
		}

		CodeSolz_MFB_Admin::register();
		CodeSolz_MFB_Product_List::register();
		CodeSolz_MFB_Settings::register();
		add_filter( 'cron_schedules', array( 'CodeSolz_MFB_Settings', 'add_weekly_cron_schedule' ) );
		CodeSolz_MFB_Product_Fields::register();
		CodeSolz_MFB_Cron::register();
		CodeSolz_MFB_Feed_Generator::register();
		CodeSolz_MFB_REST_API::register();
		CodeSolz_MFB_Image_Checker::register();
		CodeSolz_MFB_Site_Health::register();

		// Run DB upgrade if needed after a plugin update.
		if ( get_option( 'cs_mfb_db_version' ) !== CS_MFB_VERSION ) {
			self::create_tables();
			update_option( 'cs_mfb_db_version', CS_MFB_VERSION );
		}

		do_action( 'cs_mfb_ready' );

		add_filter( 'plugin_action_links_' . CS_MFB_PLUGIN_BASENAME, array( __CLASS__, 'plugin_action_links' ) );
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function woocommerce_active() {
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}

		$active_plugins = (array) apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
		if ( is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );
			$active_plugins = array_merge( $active_plugins, array_keys( $network ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins, true );
	}

	/**
	 * Convenience accessor for other classes.
	 *
	 * @return bool
	 */
	public static function is_wc_ready() {
		return (bool) self::$wc_ready;
	}

	/**
	 * Admin notice when WooCommerce is missing/inactive.
	 */
	public static function render_wc_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		// Limit to our own plugin pages to avoid cluttering all admin screens.
		$screen = get_current_screen();
		if ( $screen && false === strpos( $screen->id, 'cs-mfb' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo wp_kses_post(
			__( '<strong>CodeSolz Merchant Feed Booster Lite</strong> requires WooCommerce to be installed and active.', 'merchant-feed-booster-lite-for-woocommerce' )
		);
		echo '</p></div>';
	}

	/**
	 * Add a Settings link to the plugin row.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=cs-mfb-settings' );
		$settings = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}
}
