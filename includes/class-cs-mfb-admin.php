<?php
/**
 * Admin menu, pages, asset loading, and admin-post action handlers.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Admin {

	const MENU_SLUG          = 'cs-mfb-dashboard';
	const SETTINGS_SLUG      = 'cs-mfb-settings';
	const HEALTH_SLUG        = 'cs-mfb-health';
	const PREVIEW_SLUG       = 'cs-mfb-preview';
	const REGEN_ACTION       = 'cs_mfb_regenerate_feed';
	const CSV_EXPORT_ACTION  = 'cs_mfb_export_issues_csv';
	const CLEAR_CACHE_ACTION = 'cs_mfb_clear_audit_cache';
	const CAPABILITY         = 'manage_woocommerce';

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::REGEN_ACTION, array( __CLASS__, 'handle_regenerate' ) );
		add_action( 'admin_post_' . self::CSV_EXPORT_ACTION, array( __CLASS__, 'handle_csv_export' ) );
		add_action( 'admin_post_' . self::CLEAR_CACHE_ACTION, array( __CLASS__, 'handle_clear_cache' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_product_score_metabox' ) );
	}

	/**
	 * Register the top-level CodeSolz menu and submenu pages.
	 */
	public static function menu() {
		$cap = self::cap();

		$hooks   = array();
		$hooks[] = add_menu_page(
			__( 'Feed Booster', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Feed Booster', 'merchant-feed-booster-lite-for-woocommerce' ),
			$cap,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-rss',
			58
		);

		$hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Dashboard', 'merchant-feed-booster-lite-for-woocommerce' ),
			$cap,
			self::MENU_SLUG,
			array( __CLASS__, 'render_dashboard' )
		);

		$hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Settings', 'merchant-feed-booster-lite-for-woocommerce' ),
			$cap,
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings' )
		);

		$hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Feed Health', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Feed Health', 'merchant-feed-booster-lite-for-woocommerce' ),
			$cap,
			self::HEALTH_SLUG,
			array( __CLASS__, 'render_health' )
		);

		$hooks[] = add_submenu_page(
			self::MENU_SLUG,
			__( 'Feed Preview', 'merchant-feed-booster-lite-for-woocommerce' ),
			__( 'Feed Preview', 'merchant-feed-booster-lite-for-woocommerce' ),
			$cap,
			self::PREVIEW_SLUG,
			array( __CLASS__, 'render_preview' )
		);

		foreach ( $hooks as $hook ) {
			if ( $hook ) {
				add_action( 'load-' . $hook, array( __CLASS__, 'suppress_foreign_admin_notices' ) );
			}
		}

		/**
		 * Fires from inside the admin_menu callback (i.e. once WordPress is
		 * actually building the admin menu), NOT at plugins_loaded time —
		 * so extensions can register their own add_action( 'admin_menu', ... )
		 * here and still run before WordPress finishes processing admin_menu,
		 * regardless of plugin load order or plugins_loaded priority.
		 */
		do_action( 'cs_mfb_register_admin_pages' );
	}

	/**
	 * On our own admin pages, strip admin notices registered by other
	 * plugins/themes so only this plugin's own hero/notice UI is shown.
	 */
	public static function suppress_foreign_admin_notices() {
		add_action( 'admin_notices', array( __CLASS__, 'strip_foreign_notices' ), 0 );
		add_action( 'all_admin_notices', array( __CLASS__, 'strip_foreign_notices' ), 0 );
	}

	/**
	 * Remove any admin_notices/all_admin_notices callbacks that don't
	 * belong to this plugin, right before WordPress prints them.
	 */
	public static function strip_foreign_notices() {
		global $wp_filter;

		foreach ( array( 'admin_notices', 'all_admin_notices' ) as $tag ) {
			if ( empty( $wp_filter[ $tag ] ) || ! isset( $wp_filter[ $tag ]->callbacks ) ) {
				continue;
			}

			foreach ( $wp_filter[ $tag ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $id => $cb ) {
					if ( ! self::is_own_notice_callback( $cb['function'] ) ) {
						unset( $wp_filter[ $tag ]->callbacks[ $priority ][ $id ] );
					}
				}
			}
		}
	}

	/**
	 * Whether a hooked callback belongs to this plugin.
	 *
	 * @param callable $function Callback registered on admin_notices.
	 * @return bool
	 */
	protected static function is_own_notice_callback( $function ) {
		if ( is_array( $function ) ) {
			$target = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
			return 0 === strpos( $target, 'CodeSolz_MFB' );
		}

		if ( is_string( $function ) ) {
			return 0 === strpos( $function, 'cs_mfb_' );
		}

		return false;
	}

	/**
	 * Required capability for admin actions.
	 *
	 * @return string
	 */
	public static function cap() {
		return apply_filters( 'cs_mfb_required_capability', self::CAPABILITY );
	}

	/**
	 * Enqueue admin CSS only on our pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'cs-mfb' ) && 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		/* Bundled Swal — no external CDN */
		wp_enqueue_style(
			'cs-mfb-swal',
			CS_MFB_PLUGIN_URL . 'assets/cs-mfb-swal.css',
			array(),
			CS_MFB_VERSION
		);
		wp_enqueue_script(
			'cs-mfb-swal',
			CS_MFB_PLUGIN_URL . 'assets/cs-mfb-swal.js',
			array(),
			CS_MFB_VERSION,
			true
		);

		/* Premium admin UI */
		wp_enqueue_style(
			'cs-mfb-admin',
			CS_MFB_PLUGIN_URL . 'assets/admin.css',
			array( 'cs-mfb-swal' ),
			CS_MFB_VERSION
		);
		wp_enqueue_script(
			'cs-mfb-admin',
			CS_MFB_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery', 'cs-mfb-swal' ),
			CS_MFB_VERSION,
			true
		);
		wp_localize_script( 'cs-mfb-admin', 'csMfb', array(
			'restUrl' => esc_url_raw( rest_url( CS_MFB_REST_NAMESPACE . '/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'scanning' => __( 'Scanning…',     'merchant-feed-booster-lite-for-woocommerce' ),
				'done'     => __( 'Scan complete.', 'merchant-feed-booster-lite-for-woocommerce' ),
			),
		) );
	}

	/**
	 * Build a URL to the regenerate feed admin-post handler with nonce.
	 *
	 * @return string
	 */
	public static function regenerate_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . rawurlencode( self::REGEN_ACTION ) ),
			self::REGEN_ACTION,
			'_cs_mfb_nonce'
		);
	}

	/**
	 * Build a URL to the CSV export admin-post handler with nonce.
	 *
	 * @return string
	 */
	public static function csv_export_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . rawurlencode( self::CSV_EXPORT_ACTION ) ),
			self::CSV_EXPORT_ACTION,
			'_cs_mfb_nonce'
		);
	}

	/**
	 * Handle the Regenerate Feed admin-post action.
	 */
	public static function handle_regenerate() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to regenerate the feed.', 'merchant-feed-booster-lite-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::REGEN_ACTION, '_cs_mfb_nonce' );

		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			$redirect = add_query_arg(
				array( 'cs_mfb_msg' => 'wc_missing' ),
				admin_url( 'admin.php?page=' . self::MENU_SLUG )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$result = CodeSolz_MFB_Feed_Generator::generate();
		$msg    = is_wp_error( $result ) ? 'regen_failed' : 'regen_ok';

		$redirect = add_query_arg(
			array(
				'cs_mfb_msg'   => $msg,
				'cs_mfb_count' => is_array( $result ) && isset( $result['written'] ) ? (int) $result['written'] : 0,
			),
			admin_url( 'admin.php?page=' . self::MENU_SLUG )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle the CSV export of feed issues.
	 */
	public static function handle_csv_export() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission to export feed issues.', 'merchant-feed-booster-lite-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::CSV_EXPORT_ACTION, '_cs_mfb_nonce' );

		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			wp_die( esc_html__( 'WooCommerce is required.', 'merchant-feed-booster-lite-for-woocommerce' ), '', array( 'response' => 400 ) );
		}

		CodeSolz_MFB_Health::stream_csv();
		exit;
	}

	/**
	 * Handle clearing the audit cache.
	 */
	public static function handle_clear_cache() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'You do not have permission.', 'merchant-feed-booster-lite-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::CLEAR_CACHE_ACTION, '_cs_mfb_nonce' );

		CodeSolz_MFB_Scan_Runner::clear();

		wp_safe_redirect( add_query_arg(
			array( 'cs_mfb_msg' => 'cache_cleared' ),
			admin_url( 'admin.php?page=' . self::HEALTH_SLUG )
		) );
		exit;
	}

	/**
	 * Build a URL to the clear-cache admin-post handler.
	 *
	 * @return string
	 */
	public static function clear_cache_url() {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . rawurlencode( self::CLEAR_CACHE_ACTION ) ),
			self::CLEAR_CACHE_ACTION,
			'_cs_mfb_nonce'
		);
	}

	/**
	 * Register the per-product health score meta box.
	 */
	public static function register_product_score_metabox() {
		add_meta_box(
			'cs-mfb-product-score',
			__( 'Feed Health Score', 'merchant-feed-booster-lite-for-woocommerce' ),
			array( __CLASS__, 'render_product_score_metabox' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Render the per-product health score meta box.
	 *
	 * @param WP_Post $post The product post.
	 */
	public static function render_product_score_metabox( $post ) {
		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			echo '<p>' . esc_html__( 'WooCommerce required.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
			return;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}

		$score = (int) get_post_meta( $post->ID, CS_MFB_SCORE_META_KEY, true );
		$tier  = CodeSolz_MFB_Health_Score::get_tier( $score );
		$color = CodeSolz_MFB_Health_Score::get_tier_color( $tier );
		$label = CodeSolz_MFB_Health_Score::get_tier_label( $tier );

		echo '<div class="cs-mfb-product-score-box">';
		printf(
			'<div class="cs-mfb-score-badge" style="background-color:%s;color:#fff;font-size:22px;font-weight:700;padding:10px;border-radius:6px;text-align:center;margin-bottom:8px">%s</div>',
			esc_attr( $color ),
			esc_html( $score . '/100' )
		);
		echo '<p style="text-align:center;margin:4px 0 10px;font-weight:600">' . esc_html( $label ) . '</p>';

		$hash   = CodeSolz_MFB_Health_Score::product_hash( $product );
		$cached = CodeSolz_MFB_Health_Score::get_stored( $post->ID, $hash );
		if ( $cached && ! empty( $cached['issues'] ) ) {
			echo '<ul style="margin:0;padding-left:14px">';
			$shown = 0;
			foreach ( $cached['issues'] as $issue ) {
				if ( $shown >= 5 ) {
					break;
				}
				$sev_colors = array( 'error' => '#ef4444', 'warning' => '#f59e0b', 'notice' => '#3b82f6' );
				$sev_color  = isset( $sev_colors[ $issue['severity'] ] ) ? $sev_colors[ $issue['severity'] ] : '#666';
				printf(
					'<li style="font-size:12px;margin-bottom:4px"><span style="color:%s;font-weight:600">%s</span> %s</li>',
					esc_attr( $sev_color ),
					esc_html( $issue['rule_id'] ),
					esc_html( wp_trim_words( $issue['message'], 8 ) )
				);
				$shown++;
			}
			if ( count( $cached['issues'] ) > 5 ) {
				echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . self::HEALTH_SLUG ) ) . '">' . esc_html__( 'See all issues →', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a></li>';
			}
			echo '</ul>';
		} elseif ( $score > 0 ) {
			echo '<p style="color:#22c55e;text-align:center">' . esc_html__( '✓ No issues found.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		} else {
			echo '<p style="color:#999;font-size:12px">' . esc_html__( 'Score will appear after next feed scan.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Translate an internal message key into a user-facing notice.
	 *
	 * @param string $key Message key.
	 * @param int    $count Optional count param.
	 */
	protected static function render_admin_notice( $key, $count = 0 ) {
		$notices = array(
			'regen_ok'     => array( 'success', sprintf(
				/* translators: %d: number of products written to the feed */
				__( 'Feed regenerated. %d products written.', 'merchant-feed-booster-lite-for-woocommerce' ),
				$count
			) ),
			'regen_failed'  => array( 'error', __( 'Feed regeneration failed. Check the error log.', 'merchant-feed-booster-lite-for-woocommerce' ) ),
			'wc_missing'    => array( 'error', __( 'WooCommerce is required to generate the feed.', 'merchant-feed-booster-lite-for-woocommerce' ) ),
			'cache_cleared' => array( 'success', __( 'Audit cache cleared. The next scan will rescore all products.', 'merchant-feed-booster-lite-for-woocommerce' ) ),
		);
		if ( ! isset( $notices[ $key ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $notices[ $key ][0] ),
			esc_html( $notices[ $key ][1] )
		);
	}

	/**
	 * Render an SVG ring gauge.
	 *
	 * @param int    $score 0–100.
	 * @param string $color Hex colour.
	 * @param string $label Tier label.
	 * @param string $size  lg|md|sm.
	 */
	protected static function render_score_gauge( $score, $color, $label, $size = 'md' ) {
		$pct = min( 100, max( 0, (int) $score ) );
		printf(
			'<div class="cs-mfb-gauge cs-mfb-gauge--%s" data-cs-score="%d">
				<svg class="cs-mfb-gauge-svg" viewBox="0 0 36 36" aria-hidden="true">
					<path class="cs-mfb-gauge-track" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
					<path class="cs-mfb-gauge-fill" stroke-dasharray="0,100" style="stroke:%s"
						d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
				</svg>
				<div class="cs-mfb-gauge-inner">
					<strong class="cs-mfb-gauge-num" style="color:%s">%d</strong>
					<span class="cs-mfb-gauge-label">%s</span>
				</div>
			</div>',
			esc_attr( $size ),
			$pct,
			esc_attr( $color ),
			esc_attr( $color ),
			$pct,
			esc_html( $label )
		);
	}

	/**
	 * Log a dashboard activity entry (max 10 kept).
	 *
	 * @param string $type    Activity type key (feed, scan, cache, etc.).
	 * @param string $message Human-readable message.
	 */
	public static function log_activity( $type, $message ) {
		$log = get_option( 'cs_mfb_activity_log', array() );
		array_unshift( $log, array(
			'type'    => sanitize_key( $type ),
			'message' => sanitize_text_field( $message ),
			'time'    => time(),
		) );
		update_option( 'cs_mfb_activity_log', array_slice( $log, 0, 10 ), false );
	}

	/**
	 * Render the dashboard / main Merchant Feed page.
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}

		$msg   = isset( $_GET['cs_mfb_msg'] ) ? sanitize_key( wp_unslash( $_GET['cs_mfb_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = isset( $_GET['cs_mfb_count'] ) ? absint( wp_unslash( $_GET['cs_mfb_count'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$settings    = CodeSolz_MFB_Settings::all();
		$feed_url    = CodeSolz_MFB_Feed_Generator::public_feed_url();
		$feed_path   = CodeSolz_MFB_Feed_Generator::feed_file_path();
		$feed_exists = $feed_path && file_exists( $feed_path );
		$feed_mtime  = $feed_exists ? filemtime( $feed_path ) : 0;
		$feed_size   = $feed_exists ? size_format( filesize( $feed_path ) ) : '—';
		$next_cron   = wp_next_scheduled( CS_MFB_CRON_HOOK );
		$last_run    = get_option( 'cs_mfb_last_run', array() );
		$feed_active = ! empty( $settings['enabled'] ) && $feed_exists;

		// DB aggregates — score stats and tier distribution via SQL to avoid loading all rows.
		global $wpdb;
		$table = $wpdb->prefix . CS_MFB_AUDIT_TABLE;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$score_agg = $wpdb->get_row(
			"SELECT
				COUNT(*)                                                     AS total,
				COALESCE( SUM(score), 0 )                                    AS score_sum,
				SUM( CASE WHEN score >= 85 THEN 1 ELSE 0 END )               AS tier_excellent,
				SUM( CASE WHEN score >= 70 AND score < 85 THEN 1 ELSE 0 END ) AS tier_good,
				SUM( CASE WHEN score >= 50 AND score < 70 THEN 1 ELSE 0 END ) AS tier_needs_work,
				SUM( CASE WHEN score >= 30 AND score < 50 THEN 1 ELSE 0 END ) AS tier_poor,
				SUM( CASE WHEN score < 30 THEN 1 ELSE 0 END )                AS tier_critical
			FROM `{$table}`",
			ARRAY_A
		);

		$scanned_count = (int) ( $score_agg['total'] ?? 0 );
		$score_sum     = (int) ( $score_agg['score_sum'] ?? 0 );
		$tier_dist     = array(
			'excellent'  => (int) ( $score_agg['tier_excellent']  ?? 0 ),
			'good'       => (int) ( $score_agg['tier_good']       ?? 0 ),
			'needs-work' => (int) ( $score_agg['tier_needs_work'] ?? 0 ),
			'poor'       => (int) ( $score_agg['tier_poor']       ?? 0 ),
			'critical'   => (int) ( $score_agg['tier_critical']   ?? 0 ),
		);

		// Fetch only issues_json for rule/category analysis — capped to bound memory on large catalogs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$issue_rows = $wpdb->get_results(
			"SELECT issues_json FROM `{$table}` WHERE issues_json != '[]' LIMIT 2000",
			ARRAY_A
		);

		// Process issues from audit cache.
		$error_count   = 0;
		$warning_count = 0;
		$rule_counts   = array();
		$cat_affected  = array( 'title' => 0, 'image' => 0, 'price' => 0, 'identifier' => 0, 'description' => 0 );

		foreach ( $issue_rows as $db_row ) {
			$issues = json_decode( $db_row['issues_json'], true );
			if ( ! is_array( $issues ) ) {
				continue;
			}
			$cats_seen  = array();
			$rules_seen = array();
			foreach ( $issues as $issue ) {
				$sev = isset( $issue['severity'] ) ? $issue['severity'] : '';
				if ( 'error' === $sev ) {
					$error_count++;
				} elseif ( 'warning' === $sev ) {
					$warning_count++;
				}
				$rule = isset( $issue['rule_id'] ) ? $issue['rule_id'] : '';
				if ( $rule && ! isset( $rules_seen[ $rule ] ) ) {
					$rules_seen[ $rule ] = true;
					$rule_counts[ $rule ] = isset( $rule_counts[ $rule ] ) ? $rule_counts[ $rule ] + 1 : 1;
				}
				$cat = '';
				if ( 'RULE-ID' === substr( $rule, 0, 7 ) ) {
					$cat = 'identifier';
				} elseif ( 'RULE-T' === substr( $rule, 0, 6 ) ) {
					$cat = 'title';
				} elseif ( 'RULE-I' === substr( $rule, 0, 6 ) ) {
					$cat = 'image';
				} elseif ( 'RULE-P' === substr( $rule, 0, 6 ) ) {
					$cat = 'price';
				} elseif ( 'RULE-D' === substr( $rule, 0, 6 ) ) {
					$cat = 'description';
				}
				if ( $cat && ! isset( $cats_seen[ $cat ] ) ) {
					$cats_seen[ $cat ] = true;
					$cat_affected[ $cat ]++;
				}
			}
		}
		$avg_score = $scanned_count > 0 ? (int) round( $score_sum / $scanned_count ) : 0;
		$avg_tier  = CodeSolz_MFB_Health_Score::get_tier( $avg_score );
		$avg_color = CodeSolz_MFB_Health_Score::get_tier_color( $avg_tier );

		$tier_grad_ends = array( 'excellent' => '#34d399', 'good' => '#60a5fa', 'needs-work' => '#fbbf24', 'poor' => '#f87171', 'critical' => '#ef4444' );
		$grad_end = isset( $tier_grad_ends[ $avg_tier ] ) ? $tier_grad_ends[ $avg_tier ] : '#818cf8';

		// Quality percentages per category.
		$quality_pct = array();
		foreach ( $cat_affected as $cat => $affected ) {
			$quality_pct[ $cat ] = $scanned_count > 0 ? max( 0, (int) round( ( $scanned_count - $affected ) / $scanned_count * 100 ) ) : 100;
		}
		$quality_pct['category'] = ! empty( $settings['default_google_category'] ) ? 100 : 0;

		// Top 5 issues.
		arsort( $rule_counts );
		$top_issues = array_slice( $rule_counts, 0, 5, true );
		$tier_dist_colors = array(
			'excellent'  => '#10b981',
			'good'       => '#3b82f6',
			'needs-work' => '#f59e0b',
			'poor'       => '#f97316',
			'critical'   => '#ef4444',
		);
		$tier_dist_labels = array(
			'excellent'  => __( 'Excellent (85–100)', 'merchant-feed-booster-lite-for-woocommerce' ),
			'good'       => __( 'Good (70–84)',        'merchant-feed-booster-lite-for-woocommerce' ),
			'needs-work' => __( 'Needs Work (50–69)', 'merchant-feed-booster-lite-for-woocommerce' ),
			'poor'       => __( 'Poor (30–49)',        'merchant-feed-booster-lite-for-woocommerce' ),
			'critical'   => __( 'Critical (0–29)',     'merchant-feed-booster-lite-for-woocommerce' ),
		);
		$tier_dist_max = max( 1, max( $tier_dist ) );

		// 5 lowest-scored products for "Needs Attention" card.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$worst_products = $wpdb->get_results(
			"SELECT product_id, score FROM `{$table}` ORDER BY score ASC LIMIT 5",
			ARRAY_A
		);

		$rule_descriptions = array(
			'RULE-T01'  => __( 'Short product titles',            'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-T02'  => __( 'Product titles too long',         'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-T03'  => __( 'Promotional words in title',      'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-T04'  => __( 'ALL CAPS in titles',              'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-T05'  => __( 'HTML found in titles',            'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-T06'  => __( 'Price in title',                  'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-T07'  => __( 'Special chars in title',          'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-T08'  => __( 'Repeated words in title',         'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-I01'  => __( 'Missing product image',           'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-I02'  => __( 'Image too small (< 100px)',       'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-I03'  => __( 'Low resolution image',            'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-I04'  => __( 'Non-standard image format',       'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-I05'  => __( 'Tracking params in image URL',    'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-P01'  => __( 'Missing price',                   'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-P02'  => __( 'Sale without regular price',      'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-P03'  => __( 'Sale price exceeds regular price','merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-P04'  => __( 'Non-numeric price',               'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-ID01' => __( 'Wrong GTIN length',               'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-ID02' => __( 'Invalid GTIN check digit',        'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-ID03' => __( 'Missing product identifiers',     'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-ID04' => __( 'Missing brand value',             'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-D01'  => __( 'Missing product description',     'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-D02'  => __( 'Short product description',       'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-D03'  => __( 'HTML in description',             'merchant-feed-booster-lite-for-woocommerce' ),
			'RULE-D04'  => __( 'Promotional words in description','merchant-feed-booster-lite-for-woocommerce' ),
		);

		// Product counts.
		$total_products   = (int) wp_count_posts( 'product' )->publish;
		$products_in_feed = isset( $last_run['written'] ) ? (int) $last_run['written'] : $total_products;
		$products_oos     = isset( $last_run['skipped_oos'] ) ? (int) $last_run['skipped_oos'] : 0;
		$products_excl    = max( 0, $total_products - $products_in_feed - $products_oos );

		// Activity log.
		$activity_log = get_option( 'cs_mfb_activity_log', array() );

		// ── Feed URL accessibility check (real HTTP check, cached 15 min).
		$feed_accessible = false;
		if ( $feed_url && $feed_exists ) {
			$feed_accessible = CodeSolz_MFB_Feed_Generator::is_publicly_accessible( $feed_url );
		}

		// ── Last generated display string.
		$last_gen_display = $feed_mtime ? wp_date( 'M j, g:i a', $feed_mtime ) : '—';

		// ══════════════════════════════════════════════════════════════════
		// OUTPUT
		// ══════════════════════════════════════════════════════════════════
		echo '<div class="wrap cs-mfb-page cs-mfb-dashboard">';

		if ( $msg ) {
			self::render_admin_notice( $msg, $count );
		}
		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce is not active. Activate WooCommerce to use this plugin.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		}

		// ══════════════════════════════════════════════════════════════════
		// 1. HERO SECTION (preserved as-is)
		// ══════════════════════════════════════════════════════════════════
		echo '<div class="cs-mfb-hero">';
		echo '<div class="cs-mfb-hero-grid"></div>';
		echo '<div class="cs-mfb-hero-inner">';

		echo '<div class="cs-mfb-hero-content">';
		echo '<div class="cs-mfb-hero-eyebrow">';
		if ( $feed_active ) {
			echo '<span class="cs-mfb-hero-eyebrow-dot"></span>';
			echo esc_html__( 'Feed Active', 'merchant-feed-booster-lite-for-woocommerce' );
		} else {
			echo '<span class="cs-mfb-hero-eyebrow-dot" style="background:#94a3b8"></span>';
			echo esc_html__( 'Feed Inactive', 'merchant-feed-booster-lite-for-woocommerce' );
		}
		echo '</div>';

		echo '<h1 class="cs-mfb-hero-title">' . esc_html__( 'Merchant Feed Booster', 'merchant-feed-booster-lite-for-woocommerce' ) . '</h1>';
		echo '<p class="cs-mfb-hero-subtitle">' . esc_html__( 'Audit, fix, and optimize your WooCommerce Google Shopping feed.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';

		echo '<div class="cs-mfb-hero-chips">';
		if ( ! empty( $last_run['written'] ) ) {
			printf(
				'<div class="cs-mfb-hero-chip"><span class="cs-mfb-hero-chip-dot" style="background:#60a5fa"></span>%d %s</div>',
				(int) $last_run['written'],
				esc_html__( 'in feed', 'merchant-feed-booster-lite-for-woocommerce' )
			);
		}
		if ( $scanned_count > 0 ) {
			printf(
				'<div class="cs-mfb-hero-chip"><span class="cs-mfb-hero-chip-dot" style="background:#a78bfa"></span>%d %s</div>',
				$scanned_count,
				esc_html__( 'scanned', 'merchant-feed-booster-lite-for-woocommerce' )
			);
		}
		if ( $feed_mtime ) {
			printf(
				'<div class="cs-mfb-hero-chip"><span class="cs-mfb-hero-chip-dot" style="background:#6ee7b7"></span>%s %s</div>',
				esc_html( wp_date( 'Y-m-d H:i', $feed_mtime ) ),
				esc_html__( 'last run', 'merchant-feed-booster-lite-for-woocommerce' )
			);
		}
		if ( ! $feed_exists ) {
			echo '<div class="cs-mfb-hero-chip"><span class="cs-mfb-hero-chip-dot" style="background:#f87171"></span>' . esc_html__( 'No feed yet', 'merchant-feed-booster-lite-for-woocommerce' ) . '</div>';
		}
		echo '</div>'; // chips

		echo '<div class="cs-mfb-hero-btns">';
		echo '<button type="button" class="cs-mfb-btn cs-mfb-btn--hero-primary" data-cs-action="regenerate">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> ';
		echo esc_html__( 'Regenerate Feed', 'merchant-feed-booster-lite-for-woocommerce' );
		echo '</button>';
		echo '<a class="cs-mfb-btn cs-mfb-btn--hero-secondary" href="' . esc_url( admin_url( 'admin.php?page=' . self::HEALTH_SLUG ) ) . '">';
		echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg> ';
		echo esc_html__( 'View Health Report', 'merchant-feed-booster-lite-for-woocommerce' );
		echo '</a>';
		echo '<a class="cs-mfb-btn cs-mfb-btn--hero-secondary" href="' . esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) ) . '">';
		echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg> ';
		echo esc_html__( 'Settings', 'merchant-feed-booster-lite-for-woocommerce' );
		echo '</a>';
		echo '</div>';

		echo '</div>'; // .cs-mfb-hero-content

		echo '<div class="cs-mfb-hero-score">';
		if ( $avg_score > 0 ) {
			printf(
				'<div class="cs-mfb-hero-gauge-ring" data-cs-score="%d">
					<svg class="cs-mfb-hero-gauge-svg" viewBox="0 0 36 36" aria-hidden="true">
						<defs>
							<linearGradient id="cs-mfb-hero-grad" x1="1" y1="0" x2="0" y2="1">
								<stop offset="0%%" stop-color="%s"/>
								<stop offset="100%%" stop-color="%s"/>
							</linearGradient>
						</defs>
						<path class="cs-mfb-hero-gauge-track" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
						<path class="cs-mfb-hero-gauge-fill" stroke="url(#cs-mfb-hero-grad)" stroke-dasharray="0,100"
							d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
					</svg>
					<div class="cs-mfb-hero-gauge-center">
						<span class="cs-mfb-hero-gauge-num">%d</span>
						<span class="cs-mfb-hero-gauge-tier">%s</span>
					</div>
				</div>',
				$avg_score,
				esc_attr( $avg_color ),
				esc_attr( $grad_end ),
				$avg_score,
				esc_html( CodeSolz_MFB_Health_Score::get_tier_label( $avg_tier ) )
			);
			echo '<p class="cs-mfb-hero-gauge-caption">' . esc_html__( 'Overall Health Score', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		} else {
			echo '<div class="cs-mfb-hero-no-score">';
			echo '<p>' . esc_html__( 'No score yet — run your first scan.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
			echo '<button type="button" class="cs-mfb-btn cs-mfb-btn--hero-secondary" data-cs-action="rescan">' . esc_html__( 'Run Scan', 'merchant-feed-booster-lite-for-woocommerce' ) . '</button>';
			echo '</div>';
		}
		echo '</div>'; // .cs-mfb-hero-score

		echo '</div>'; // .cs-mfb-hero-inner
		echo '</div>'; // .cs-mfb-hero

		// ══════════════════════════════════════════════════════════════════
		// 2. STATS ROW (5 cards)
		// ══════════════════════════════════════════════════════════════════
		echo '<div class="cs-mfb-dash-stats">';

		// Products in Feed.
		echo '<div class="cs-mfb-dash-stat">';
		echo '<div class="cs-mfb-dash-stat-icon cs-mfb-dash-stat-icon--blue">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';
		echo '</div>';
		echo '<div class="cs-mfb-dash-stat-body">';
		echo '<span class="cs-mfb-dash-stat-label">' . esc_html__( 'Products in Feed', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-dash-stat-num">' . (int) $products_in_feed . '</span>';
		echo '<span class="cs-mfb-dash-stat-sub">' . esc_html__( 'of', 'merchant-feed-booster-lite-for-woocommerce' ) . ' ' . (int) $total_products . ' ' . esc_html__( 'total', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div></div>';

		// Products Scanned.
		echo '<div class="cs-mfb-dash-stat">';
		echo '<div class="cs-mfb-dash-stat-icon cs-mfb-dash-stat-icon--violet">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
		echo '</div>';
		echo '<div class="cs-mfb-dash-stat-body">';
		echo '<span class="cs-mfb-dash-stat-label">' . esc_html__( 'Products Scanned', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-dash-stat-num">' . (int) $scanned_count . '</span>';
		echo '<span class="cs-mfb-dash-stat-sub">' . esc_html__( 'health scored', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div></div>';

		// Issues Found.
		echo '<div class="cs-mfb-dash-stat cs-mfb-dash-stat--red">';
		echo '<div class="cs-mfb-dash-stat-icon cs-mfb-dash-stat-icon--red">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
		echo '</div>';
		echo '<div class="cs-mfb-dash-stat-body">';
		echo '<span class="cs-mfb-dash-stat-label">' . esc_html__( 'Issues Found', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-dash-stat-num">' . (int) $error_count . '</span>';
		echo '<span class="cs-mfb-dash-stat-sub">' . esc_html__( 'errors across products', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div></div>';

		// Warnings.
		echo '<div class="cs-mfb-dash-stat cs-mfb-dash-stat--amber">';
		echo '<div class="cs-mfb-dash-stat-icon cs-mfb-dash-stat-icon--amber">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
		echo '</div>';
		echo '<div class="cs-mfb-dash-stat-body">';
		echo '<span class="cs-mfb-dash-stat-label">' . esc_html__( 'Warnings', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-dash-stat-num">' . (int) $warning_count . '</span>';
		echo '<span class="cs-mfb-dash-stat-sub">' . esc_html__( 'to review', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div></div>';

		// Last Generated.
		echo '<div class="cs-mfb-dash-stat cs-mfb-dash-stat--green">';
		echo '<div class="cs-mfb-dash-stat-icon cs-mfb-dash-stat-icon--green">';
		echo '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
		echo '</div>';
		echo '<div class="cs-mfb-dash-stat-body">';
		echo '<span class="cs-mfb-dash-stat-label">' . esc_html__( 'Last Generated', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-dash-stat-num cs-mfb-dash-stat-num--date">' . esc_html( $last_gen_display ) . '</span>';
		echo '<span class="cs-mfb-dash-stat-sub">' . esc_html( $feed_size ) . '</span>';
		echo '</div></div>';

		echo '</div>'; // .cs-mfb-dash-stats

		// ══════════════════════════════════════════════════════════════════
		// 3. THREE-COLUMN MAIN GRID
		// ══════════════════════════════════════════════════════════════════
		echo '<div class="cs-mfb-dash-grid">';

		// ── LEFT COLUMN: Feed Quality Breakdown ───────────────────────────
		echo '<div class="cs-mfb-dash-col">';
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#ecfdf5">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 13"/></svg>';
		echo '</div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'Feed Quality Breakdown', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		$quality_rows = array(
			'title'       => __( 'Title Quality', 'merchant-feed-booster-lite-for-woocommerce' ),
			'image'       => __( 'Image Quality', 'merchant-feed-booster-lite-for-woocommerce' ),
			'price'       => __( 'Price Data', 'merchant-feed-booster-lite-for-woocommerce' ),
			'identifier'  => __( 'Product Identifiers', 'merchant-feed-booster-lite-for-woocommerce' ),
			'description' => __( 'Description Quality', 'merchant-feed-booster-lite-for-woocommerce' ),
			'category'    => __( 'Category Mapping', 'merchant-feed-booster-lite-for-woocommerce' ),
		);
		foreach ( $quality_rows as $qkey => $qlabel ) {
			$pct = isset( $quality_pct[ $qkey ] ) ? (int) $quality_pct[ $qkey ] : 100;
			if ( $pct >= 80 ) {
				$bar_color = '#22c55e';
			} elseif ( $pct >= 60 ) {
				$bar_color = '#f59e0b';
			} else {
				$bar_color = '#ef4444';
			}
			echo '<div class="cs-mfb-quality-row">';
			echo '<span class="cs-mfb-quality-label">' . esc_html( $qlabel ) . '</span>';
			echo '<div class="cs-mfb-quality-bar-wrap">';
			printf( '<div class="cs-mfb-quality-bar" style="width:%d%%;background:%s"></div>', $pct, esc_attr( $bar_color ) );
			echo '</div>';
			printf( '<span class="cs-mfb-quality-pct" style="color:%s">%d%%</span>', esc_attr( $bar_color ), $pct );
			echo '</div>';
		}

		echo '</div>'; // card feed-quality

		// ── Score Distribution card ───────────────────────────────────────
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#fdf4ff">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>';
		echo '</div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'Score Distribution', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		if ( $scanned_count > 0 ) {
			foreach ( $tier_dist as $tier_key => $tier_count ) {
				$bar_w  = (int) round( $tier_count / $tier_dist_max * 100 );
				$color  = $tier_dist_colors[ $tier_key ];
				$label  = $tier_dist_labels[ $tier_key ];
				echo '<div class="cs-mfb-tier-dist-row">';
				echo '<span class="cs-mfb-tier-dist-label">' . esc_html( $label ) . '</span>';
				echo '<div class="cs-mfb-tier-dist-bar-wrap">';
				printf( '<div class="cs-mfb-tier-dist-bar" style="width:%d%%;background:%s"></div>', $bar_w, esc_attr( $color ) );
				echo '</div>';
				printf( '<span class="cs-mfb-tier-dist-count" style="color:%s">%d</span>', esc_attr( $color ), $tier_count );
				echo '</div>';
			}
		} else {
			echo '<p style="font-size:13px;color:#94a3b8;padding:8px 0">' . esc_html__( 'Run a scan to see score distribution.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		}
		echo '</div>'; // card score-distribution

		// ── Feed URL ──────────────────────────────────────────────────────
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#f0f9ff">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
		echo '</div>';
		echo '<div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'Feed URL', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<p style="font-size:12px;color:#94a3b8;margin:2px 0 0">' . esc_html__( 'Add this URL to Google Merchant Center as a Scheduled Fetch.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		echo '</div>';
		echo '</div>';

		if ( $feed_url ) {
			echo '<div class="cs-mfb-url-box" style="margin-bottom:12px"><code>' . esc_html( $feed_url ) . '</code></div>';
			echo '<div class="cs-mfb-url-actions">';
			echo '<button type="button" class="cs-mfb-btn cs-mfb-btn--primary cs-mfb-btn--sm" data-cs-copy-url="1">';
			echo '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> ';
			echo esc_html__( 'Copy URL', 'merchant-feed-booster-lite-for-woocommerce' );
			echo '</button>';
			echo '<a class="cs-mfb-btn cs-mfb-btn--secondary cs-mfb-btn--sm" href="' . esc_url( $feed_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
			echo '<a class="cs-mfb-btn cs-mfb-btn--ghost cs-mfb-btn--sm" href="' . esc_url( admin_url( 'admin.php?page=' . self::PREVIEW_SLUG ) ) . '">' . esc_html__( 'Preview', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
			echo '</div>';
		} else {
			echo '<p style="color:#94a3b8;font-size:13px">' . esc_html__( 'Feed URL will appear here after first generation.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		}

		echo '<div class="cs-mfb-feed-url-meta">';

		echo '<div class="cs-mfb-feed-url-meta-item">';
		echo '<span class="cs-mfb-feed-url-meta-label">' . esc_html__( 'Status', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		if ( $feed_accessible ) {
			echo '<span class="cs-mfb-feed-url-meta-val"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:5px"></span>' . esc_html__( 'Accessible', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		} else {
			echo '<span class="cs-mfb-feed-url-meta-val"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;margin-right:5px"></span>' . esc_html__( 'Not accessible', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		}
		echo '</div>';

		echo '<div class="cs-mfb-feed-url-meta-item">';
		echo '<span class="cs-mfb-feed-url-meta-label">' . esc_html__( 'File Size', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-feed-url-meta-val">' . esc_html( $feed_size ) . '</span>';
		echo '</div>';

		echo '<div class="cs-mfb-feed-url-meta-item">';
		echo '<span class="cs-mfb-feed-url-meta-label">' . esc_html__( 'Products', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-feed-url-meta-val">' . (int) $products_in_feed . '</span>';
		echo '</div>';

		echo '<div class="cs-mfb-feed-url-meta-item">';
		echo '<span class="cs-mfb-feed-url-meta-label">' . esc_html__( 'Next Refresh', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-feed-url-meta-val">';
		echo $next_cron
			? esc_html( wp_date( 'D H:i', $next_cron ) )
			: '<span style="color:#94a3b8">' . esc_html__( 'Not scheduled', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</span>';
		echo '</div>';

		echo '</div>'; // feed-url-meta
		echo '</div>'; // card feed-url

		echo '</div>'; // left col

		// ── MIDDLE COLUMN: Top Issues + Recent Activity ───────────────────
		echo '<div class="cs-mfb-dash-col">';

		// Top Issues to Fix.
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#fef2f2">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
		echo '</div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'Top Issues to Fix', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		if ( empty( $top_issues ) ) {
			echo '<p style="font-size:13px;color:#64748b;padding:8px 0">' . esc_html__( 'No issues found. Run a scan to see results.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		} else {
			foreach ( $top_issues as $rule_id => $affected ) {
				$desc = isset( $rule_descriptions[ $rule_id ] ) ? $rule_descriptions[ $rule_id ] : $rule_id;
				echo '<div class="cs-mfb-issue-row">';
				echo '<span class="cs-mfb-issue-dot"></span>';
				echo '<span class="cs-mfb-issue-name">' . esc_html( $desc ) . '</span>';
				printf( '<span class="cs-mfb-issue-count-badge">%d %s</span>', (int) $affected, esc_html__( 'products', 'merchant-feed-booster-lite-for-woocommerce' ) );
				echo '</div>';
			}
		}

		echo '<div style="margin-top:14px;padding-top:12px;border-top:1px solid #f1f5f9">';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::HEALTH_SLUG ) ) . '" style="font-size:12.5px;color:#4f46e5;font-weight:600;text-decoration:none">';
		echo esc_html__( 'View Full Health Report', 'merchant-feed-booster-lite-for-woocommerce' ) . ' &rarr;';
		echo '</a>';
		echo '</div>';
		echo '</div>'; // card top-issues

		// Products Needing Attention (lowest-scored).
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#fff7ed">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
		echo '</div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'Needs Attention', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		if ( empty( $worst_products ) ) {
			echo '<p style="font-size:13px;color:#94a3b8;padding:8px 0">' . esc_html__( 'No scan data yet. Run a health scan to see products needing attention.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		} else {
			foreach ( $worst_products as $wp_row ) {
				$pid       = (int) $wp_row['product_id'];
				$pscore    = (int) $wp_row['score'];
				$ptier     = CodeSolz_MFB_Health_Score::get_tier( $pscore );
				$pcolor    = CodeSolz_MFB_Health_Score::get_tier_color( $ptier );
				$pname     = get_the_title( $pid );
				$pname     = $pname ? ( strlen( $pname ) > 36 ? mb_substr( $pname, 0, 36 ) . '…' : $pname ) : '#' . $pid;
				$edit_link = get_edit_post_link( $pid );
				echo '<div class="cs-mfb-attn-row">';
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::render_score_gauge_mini( $pscore, $pcolor );
				echo '<span class="cs-mfb-attn-name">' . esc_html( $pname ) . '</span>';
				if ( $edit_link ) {
					echo '<a href="' . esc_url( $edit_link ) . '" class="cs-mfb-attn-fix" title="' . esc_attr__( 'Edit product', 'merchant-feed-booster-lite-for-woocommerce' ) . '">';
					echo '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
					echo '</a>';
				}
				echo '</div>';
			}
			echo '<div style="margin-top:12px;padding-top:10px;border-top:1px solid #f1f5f9">';
			echo '<a href="' . esc_url( add_query_arg( array( 'page' => self::HEALTH_SLUG, 'orderby' => 'score', 'order' => 'asc' ), admin_url( 'admin.php' ) ) ) . '" style="font-size:12.5px;color:#4f46e5;font-weight:600;text-decoration:none">' . esc_html__( 'View all low-score products', 'merchant-feed-booster-lite-for-woocommerce' ) . ' &rarr;</a>';
			echo '</div>';
		}
		echo '</div>'; // card needs-attention

		// Recent Activity.
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#f0f9ff">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
		echo '</div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'Recent Activity', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		if ( empty( $activity_log ) ) {
			echo '<p style="font-size:13px;color:#64748b;padding:8px 0">' . esc_html__( 'No activity yet. Regenerate the feed or run a scan.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		} else {
			$activity_type_colors = array( 'feed' => '#3b82f6', 'scan' => '#8b5cf6', 'cache' => '#f59e0b', 'error' => '#ef4444' );
			$shown_activity = 0;
			foreach ( $activity_log as $entry ) {
				if ( $shown_activity >= 5 ) {
					break;
				}
				$dot_color = isset( $activity_type_colors[ $entry['type'] ] ) ? $activity_type_colors[ $entry['type'] ] : '#94a3b8';
				echo '<div class="cs-mfb-activity-item">';
				printf( '<span class="cs-mfb-activity-dot" style="background:%s"></span>', esc_attr( $dot_color ) );
				echo '<div>';
				echo '<div class="cs-mfb-activity-msg">' . esc_html( $entry['message'] ) . '</div>';
				echo '<div class="cs-mfb-activity-time">' . esc_html( human_time_diff( (int) $entry['time'], time() ) . ' ' . __( 'ago', 'merchant-feed-booster-lite-for-woocommerce' ) ) . '</div>';
				echo '</div>';
				echo '</div>';
				$shown_activity++;
			}
		}
		echo '</div>'; // card recent-activity

		echo '</div>'; // middle col

		// ── RIGHT COLUMN: Readiness + Coverage + Quick Actions + Fix First ─
		echo '<div class="cs-mfb-dash-col">';

		// Product Coverage.
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#f5f3ff">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>';
		echo '</div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'Product Coverage', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		$hidden_ct = max( 0, $total_products - $products_in_feed - $products_oos - $products_excl );
		$cov_total = max( 1, $total_products );
		$cov_segs  = array(
			array( 'color' => '#4f46e5', 'label' => __( 'Included',     'merchant-feed-booster-lite-for-woocommerce' ), 'count' => $products_in_feed ),
			array( 'color' => '#f59e0b', 'label' => __( 'Out of stock', 'merchant-feed-booster-lite-for-woocommerce' ), 'count' => $products_oos ),
			array( 'color' => '#ef4444', 'label' => __( 'Excluded',     'merchant-feed-booster-lite-for-woocommerce' ), 'count' => $products_excl ),
			array( 'color' => '#94a3b8', 'label' => __( 'Hidden',       'merchant-feed-booster-lite-for-woocommerce' ), 'count' => $hidden_ct ),
		);

		echo '<div class="cs-mfb-coverage-wrap">';

		// Ring (donut) chart — each segment is a stroked circle, rotated to its
		// start angle. Circumference ≈ 226 SVG units for r=36, stroke-width=14.
		$ring_r    = 36;
		$ring_circ = 2.0 * M_PI * $ring_r;
		$ring_gap  = 2.0;   // gap between segments in SVG units
		$ring_deg  = -90.0; // start at 12 o'clock

		echo '<svg class="cs-mfb-ring-svg" viewBox="0 0 100 100">';
		printf( '<circle cx="50" cy="50" r="%d" fill="none" stroke="#f1f5f9" stroke-width="14"/>', $ring_r );

		if ( $total_products > 0 ) {
			foreach ( $cov_segs as $i => $seg ) {
				if ( (int) $seg['count'] <= 0 ) {
					continue;
				}
				$pct = (int) $seg['count'] / $cov_total;
				$arc = max( 0.0, $pct * $ring_circ - $ring_gap );
				printf(
					'<circle class="cs-mfb-ring-seg" cx="50" cy="50" r="%d" fill="none" stroke="%s" stroke-width="14" stroke-dasharray="%.4f %.4f" stroke-linecap="butt" transform="rotate(%.4f 50 50)" data-label="%s" data-count="%d" data-pct="%d" data-color="%s"/>',
					$ring_r,
					esc_attr( $seg['color'] ),
					$arc, $ring_circ,
					$ring_deg,
					esc_attr( $seg['label'] ),
					(int) $seg['count'],
					(int) round( $pct * 100 ),
					esc_attr( $seg['color'] )
				);
				$ring_deg += $pct * 360.0;
			}
		}

		// Center text — JS swaps these on hover to show the hovered segment.
		printf( '<text class="cs-mfb-ring-num" x="50" y="46" text-anchor="middle" dominant-baseline="middle">%d</text>', (int) $total_products );
		echo '<text class="cs-mfb-ring-lbl" x="50" y="62" text-anchor="middle">products</text>';
		echo '</svg>';

		echo '<div class="cs-mfb-coverage-legend">';
		foreach ( $cov_segs as $i => $li ) {
			printf(
				'<div class="cs-mfb-coverage-legend-item" data-cov-index="%d"><span class="cs-mfb-coverage-dot" style="background:%s"></span><span>%s</span><strong>%d</strong></div>',
				$i,
				esc_attr( $li['color'] ),
				esc_html( $li['label'] ),
				(int) $li['count']
			);
		}
		echo '</div>';
		echo '</div>'; // coverage-wrap
		echo '</div>'; // card coverage

		// Quick Actions.
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#eff6ff">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
		echo '</div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'Quick Actions', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		echo '<button type="button" class="cs-mfb-btn cs-mfb-btn--primary" style="width:100%;justify-content:center;margin-bottom:8px" data-cs-action="regenerate">';
		echo '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> ';
		echo esc_html__( 'Regenerate Feed', 'merchant-feed-booster-lite-for-woocommerce' );
		echo '</button>';

		// Ghost action rows.
		$ghost_actions = array(
			array(
				'href'  => admin_url( 'admin.php?page=' . self::HEALTH_SLUG ),
				'label' => __( 'Scan Feed Health', 'merchant-feed-booster-lite-for-woocommerce' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
			),
			array(
				'href'  => admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ),
				'label' => __( 'Feed Settings', 'merchant-feed-booster-lite-for-woocommerce' ),
				'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>',
			),
		);
		foreach ( $ghost_actions as $ga ) {
			echo '<a href="' . esc_url( $ga['href'] ) . '" class="cs-mfb-quick-action-row">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $ga['icon'];
			echo '<span style="flex:1">' . esc_html( $ga['label'] ) . '</span>';
			echo '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>';
			echo '</a>';
		}
		echo '<button type="button" class="cs-mfb-quick-action-row" data-cs-action="clear-cache" style="width:100%;text-align:left;background:none;border:none;cursor:pointer">';
		echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.13"/></svg>';
		echo '<span style="flex:1">' . esc_html__( 'Clear Score Cache', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>';
		echo '</button>';

		echo '</div>'; // card quick-actions

		// What Should I Fix First?
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#fffbeb">';
		echo '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="2" x2="12" y2="6"/><path d="M12 6C9.24 6 7 8.24 7 11c0 2.03 1.17 3.79 2.88 4.66L9 18h6l-.88-2.34C15.83 14.79 17 13.03 17 11c0-2.76-2.24-5-5-5z"/><line x1="9" y1="21" x2="15" y2="21"/><line x1="9" y1="18" x2="15" y2="18"/></svg>';
		echo '</div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'What Should I Fix First?', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		if ( empty( $top_issues ) ) {
			echo '<p class="cs-mfb-fix-first-body">' . esc_html__( 'Run a scan to get personalized fix recommendations.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		} else {
			$first_rule = key( $top_issues );
			$first_cnt  = reset( $top_issues );
			$first_desc = isset( $rule_descriptions[ $first_rule ] ) ? $rule_descriptions[ $first_rule ] : $first_rule;
			$score_impact = (int) ceil( $first_cnt / max( 1, $scanned_count ) * 30 );

			echo '<p class="cs-mfb-fix-first-body">';
			printf(
				/* translators: 1: issue description, 2: affected count */
				esc_html__( 'Fix "%1$s" — affects %2$d product(s).', 'merchant-feed-booster-lite-for-woocommerce' ),
				esc_html( $first_desc ),
				(int) $first_cnt
			);
			echo '</p>';
			printf(
				'<p style="font-size:11.5px;color:#64748b;margin:6px 0 12px">%s <strong style="color:#16a34a">+%d pts</strong></p>',
				esc_html__( 'Estimated score impact:', 'merchant-feed-booster-lite-for-woocommerce' ),
				$score_impact
			);
			echo '<div style="display:flex;gap:8px">';
			echo '<a href="' . esc_url( add_query_arg( array( 'page' => self::HEALTH_SLUG, 'cs_mfb_issues_only' => '1' ), admin_url( 'admin.php' ) ) ) . '" class="cs-mfb-btn cs-mfb-btn--primary cs-mfb-btn--sm" style="flex:1;justify-content:center">' . esc_html__( 'View Products', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
			echo '<a href="https://support.google.com/merchants/answer/6324475" target="_blank" rel="noopener noreferrer" class="cs-mfb-btn cs-mfb-btn--ghost cs-mfb-btn--sm" style="flex:1;justify-content:center">' . esc_html__( 'Learn More', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
			echo '</div>';
		}
		echo '</div>'; // card fix-first

		// Google Merchant Readiness.
		echo '<div class="cs-mfb-dash-card">';
		echo '<div class="cs-mfb-dash-card-hdr">';
		echo '<div class="cs-mfb-dash-card-icon" style="background:#fafafa;border:1px solid #e2e8f0">';
		echo '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>';
		echo '</div>';
		echo '<span class="cs-mfb-dash-card-title">' . esc_html__( 'Google Merchant Readiness', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		if ( 0 === $error_count && $scanned_count > 0 ) {
			echo '<span class="cs-mfb-gmc-badge--ready">' . esc_html__( 'Ready', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
			echo '<p style="font-size:12.5px;color:#16a34a;margin:10px 0 0;line-height:1.5">' . esc_html__( 'No critical errors detected. Your feed looks great!', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		} else {
			echo '<span class="cs-mfb-gmc-badge--not-ready">' . esc_html__( 'Not Ready', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
			if ( 0 === $scanned_count ) {
				$readiness_msg = esc_html__( 'Run a product scan to check your feed readiness.', 'merchant-feed-booster-lite-for-woocommerce' );
			} else {
				$readiness_msg = sprintf(
					/* translators: %d: number of errors */
					esc_html__( '%d critical error(s) need to be fixed before your feed is Google-ready.', 'merchant-feed-booster-lite-for-woocommerce' ),
					$error_count
				);
			}
			echo '<p style="font-size:12.5px;color:#64748b;margin:10px 0 12px;line-height:1.5">' . $readiness_msg . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::HEALTH_SLUG ) ) . '" style="font-size:12.5px;color:#4f46e5;font-weight:600;text-decoration:none">' . esc_html__( 'Fix Issues', 'merchant-feed-booster-lite-for-woocommerce' ) . ' &rarr;</a>';
		}
		echo '</div>'; // card gmc-readiness

		echo '</div>'; // right col

		echo '</div>'; // .cs-mfb-dash-grid

		echo '</div>'; // .wrap
	}

	/**
	 * Render the Settings page.
	 */
	public static function render_settings() {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}
		$s         = CodeSolz_MFB_Settings::all();
		$opt       = CodeSolz_MFB_Settings::OPTION_KEY;
		$feed_url  = CodeSolz_MFB_Feed_Generator::public_feed_url();
		$feed_path = CodeSolz_MFB_Feed_Generator::feed_file_path();
		$is_active = (bool) $s['enabled'];
		$total_products = (int) wp_count_posts( 'product' )->publish;
		$last_generated = '';
		$last_generated_full = '';
		$next_refresh   = '';
		$next_refresh_full = '';
		if ( $feed_path && file_exists( $feed_path ) ) {
			$mtime = (int) filemtime( $feed_path );
			$last_generated      = human_time_diff( $mtime, time() ) . ' ' . __( 'ago', 'merchant-feed-booster-lite-for-woocommerce' );
			$last_generated_full = wp_date( get_option( 'time_format' ), $mtime );
		}
		$next_cron = wp_next_scheduled( CS_MFB_CRON_HOOK );
		if ( $next_cron ) {
			$next_refresh      = __( 'Today', 'merchant-feed-booster-lite-for-woocommerce' ) . ', ' . wp_date( get_option( 'time_format' ), $next_cron );
			$next_refresh_full = human_time_diff( time(), (int) $next_cron ) . ' ' . __( 'from now', 'merchant-feed-booster-lite-for-woocommerce' );
		}
		$tz_string = wp_timezone_string();

		echo '<div class="wrap cs-mfb-page">';

		// ── Page header card ──────────────────────────────────────────────
		echo '<div class="cs-mfb-page-hdr">';
		echo '<div class="cs-mfb-page-hdr-top">';
		echo '<div class="cs-mfb-page-hdr-left">';
		echo '<div class="cs-mfb-page-hdr-icon" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe)">';
		echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>';
		echo '</div>';
		echo '<div>';
		echo '<h1 class="cs-mfb-page-hdr-title">' . esc_html__( 'Feed Settings', 'merchant-feed-booster-lite-for-woocommerce' ) . '</h1>';
		echo '<p class="cs-mfb-page-hdr-sub">' . esc_html__( 'Configure how your Google Shopping feed is generated, labelled, and refreshed.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		echo '</div></div>';
		echo '<div class="cs-mfb-page-hdr-right">';
		if ( $is_active ) {
			echo '<div class="cs-mfb-hdr-status-group">';
			echo '<span class="cs-mfb-hdr-status-badge"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' . esc_html__( 'Feed Active', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
			if ( $last_generated ) {
				echo '<span class="cs-mfb-hdr-status-sub"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' . esc_html( sprintf( __( 'Last updated %s', 'merchant-feed-booster-lite-for-woocommerce' ), $last_generated ) ) . '</span>';
			}
			echo '</div>';
		} else {
			echo '<span class="cs-mfb-hdr-status-badge cs-mfb-hdr-status-badge--off"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' . esc_html__( 'Feed Paused', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		}
		if ( $feed_url ) {
			echo '<a class="cs-mfb-btn cs-mfb-btn--primary cs-mfb-btn--sm" href="' . esc_url( admin_url( 'admin.php?page=' . self::PREVIEW_SLUG ) ) . '" style="gap:6px">';
			echo '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' . esc_html__( 'Preview Feed', 'merchant-feed-booster-lite-for-woocommerce' );
			echo '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
			echo '</a>';
		}
		echo '</div>';
		echo '</div>'; // hdr-top
		echo '</div>'; // page-hdr

		// ── Two-column: main settings + sidebar ───────────────────────────
		echo '<div class="cs-mfb-settings-main-layout">';

		// ── Left: form + sections ─────────────────────────────────────────
		echo '<div class="cs-mfb-settings-main">';
		echo '<form id="cs-mfb-settings-form" method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'cs_mfb_settings_group' );

		// ── Section: Feed Content ─────────────────────────────────────────
		echo '<div class="cs-mfb-settings-panel">';
		echo '<div class="cs-mfb-settings-section-hdr"><span class="cs-mfb-settings-section-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>' . esc_html__( 'FEED CONTENT', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span><p class="cs-mfb-settings-section-sub">' . esc_html__( 'Control the basic information and defaults used in your feed.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';

		// Enable feed
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_enabled">' . esc_html__( 'Enable feed', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( 'Toggle off to stop generating and serving the XML feed.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl"><label class="cs-mfb-toggle-wrap">';
		echo '<input class="cs-mfb-toggle-cb" type="checkbox" name="' . esc_attr( $opt ) . '[enabled]" id="cs_mfb_enabled" value="1" ' . checked( 1, (int) $s['enabled'], false ) . ' />';
		echo '<span class="cs-mfb-toggle-track" aria-hidden="true"><span class="cs-mfb-toggle-thumb"></span></span>';
		echo '<span class="cs-mfb-toggle-text">' . esc_html__( 'Feed is active', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</label></div></div>';

		// Feed title
		$title_len = mb_strlen( $s['feed_title'] );
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_feed_title">' . esc_html__( 'Feed title', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( 'Appears as the &lt;title&gt; in the RSS feed header sent to Google.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl cs-mfb-setting-ctrl--col">';
		echo '<input type="text" name="' . esc_attr( $opt ) . '[feed_title]" id="cs_mfb_feed_title" value="' . esc_attr( $s['feed_title'] ) . '" placeholder="' . esc_attr( get_bloginfo( 'name' ) . ' – Google Shopping' ) . '" class="cs-mfb-text-input" maxlength="150" data-cs-maxlen="150" data-cs-counter="cs_mfb_feed_title_cnt" />';
		printf( '<span class="cs-mfb-char-counter" id="cs_mfb_feed_title_cnt">%d / 150 %s</span>', $title_len, esc_html__( 'characters', 'merchant-feed-booster-lite-for-woocommerce' ) );
		echo '</div></div>';

		// Title prefix
		$prefix_len = mb_strlen( $s['feed_prefix'] );
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_feed_prefix">' . esc_html__( 'Title prefix', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( 'Optional text prepended to every product title. Useful for adding your brand name.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl cs-mfb-setting-ctrl--col">';
		echo '<input type="text" name="' . esc_attr( $opt ) . '[feed_prefix]" id="cs_mfb_feed_prefix" value="' . esc_attr( $s['feed_prefix'] ) . '" placeholder="' . esc_attr__( 'e.g. BrandName –', 'merchant-feed-booster-lite-for-woocommerce' ) . '" class="cs-mfb-text-input" maxlength="100" data-cs-maxlen="100" data-cs-counter="cs_mfb_feed_prefix_cnt" />';
		printf( '<span class="cs-mfb-char-counter" id="cs_mfb_feed_prefix_cnt">%d / 100 %s</span>', $prefix_len, esc_html__( 'characters', 'merchant-feed-booster-lite-for-woocommerce' ) );
		echo '</div></div>';

		// Default brand
		$brand_len = mb_strlen( $s['default_brand'] );
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_default_brand">' . esc_html__( 'Default brand', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( 'Fallback brand value for products that have no brand attribute set.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl cs-mfb-setting-ctrl--col">';
		echo '<input type="text" name="' . esc_attr( $opt ) . '[default_brand]" id="cs_mfb_default_brand" value="' . esc_attr( $s['default_brand'] ) . '" placeholder="' . esc_attr( get_bloginfo( 'name' ) ) . '" class="cs-mfb-text-input" maxlength="100" data-cs-maxlen="100" data-cs-counter="cs_mfb_default_brand_cnt" />';
		printf( '<span class="cs-mfb-char-counter" id="cs_mfb_default_brand_cnt">%d / 100 %s</span>', $brand_len, esc_html__( 'characters', 'merchant-feed-booster-lite-for-woocommerce' ) );
		echo '</div></div>';

		// Default Google category
		$gcat_len = mb_strlen( $s['default_google_category'] );
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_default_google_category">' . esc_html__( 'Default Google category', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( "Used when a product has no Google category set. See Google's product taxonomy.", 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl cs-mfb-setting-ctrl--col">';
		echo '<input type="text" name="' . esc_attr( $opt ) . '[default_google_category]" id="cs_mfb_default_google_category" value="' . esc_attr( $s['default_google_category'] ) . '" placeholder="' . esc_attr__( 'e.g. Apparel & Accessories > Clothing', 'merchant-feed-booster-lite-for-woocommerce' ) . '" class="cs-mfb-text-input" maxlength="150" data-cs-maxlen="150" data-cs-counter="cs_mfb_gcat_cnt" />';
		printf( '<span class="cs-mfb-char-counter" id="cs_mfb_gcat_cnt">%d / 150 %s</span>', $gcat_len, esc_html__( 'characters', 'merchant-feed-booster-lite-for-woocommerce' ) );
		echo '</div></div>';

		echo '</div>'; // panel feed-content

		// ── Section: Feed Delivery ────────────────────────────────────────
		echo '<div class="cs-mfb-settings-panel">';
		echo '<div class="cs-mfb-settings-section-hdr"><span class="cs-mfb-settings-section-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' . esc_html__( 'FEED DELIVERY', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span><p class="cs-mfb-settings-section-sub">' . esc_html__( 'Choose which products to include and how often the feed is refreshed.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';

		// Out-of-stock products
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_include_oos">' . esc_html__( 'Out-of-stock products', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( 'Include products that are currently out of stock. Their availability will be marked "out_of_stock".', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl"><label class="cs-mfb-toggle-wrap">';
		echo '<input class="cs-mfb-toggle-cb" type="checkbox" name="' . esc_attr( $opt ) . '[include_oos]" id="cs_mfb_include_oos" value="1" ' . checked( 1, (int) $s['include_oos'], false ) . ' />';
		echo '<span class="cs-mfb-toggle-track" aria-hidden="true"><span class="cs-mfb-toggle-thumb"></span></span>';
		echo '<span class="cs-mfb-toggle-text">' . esc_html__( 'Include out-of-stock products', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</label></div></div>';

		// Auto-refresh frequency with pill selector
		$pill_standard  = array( 'daily', 'twicedaily', 'weekly' );
		$is_custom_freq = ! in_array( $s['refresh_frequency'], $pill_standard, true );

		echo '<div class="cs-mfb-setting-row cs-mfb-setting-row--stacked">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name">' . esc_html__( 'Auto-refresh frequency', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( 'How often WP-Cron should automatically regenerate the feed.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl-full">';

		// Pill selector UI
		echo '<div class="cs-mfb-schedule-pills" data-cs-pills="cs_mfb_refresh_frequency">';
		$freq_pills = array(
			'daily'      => __( 'Daily',       'merchant-feed-booster-lite-for-woocommerce' ),
			'twicedaily' => __( 'Twice Daily',  'merchant-feed-booster-lite-for-woocommerce' ),
			'weekly'     => __( 'Weekly',       'merchant-feed-booster-lite-for-woocommerce' ),
		);
		foreach ( $freq_pills as $pill_val => $pill_label ) {
			printf(
				'<button type="button" class="cs-mfb-schedule-pill%s" data-cs-pill-val="%s">%s</button>',
				( ! $is_custom_freq && $pill_val === $s['refresh_frequency'] ) ? ' cs-mfb-schedule-pill--active' : '',
				esc_attr( $pill_val ),
				esc_html( $pill_label )
			);
		}
		// Custom pill — no data-cs-pill-val so JS handles it separately
		printf(
			'<button type="button" class="cs-mfb-schedule-pill%s" data-cs-pill-custom>%s</button>',
			$is_custom_freq ? ' cs-mfb-schedule-pill--active' : '',
			esc_html__( 'Custom', 'merchant-feed-booster-lite-for-woocommerce' )
		);
		echo '</div>';

		// Single hidden input carries the frequency value; JS updates it on pill click.
		$freq_hidden_val = $is_custom_freq ? 'custom' : $s['refresh_frequency'];
		echo '<input type="hidden" name="' . esc_attr( $opt ) . '[refresh_frequency]" id="cs_mfb_refresh_frequency_hidden" value="' . esc_attr( $freq_hidden_val ) . '" />';

		// Custom interval picker — visible only when Custom pill is active.
		echo '<div id="cs-mfb-custom-freq-wrap" class="cs-mfb-custom-freq-wrap"' . ( $is_custom_freq ? '' : ' hidden' ) . '>';
		echo '<p class="cs-mfb-custom-freq-label">' . esc_html__( 'Repeat every', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		echo '<div class="cs-mfb-custom-interval-row">';
		// Number
		printf(
			'<input type="number" name="%s[refresh_custom_interval]" id="cs_mfb_refresh_custom_interval" value="%d" min="1" max="999" class="cs-mfb-interval-num" />',
			esc_attr( $opt ),
			(int) $s['refresh_custom_interval']
		);
		// Unit
		echo '<select name="' . esc_attr( $opt ) . '[refresh_custom_unit]" id="cs_mfb_refresh_custom_unit" class="cs-mfb-select-input cs-mfb-interval-unit">';
		$units = array(
			'hours' => __( 'Hours',  'merchant-feed-booster-lite-for-woocommerce' ),
			'days'  => __( 'Days',   'merchant-feed-booster-lite-for-woocommerce' ),
			'weeks' => __( 'Weeks',  'merchant-feed-booster-lite-for-woocommerce' ),
		);
		foreach ( $units as $u_val => $u_label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $u_val ), selected( $u_val, $s['refresh_custom_unit'], false ), esc_html( $u_label ) );
		}
		echo '</select>';
		echo '</div>';
		if ( $is_custom_freq ) {
			printf(
				'<p class="cs-mfb-setting-hint" style="margin-top:6px">%s</p>',
				esc_html( sprintf(
					/* translators: 1: number, 2: unit */
					__( 'Feed will regenerate every %1$d %2$s.', 'merchant-feed-booster-lite-for-woocommerce' ),
					(int) $s['refresh_custom_interval'],
					$s['refresh_custom_unit']
				) )
			);
		}
		echo '</div>';

		if ( ! $is_custom_freq ) {
			echo '<p class="cs-mfb-setting-hint" style="margin-top:8px">' . esc_html__( 'Daily or Twice Daily is recommended for most stores.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		}
		echo '</div></div>';

		// Quick schedule time
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_refresh_time">' . esc_html__( 'Quick schedule', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( 'Choose a preset or custom time for the daily refresh.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl cs-mfb-setting-ctrl--col">';
		echo '<input type="time" name="' . esc_attr( $opt ) . '[refresh_time]" id="cs_mfb_refresh_time" value="' . esc_attr( $s['refresh_time'] ) . '" class="cs-mfb-text-input" style="width:140px" />';
		printf(
			'<span class="cs-mfb-setting-hint">%s</span>',
			esc_html( sprintf(
				/* translators: %s: timezone string */
				__( 'All times are shown in your site timezone (%s).', 'merchant-feed-booster-lite-for-woocommerce' ),
				$tz_string
			) )
		);
		echo '</div></div>';

		echo '</div>'; // panel feed-delivery

		// ── Section: Optimization & Validation ───────────────────────────
		echo '<div class="cs-mfb-settings-panel">';
		echo '<div class="cs-mfb-settings-section-hdr"><span class="cs-mfb-settings-section-badge" style="color:#0891b2;border-left-color:#0891b2"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>' . esc_html__( 'OPTIMIZATION & VALIDATION', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span><p class="cs-mfb-settings-section-sub">' . esc_html__( "Improve data quality and ensure your feed meets Google's requirements.", 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';

		// Enforce GTIN / MPN
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_enforce_gtin_mpn">' . esc_html__( 'Enforce GTIN / MPN', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( 'Warn when products are missing GTIN or MPN identifiers.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl cs-mfb-setting-ctrl--badges"><label class="cs-mfb-toggle-wrap">';
		echo '<input class="cs-mfb-toggle-cb" type="checkbox" name="' . esc_attr( $opt ) . '[enforce_gtin_mpn]" id="cs_mfb_enforce_gtin_mpn" value="1" ' . checked( 1, (int) $s['enforce_gtin_mpn'], false ) . ' />';
		echo '<span class="cs-mfb-toggle-track" aria-hidden="true"><span class="cs-mfb-toggle-thumb"></span></span>';
		echo '<span class="cs-mfb-toggle-text">' . esc_html__( 'Enable identifier enforcement', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</label><span class="cs-mfb-recommended-badge">' . esc_html__( 'Recommended', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span></div></div>';

		// Image checks
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_enable_image_checks">' . esc_html__( 'Image checks', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( "Validate that product images meet Google's requirements.", 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl cs-mfb-setting-ctrl--badges"><label class="cs-mfb-toggle-wrap">';
		echo '<input class="cs-mfb-toggle-cb" type="checkbox" name="' . esc_attr( $opt ) . '[enable_image_checks]" id="cs_mfb_enable_image_checks" value="1" ' . checked( 1, (int) $s['enable_image_checks'], false ) . ' />';
		echo '<span class="cs-mfb-toggle-track" aria-hidden="true"><span class="cs-mfb-toggle-thumb"></span></span>';
		echo '<span class="cs-mfb-toggle-text">' . esc_html__( 'Enable image quality checks', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</label><span class="cs-mfb-recommended-badge">' . esc_html__( 'Recommended', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span></div></div>';

		// Smart suggestions
		echo '<div class="cs-mfb-setting-row">';
		echo '<div class="cs-mfb-setting-info"><label class="cs-mfb-setting-name" for="cs_mfb_enable_smart_suggestions">' . esc_html__( 'Smart suggestions', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
		echo '<p class="cs-mfb-setting-desc">' . esc_html__( 'Show contextual fix hints alongside each issue in the Feed Health report.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '<div class="cs-mfb-setting-ctrl cs-mfb-setting-ctrl--badges"><label class="cs-mfb-toggle-wrap">';
		echo '<input class="cs-mfb-toggle-cb" type="checkbox" name="' . esc_attr( $opt ) . '[enable_smart_suggestions]" id="cs_mfb_enable_smart_suggestions" value="1" ' . checked( 1, (int) $s['enable_smart_suggestions'], false ) . ' />';
		echo '<span class="cs-mfb-toggle-track" aria-hidden="true"><span class="cs-mfb-toggle-thumb"></span></span>';
		echo '<span class="cs-mfb-toggle-text">' . esc_html__( 'Enable smart suggestions', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</label><span class="cs-mfb-recommended-badge">' . esc_html__( 'Recommended', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span></div></div>';

		echo '</div>'; // panel optimization

		// ── Save footer (sticky) ──────────────────────────────────────────
		echo '<div class="cs-mfb-settings-footer">';
		echo '<div class="cs-mfb-settings-footer-left">';
		echo '<span class="cs-mfb-unsaved-indicator" id="cs-mfb-unsaved-indicator" hidden>';
		echo '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
		echo esc_html__( 'You have unsaved changes', 'merchant-feed-booster-lite-for-woocommerce' );
		echo '</span>';
		echo '<span class="cs-mfb-saved-time" id="cs-mfb-saved-time" hidden>';
		echo '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
		echo '<span id="cs-mfb-saved-time-text"></span>';
		echo '</span>';
		echo '</div>';
		echo '<div class="cs-mfb-settings-footer-right">';
		echo '<button type="button" class="cs-mfb-btn cs-mfb-btn--ghost" id="cs-mfb-reset-settings"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.13"/></svg>' . esc_html__( 'Reset to Defaults', 'merchant-feed-booster-lite-for-woocommerce' ) . '</button>';
		echo '<button type="submit" class="cs-mfb-btn cs-mfb-btn--primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>' . esc_html__( 'Save Settings', 'merchant-feed-booster-lite-for-woocommerce' ) . '</button>';
		echo '</div>';
		echo '</div>'; // footer
		echo '</form>';
		echo '</div>'; // settings-main

		// ── Right: sidebar cards ──────────────────────────────────────────
		echo '<aside class="cs-mfb-settings-sidebar">';

		// Feed Summary card
		echo '<div class="cs-mfb-sidebar-card">';
		echo '<div class="cs-mfb-sidebar-card-hdr"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>' . esc_html__( 'FEED SUMMARY', 'merchant-feed-booster-lite-for-woocommerce' ) . '</div>';
		echo '<div class="cs-mfb-sidebar-row"><span class="cs-mfb-sidebar-row-label"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' . esc_html__( 'Feed Status', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		if ( $is_active ) {
			echo '<span class="cs-mfb-sidebar-badge cs-mfb-sidebar-badge--on">' . esc_html__( 'Active', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		} else {
			echo '<span class="cs-mfb-sidebar-badge cs-mfb-sidebar-badge--off">' . esc_html__( 'Paused', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		}
		echo '</div>';
		echo '<div class="cs-mfb-sidebar-row"><span class="cs-mfb-sidebar-row-label"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>' . esc_html__( 'Total Products', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-sidebar-row-val" style="color:var(--c-primary);font-size:16px;font-weight:800">' . (int) $total_products . '</span></div>';
		echo '<div class="cs-mfb-sidebar-row"><span class="cs-mfb-sidebar-row-label"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' . esc_html__( 'Last Generated', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-sidebar-row-val">' . ( $last_generated ? esc_html( $last_generated ) : '<span style="color:var(--c-slate-300)">—</span>' ) . '</span></div>';
		echo '<div class="cs-mfb-sidebar-row"><span class="cs-mfb-sidebar-row-label"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' . esc_html__( 'Next Refresh', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-sidebar-row-val" title="' . esc_attr( $next_refresh_full ) . '">' . ( $next_refresh ? esc_html( $next_refresh ) : '<span style="color:var(--c-slate-300)">—</span>' ) . '</span></div>';
		echo '<div class="cs-mfb-sidebar-row"><span class="cs-mfb-sidebar-row-label"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>' . esc_html__( 'Default Brand', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-sidebar-row-val">' . ( $s['default_brand'] ? esc_html( $s['default_brand'] ) : '<span style="color:var(--c-slate-300)">—</span>' ) . '</span></div>';
		if ( $s['default_google_category'] ) {
			echo '<div class="cs-mfb-sidebar-row"><span class="cs-mfb-sidebar-row-label"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>' . esc_html__( 'Default Category', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
			echo '<span class="cs-mfb-sidebar-row-val" style="text-align:right;max-width:140px;font-size:11.5px">' . esc_html( mb_strimwidth( $s['default_google_category'], 0, 40, '…' ) ) . '</span></div>';
		}
		if ( $feed_url ) {
			echo '<div class="cs-mfb-sidebar-link-row">';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PREVIEW_SLUG ) ) . '" class="cs-mfb-sidebar-link"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' . esc_html__( 'View Feed Preview', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
			echo '</div>';
		}
		echo '</div>'; // sidebar-card

		// Help & Resources card
		echo '<div class="cs-mfb-sidebar-card">';
		echo '<div class="cs-mfb-sidebar-card-hdr"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' . esc_html__( 'HELP & RESOURCES', 'merchant-feed-booster-lite-for-woocommerce' ) . '</div>';
		$help_links = array(
			__( 'How Google Shopping feeds work', 'merchant-feed-booster-lite-for-woocommerce' )   => 'https://support.google.com/merchants/answer/188493',
			__( 'Google product data guidelines', 'merchant-feed-booster-lite-for-woocommerce' )   => 'https://support.google.com/merchants/answer/6324475',
			__( 'Common feed issues', 'merchant-feed-booster-lite-for-woocommerce' )                => 'https://support.google.com/merchants/answer/7052112',
			__( 'Contact support', 'merchant-feed-booster-lite-for-woocommerce' )                  => 'https://wordpress.org/support/plugin/merchant-feed-booster/',
		);
		foreach ( $help_links as $link_label => $link_href ) {
			echo '<div class="cs-mfb-sidebar-help-row">';
			echo '<a href="' . esc_url( $link_href ) . '" target="_blank" rel="noopener noreferrer" class="cs-mfb-sidebar-help-link">';
			echo esc_html( $link_label );
			echo '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
			echo '</a>';
			echo '</div>';
		}
		echo '</div>'; // sidebar-card

		// Tips card
		echo '<div class="cs-mfb-sidebar-card">';
		echo '<div class="cs-mfb-sidebar-card-hdr"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' . esc_html__( 'TIPS', 'merchant-feed-booster-lite-for-woocommerce' ) . '</div>';
		echo '<div style="padding:12px 18px 6px"><p style="font-size:12.5px;color:var(--c-slate-600);margin:0 0 10px;line-height:1.55">' . esc_html__( 'Keep your feed updated regularly for the best performance.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		echo '<a href="https://support.google.com/merchants/answer/188490" target="_blank" rel="noopener noreferrer" class="cs-mfb-sidebar-link">' . esc_html__( 'Learn best practices', 'merchant-feed-booster-lite-for-woocommerce' ) . ' &rarr;</a></div>';
		echo '</div>'; // sidebar-card

		echo '</aside>';
		echo '</div>'; // settings-main-layout
		echo '</div>'; // .wrap
	}

	/**
	 * Render a small circular score gauge for table cells.
	 * Re-uses .cs-mfb-gauge-fill so animateGauges() animates it on load.
	 *
	 * @param int    $score 0-100.
	 * @param string $color Hex/CSS color for the ring stroke and number.
	 * @return string HTML.
	 */
	protected static function render_score_gauge_mini( $score, $color ) {
		$score = max( 0, min( 100, (int) $score ) );
		return sprintf(
			'<div class="cs-mfb-mini-gauge" data-cs-score="%1$d">
				<svg class="cs-mfb-mini-gauge-svg" viewBox="0 0 36 36" aria-hidden="true">
					<path class="cs-mfb-mini-gauge-track" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
					<path class="cs-mfb-gauge-fill" stroke-dasharray="0,100" style="stroke:%2$s"
						d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
				</svg>
				<span class="cs-mfb-mini-gauge-num" style="color:%2$s">%1$d</span>
			</div>',
			$score,
			esc_attr( $color )
		);
	}

	/**
	 * Map rule IDs to human-readable chip labels.
	 *
	 * @return array
	 */
	protected static function rule_chip_labels() {
		return array(
			'T01' => 'Title too short',    'T02' => 'Title too long',
			'T03' => 'Promo in title',     'T04' => 'ALL CAPS title',
			'T05' => 'HTML in title',      'T06' => 'Price in title',
			'T07' => 'Special chars',      'T08' => 'Repeated words',
			'I01' => 'Missing image',      'I02' => 'Image too small',
			'I03' => 'Low-res image',      'I04' => 'Bad image format',
			'I05' => 'Tracking URL',
			'P01' => 'Missing price',      'P02' => 'Sale price issue',
			'P03' => 'Sale ≥ regular',     'P04' => 'Invalid price',
			'ID01' => 'Wrong GTIN length', 'ID02' => 'Invalid GTIN',
			'ID03' => 'Missing identifiers','ID04' => 'Missing brand',
			'D01' => 'Missing description','D02' => 'Desc too short',
			'D03' => 'HTML in desc',       'D04' => 'Promo in desc',
		);
	}

	/**
	 * Render issue chips HTML for a product row.
	 *
	 * @param array $issues Issue list from policy engine.
	 * @param int   $max    Max chips to show before "+N more".
	 * @return string HTML.
	 */
	protected static function render_issue_chips( array $issues, $max = 2 ) {
		if ( empty( $issues ) ) {
			return '<span class="cs-mfb-ok-chip">&#10003; No issues</span>';
		}
		$labels = self::rule_chip_labels();
		usort( $issues, static function ( $a, $b ) {
			$r = array( 'error' => 3, 'warning' => 2, 'notice' => 1 );
			return ( $r[ $b['severity'] ] ?? 0 ) - ( $r[ $a['severity'] ] ?? 0 );
		} );
		$html  = '';
		$shown = 0;
		foreach ( $issues as $issue ) {
			if ( $shown >= $max ) break;
			$cls   = 'cs-mfb-issue-chip cs-mfb-issue-chip--' . esc_attr( $issue['severity'] );
			$label = $labels[ $issue['rule_id'] ] ?? $issue['rule_id'];
			$html .= '<span class="' . $cls . '">' . esc_html( $label ) . '</span>';
			$shown++;
		}
		$remaining = count( $issues ) - $shown;
		if ( $remaining > 0 ) {
			$html .= '<span class="cs-mfb-issue-chip cs-mfb-issue-chip--more">+' . (int) $remaining . '</span>';
		}
		return $html;
	}

	/**
	 * Return a priority badge HTML based on score.
	 *
	 * @param int $score 0–100.
	 * @return string HTML.
	 */
	protected static function priority_badge( $score ) {
		if ( $score < 50 ) {
			return '<span class="cs-mfb-priority cs-mfb-priority--high">' . esc_html__( 'High', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		}
		if ( $score < 70 ) {
			return '<span class="cs-mfb-priority cs-mfb-priority--medium">' . esc_html__( 'Medium', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		}
		return '<span class="cs-mfb-priority cs-mfb-priority--low">' . esc_html__( 'Low', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
	}

	/**
	 * Render the Feed Health page.
	 */
	public static function render_health() {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$msg            = isset( $_GET['cs_mfb_msg'] ) ? sanitize_key( wp_unslash( $_GET['cs_mfb_msg'] ) ) : '';
		$current_filter = isset( $_GET['cs_mfb_filter'] ) ? sanitize_key( wp_unslash( $_GET['cs_mfb_filter'] ) ) : '';
		$issues_only    = ! empty( $_GET['cs_mfb_issues_only'] );
		$current_page   = isset( $_GET['paged'] ) ? max( 1, (int) wp_unslash( $_GET['paged'] ) ) : 1;
		$per_page_raw   = isset( $_GET['per_page'] ) ? (int) wp_unslash( $_GET['per_page'] ) : 25;
		// phpcs:enable
		$per_page = in_array( $per_page_raw, array( 10, 25, 50, 100 ), true ) ? $per_page_raw : 25;

		echo '<div class="wrap cs-mfb-page">';

		if ( $msg ) {
			self::render_admin_notice( $msg );
		}

		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce is not active. Activate WooCommerce to scan products.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div></div>';
			return;
		}

		$report    = CodeSolz_MFB_Health::scan( array(
			'page'              => $current_page,
			'per_page'          => $per_page,
			'issue_type_filter' => $current_filter,
			'issues_only'       => $issues_only,
		) );
		$totals    = $report['totals'];
		$avg_score = $report['avg_score'];
		$avg_tier  = CodeSolz_MFB_Health_Score::get_tier( $avg_score );
		$avg_color = CodeSolz_MFB_Health_Score::get_tier_color( $avg_tier );
		$avg_label = CodeSolz_MFB_Health_Score::get_tier_label( $avg_tier );

		// ── Page header card ──────────────────────────────────────────────
		echo '<div class="cs-mfb-page-hdr">';
		echo '<div class="cs-mfb-page-hdr-top">';
		echo '<div class="cs-mfb-page-hdr-left">';
		echo '<div class="cs-mfb-page-hdr-icon" style="background:linear-gradient(135deg,#ecfdf5,#d1fae5)">';
		echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
		echo '</div>';
		echo '<div><h1 class="cs-mfb-page-hdr-title">' . esc_html__( 'Feed Health', 'merchant-feed-booster-lite-for-woocommerce' ) . '</h1>';
		echo '<p class="cs-mfb-page-hdr-sub">' . esc_html__( 'Monitor your Google Shopping feed quality and fix product issues to improve performance and visibility.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '</div>';
		if ( (int) $totals['scanned'] > 0 ) {
			echo '<span class="cs-mfb-hdr-status-badge"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' . esc_html__( 'Scan complete', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		}
		echo '</div>';
		// Stats + actions row
		echo '<div class="cs-mfb-page-hdr-bottom">';
		echo '<div class="cs-mfb-stat-chips">';
		if ( $avg_score > 0 ) {
			printf( '<span class="cs-mfb-stat-chip cs-mfb-stat-chip--score" style="color:%s">&#9733; %d %s</span>', esc_attr( $avg_color ), (int) $avg_score, esc_html__( 'avg score', 'merchant-feed-booster-lite-for-woocommerce' ) );
		}
		printf( '<span class="cs-mfb-stat-chip cs-mfb-stat-chip--error">&#10007; %d %s</span>', (int) $totals['errors'], esc_html__( 'errors', 'merchant-feed-booster-lite-for-woocommerce' ) );
		printf( '<span class="cs-mfb-stat-chip cs-mfb-stat-chip--warning">&#9651; %d %s</span>', (int) $totals['warnings'], esc_html__( 'warnings', 'merchant-feed-booster-lite-for-woocommerce' ) );
		printf( '<span class="cs-mfb-stat-chip cs-mfb-stat-chip--scan">&#8597; %d %s</span>', (int) $totals['scanned'], esc_html__( 'scanned', 'merchant-feed-booster-lite-for-woocommerce' ) );
		echo '</div>';
		echo '<div class="cs-mfb-page-hdr-btns">';
		echo '<button type="button" class="cs-mfb-btn cs-mfb-btn--primary cs-mfb-btn--sm" data-cs-action="rescan"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>' . esc_html__( 'Re-scan Products', 'merchant-feed-booster-lite-for-woocommerce' ) . '</button>';
		echo '<a class="cs-mfb-btn cs-mfb-btn--secondary cs-mfb-btn--sm" href="' . esc_url( self::csv_export_url() ) . '">' . esc_html__( 'Export CSV', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
		echo '<button type="button" class="cs-mfb-btn cs-mfb-btn--ghost cs-mfb-btn--sm" data-cs-action="clear-cache">' . esc_html__( 'Clear Cache', 'merchant-feed-booster-lite-for-woocommerce' ) . '</button>';
		echo '</div>';
		echo '</div>'; // hdr-bottom
		echo '</div>'; // page-hdr

		// ── Metric cards ──────────────────────────────────────────────────
		echo '<div class="cs-mfb-metric-cards">';

		// Overall Score card
		echo '<div class="cs-mfb-mcard cs-mfb-mcard--score">';
		echo '<div class="cs-mfb-mcard-hdr">';
		echo '<div class="cs-mfb-mcard-icon" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe)"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg></div>';
		echo '<p class="cs-mfb-mcard-label">' . esc_html__( 'Overall Score', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		echo '</div>';
		if ( $avg_score > 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::render_score_gauge_html( $avg_score, $avg_color, $avg_label, 'md' );
			echo '<p class="cs-mfb-mcard-desc">' . esc_html( CodeSolz_MFB_Health_Score::get_tier_description( $avg_tier ) ) . '</p>';
		} else {
			echo '<div class="cs-mfb-mcard-num" style="color:var(--c-slate-300)">—</div>';
			echo '<p class="cs-mfb-mcard-desc">' . esc_html__( 'Run a scan to see your score', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		}
		echo '</div>';

		// Errors card
		printf(
			'<div class="cs-mfb-mcard cs-mfb-mcard--error"><div class="cs-mfb-mcard-hdr"><div class="cs-mfb-mcard-icon" style="background:#fef2f2"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><p class="cs-mfb-mcard-label">%s</p></div><div class="cs-mfb-mcard-num">%d</div><p class="cs-mfb-mcard-desc">%s</p></div>',
			esc_html__( 'Errors', 'merchant-feed-booster-lite-for-woocommerce' ),
			(int) $totals['errors'],
			esc_html__( 'Issues that must be fixed', 'merchant-feed-booster-lite-for-woocommerce' )
		);

		// Warnings card
		printf(
			'<div class="cs-mfb-mcard cs-mfb-mcard--warning"><div class="cs-mfb-mcard-hdr"><div class="cs-mfb-mcard-icon" style="background:#fffbeb"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><p class="cs-mfb-mcard-label">%s</p></div><div class="cs-mfb-mcard-num">%d</div><p class="cs-mfb-mcard-desc">%s</p></div>',
			esc_html__( 'Warnings', 'merchant-feed-booster-lite-for-woocommerce' ),
			(int) $totals['warnings'],
			esc_html__( 'Things to review but not critical', 'merchant-feed-booster-lite-for-woocommerce' )
		);

		// Notices card
		printf(
			'<div class="cs-mfb-mcard cs-mfb-mcard--notice"><div class="cs-mfb-mcard-hdr"><div class="cs-mfb-mcard-icon" style="background:#eff6ff"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><p class="cs-mfb-mcard-label">%s</p></div><div class="cs-mfb-mcard-num">%d</div><p class="cs-mfb-mcard-desc">%s</p></div>',
			esc_html__( 'Notices', 'merchant-feed-booster-lite-for-woocommerce' ),
			(int) $totals['notices'],
			esc_html__( 'Informational notifications', 'merchant-feed-booster-lite-for-woocommerce' )
		);

		// Scanned card
		printf(
			'<div class="cs-mfb-mcard cs-mfb-mcard--scanned"><div class="cs-mfb-mcard-hdr"><div class="cs-mfb-mcard-icon" style="background:#f5f3ff"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div><p class="cs-mfb-mcard-label">%s</p></div><div class="cs-mfb-mcard-num">%d</div><p class="cs-mfb-mcard-desc">%s</p></div>',
			esc_html__( 'Scanned', 'merchant-feed-booster-lite-for-woocommerce' ),
			(int) $totals['scanned'],
			esc_html__( 'Products scanned in this run', 'merchant-feed-booster-lite-for-woocommerce' )
		);

		echo '</div>'; // metric-cards

		// ── Scan progress bar ─────────────────────────────────────────────
		echo '<div id="cs-mfb-scan-progress" class="cs-mfb-scan-progress" style="display:none">';
		echo '<div class="cs-mfb-progress-bar"><div id="cs-mfb-progress-fill" class="cs-mfb-progress-fill" style="width:0%"></div></div>';
		echo '<p id="cs-mfb-progress-label" class="cs-mfb-progress-label">' . esc_html__( 'Starting scan…', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
		echo '</div>';

		// ── Filter tabs ───────────────────────────────────────────────────
		$base_url    = admin_url( 'admin.php?page=' . self::HEALTH_SLUG );
		$filter_icons = array(
			''            => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
			'issues_only' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
			'title'       => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>',
			'image'       => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
			'price'       => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
			'identifier'  => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
			'description' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
		);
		$filters = array(
			''            => __( 'All Products', 'merchant-feed-booster-lite-for-woocommerce' ),
			'issues_only' => __( 'Issues Only',  'merchant-feed-booster-lite-for-woocommerce' ),
			'title'       => __( 'Title',        'merchant-feed-booster-lite-for-woocommerce' ),
			'image'       => __( 'Image',        'merchant-feed-booster-lite-for-woocommerce' ),
			'price'       => __( 'Price',        'merchant-feed-booster-lite-for-woocommerce' ),
			'identifier'  => __( 'Identifiers',  'merchant-feed-booster-lite-for-woocommerce' ),
			'description' => __( 'Description',  'merchant-feed-booster-lite-for-woocommerce' ),
		);

		echo '<div class="cs-mfb-filter-tabs" role="tablist">';
		foreach ( $filters as $fkey => $flabel ) {
			if ( 'issues_only' === $fkey ) {
				$tab_url   = add_query_arg( array( 'cs_mfb_issues_only' => '1', 'cs_mfb_filter' => '', 'per_page' => $per_page ), $base_url );
				$is_active = $issues_only && '' === $current_filter;
			} else {
				$tab_url   = add_query_arg( array( 'cs_mfb_filter' => $fkey, 'cs_mfb_issues_only' => '', 'per_page' => $per_page ), $base_url );
				$is_active = ! $issues_only && $current_filter === $fkey;
			}
			printf(
				'<a href="%s" class="cs-mfb-filter-tab%s" role="tab" aria-selected="%s">%s %s</a>',
				esc_url( $tab_url ),
				$is_active ? ' cs-mfb-filter-tab--active' : '',
				$is_active ? 'true' : 'false',
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$filter_icons[ $fkey ] ?? '',
				esc_html( $flabel )
			);
		}
		echo '</div>';

		/**
		 * Fires right before the health table (and any empty-state message),
		 * after the filter tabs. Pro uses this to render a bulk-actions toolbar.
		 *
		 * @param array $report Full scan report for the current page/filter.
		 */
		do_action( 'cs_mfb_health_table_before', $report );

		// ── Products table ────────────────────────────────────────────────
		if ( empty( $report['rows'] ) ) {
			echo '<div class="cs-mfb-empty">' . esc_html__( 'No products match this filter. Run a scan first or try a different filter.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</div>';
		} else {
			echo '<div class="cs-mfb-table-wrap"><table class="widefat cs-mfb-health-table">';
			echo '<thead><tr>';
			/**
			 * Fires as the first cell of the table header row. Pro uses this
			 * to add a "select all" checkbox column for bulk actions.
			 */
			do_action( 'cs_mfb_health_table_head' );
			echo '<th class="col-product">' . esc_html__( 'Product', 'merchant-feed-booster-lite-for-woocommerce' ) . '</th>';
			echo '<th class="col-score">' . esc_html__( 'Score', 'merchant-feed-booster-lite-for-woocommerce' ) . '</th>';
			echo '<th class="col-issues">' . esc_html__( 'Feed Status / Issues', 'merchant-feed-booster-lite-for-woocommerce' ) . '</th>';
			echo '<th class="col-sku">' . esc_html__( 'SKU', 'merchant-feed-booster-lite-for-woocommerce' ) . '</th>';
			echo '<th class="col-priority">' . esc_html__( 'Priority', 'merchant-feed-booster-lite-for-woocommerce' ) . '</th>';
			echo '<th class="col-actions">' . esc_html__( 'Actions', 'merchant-feed-booster-lite-for-woocommerce' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $report['rows'] as $row ) {
				$score      = (int) $row['score'];
				$tier_color = CodeSolz_MFB_Health_Score::get_tier_color( $row['tier'] );
				$issues     = $row['issues'];
				$detail_id  = 'cs-mfb-row-' . (int) $row['id'];
				$thumb_url  = get_the_post_thumbnail_url( (int) $row['id'], array( 40, 40 ) );

				echo '<tr class="cs-mfb-product-row">';

				/**
				 * Fires as the first cell of each product row, immediately after
				 * the row is opened. Pro uses this to add a bulk-select checkbox.
				 *
				 * @param array $row Row data (id, title, sku, score, tier, issues, edit, link).
				 */
				do_action( 'cs_mfb_health_table_row_start', $row );

				// Product column: thumbnail + name
				echo '<td class="col-product">';
				echo '<div class="cs-mfb-product-cell">';
				if ( $thumb_url ) {
					echo '<img src="' . esc_url( $thumb_url ) . '" class="cs-mfb-thumb-sm" width="36" height="36" loading="lazy" alt="" />';
				} else {
					echo '<div class="cs-mfb-thumb-sm cs-mfb-thumb-sm--empty"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>';
				}
				echo '<strong class="cs-mfb-product-name"><a href="' . esc_url( (string) $row['edit'] ) . '">' . esc_html( $row['title'] ) . '</a></strong>';
				echo '</div>';
				echo '</td>';

				// Score column: mini circular gauge
				echo '<td class="col-score">';
				echo self::render_score_gauge_mini( $score, $tier_color );
				echo '</td>';

				// Issues chips column
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td class="col-issues">' . self::render_issue_chips( $issues ) . '</td>';

				// SKU
				echo '<td class="col-sku"><span class="cs-mfb-sku-text">' . esc_html( $row['sku'] ?: '—' ) . '</span></td>';

				// Priority badge
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td class="col-priority">' . ( ! empty( $issues ) ? self::priority_badge( $score ) : '<span class="cs-mfb-priority cs-mfb-priority--ok">&#10003;</span>' ) . '</td>';

				// Actions
				echo '<td class="col-actions">';
				if ( ! empty( $issues ) ) {
					printf(
						'<button type="button" class="cs-mfb-btn cs-mfb-btn--secondary cs-mfb-btn--sm cs-mfb-toggle-row" aria-expanded="false" aria-controls="%s">%s</button>',
						esc_attr( $detail_id ),
						esc_html__( 'Details', 'merchant-feed-booster-lite-for-woocommerce' )
					);
				}
				echo '<a class="cs-mfb-btn cs-mfb-btn--ghost cs-mfb-btn--sm" href="' . esc_url( (string) $row['edit'] ) . '">' . esc_html__( 'Edit', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
				echo '</td>';
				echo '</tr>';

				if ( ! empty( $issues ) ) {
					echo '<tr id="' . esc_attr( $detail_id ) . '" class="cs-mfb-detail-row" hidden>';
					echo '<td colspan="' . (int) apply_filters( 'cs_mfb_health_table_colspan', 6 ) . '"><ul class="cs-mfb-issue-list">';
					foreach ( $issues as $issue ) {
						printf(
							'<li class="cs-mfb-issue-item"><span class="cs-mfb-sev cs-mfb-sev--%s">%s</span> <strong>%s</strong> — %s <em class="cs-mfb-fix-hint">%s %s</em></li>',
							esc_attr( $issue['severity'] ),
							esc_html( strtoupper( $issue['severity'] ) ),
							esc_html( $issue['rule_id'] ),
							esc_html( $issue['message'] ),
							esc_html__( 'Fix:', 'merchant-feed-booster-lite-for-woocommerce' ),
							esc_html( $issue['fix_hint'] )
						);
					}
					echo '</ul></td></tr>';
				}
			}

			echo '</tbody></table></div>';

			// ── Table footer: rows-per-page + pagination ───────────────────
			$total_pages  = (int) ceil( $report['total_rows'] / $per_page );
			$showing_from = ( ( $current_page - 1 ) * $per_page ) + 1;
			$showing_to   = min( $current_page * $per_page, (int) $report['total_rows'] );
			$total_rows   = (int) $report['total_rows'];

			echo '<div class="cs-mfb-table-footer">';
			printf( '<span class="cs-mfb-table-showing">%s</span>', esc_html( sprintf(
				/* translators: 1: start, 2: end, 3: total */
				__( 'Showing %1$d–%2$d of %3$d products', 'merchant-feed-booster-lite-for-woocommerce' ),
				$showing_from, $showing_to, $total_rows
			) ) );
			echo '<div class="cs-mfb-table-footer-right">';

			// Rows per page selector
			echo '<div class="cs-mfb-rows-per-page">';
			echo '<label for="cs-mfb-per-page">' . esc_html__( 'Rows per page:', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
			echo '<select id="cs-mfb-per-page" onchange="window.location=this.value" class="cs-mfb-select-sm">';
			foreach ( array( 10, 25, 50, 100 ) as $opt_val ) {
				$url = esc_url( add_query_arg( array( 'per_page' => $opt_val, 'paged' => 1 ) ) );
				printf( '<option value="%s"%s>%d</option>', $url, selected( $per_page, $opt_val, false ), $opt_val );
			}
			echo '</select>';
			echo '</div>';

			// Pagination
			if ( $total_pages > 1 ) {
				echo '<nav class="cs-mfb-pagination-nav">';
				$first_url = add_query_arg( 'paged', 1 );
				$last_url  = add_query_arg( 'paged', $total_pages );
				$prev_url  = $current_page > 1 ? add_query_arg( 'paged', $current_page - 1 ) : null;
				$next_url  = $current_page < $total_pages ? add_query_arg( 'paged', $current_page + 1 ) : null;
				printf( '<a href="%s" class="cs-mfb-pager-btn%s" title="%s">&laquo;</a>', esc_url( $first_url ), $current_page <= 1 ? ' disabled' : '', esc_attr__( 'First', 'merchant-feed-booster-lite-for-woocommerce' ) );
				printf( '<a href="%s" class="cs-mfb-pager-btn%s" title="%s">&lsaquo;</a>', $prev_url ? esc_url( $prev_url ) : '#', ! $prev_url ? ' disabled' : '', esc_attr__( 'Previous', 'merchant-feed-booster-lite-for-woocommerce' ) );
				printf( '<span class="cs-mfb-pager-current">%d</span>', $current_page );
				printf( '<a href="%s" class="cs-mfb-pager-btn%s" title="%s">&rsaquo;</a>', $next_url ? esc_url( $next_url ) : '#', ! $next_url ? ' disabled' : '', esc_attr__( 'Next', 'merchant-feed-booster-lite-for-woocommerce' ) );
				printf( '<a href="%s" class="cs-mfb-pager-btn%s" title="%s">&raquo;</a>', esc_url( $last_url ), $current_page >= $total_pages ? ' disabled' : '', esc_attr__( 'Last', 'merchant-feed-booster-lite-for-woocommerce' ) );
				echo '</nav>';
			}

			echo '</div>'; // footer-right
			echo '</div>'; // table-footer

			if ( $report['truncated'] ) {
				echo '<p class="cs-mfb-truncated-note">' . esc_html__( 'Large catalog: showing first 5,000 products. Use CSV export for the full list.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p>';
			}
		}

		do_action( 'cs_mfb_after_health_table' );

		echo '</div>'; // .wrap
	}

	/**
	 * Return render_score_gauge() output as a string (for embedding inside other output).
	 *
	 * @param int    $score 0–100.
	 * @param string $color Hex colour.
	 * @param string $label Tier label.
	 * @param string $size  lg|md|sm.
	 * @return string HTML.
	 */
	protected static function render_score_gauge_html( $score, $color, $label, $size = 'md' ) {
		ob_start();
		self::render_score_gauge( $score, $color, $label, $size );
		return ob_get_clean();
	}

	/**
	 * Render the Feed Preview page — paginated, with per-cell issue highlights.
	 */
	public static function render_preview() {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_GET['paged'] ) ? max( 1, (int) wp_unslash( $_GET['paged'] ) ) : 1;
		$per_page_raw = isset( $_GET['per_page'] ) ? (int) wp_unslash( $_GET['per_page'] ) : 20;
		// phpcs:enable
		$per_page = in_array( $per_page_raw, array( 10, 20, 50 ), true ) ? $per_page_raw : 20;

		$feed_url = CodeSolz_MFB_Feed_Generator::public_feed_url();

		echo '<div class="wrap cs-mfb-page">';

		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce is not active.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div></div>';
			return;
		}

		$feed_path = CodeSolz_MFB_Feed_Generator::feed_file_path();
		if ( ! $feed_path || ! file_exists( $feed_path ) ) {
			// No-feed empty state with header card
			echo '<div class="cs-mfb-page-hdr">';
			echo '<div class="cs-mfb-page-hdr-top"><div class="cs-mfb-page-hdr-left">';
			echo '<div class="cs-mfb-page-hdr-icon" style="background:linear-gradient(135deg,#eff6ff,#dbeafe)"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>';
			echo '<div><h1 class="cs-mfb-page-hdr-title">' . esc_html__( 'Feed Preview', 'merchant-feed-booster-lite-for-woocommerce' ) . '</h1><p class="cs-mfb-page-hdr-sub">' . esc_html__( 'No feed has been generated yet.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
			echo '</div>';
			echo '<div class="cs-mfb-page-hdr-btns"><button type="button" class="cs-mfb-btn cs-mfb-btn--primary cs-mfb-btn--sm" data-cs-action="regenerate" data-cs-reload-after="1">' . esc_html__( 'Generate Feed Now', 'merchant-feed-booster-lite-for-woocommerce' ) . '</button></div>';
			echo '</div></div>';
			echo '<div class="cs-mfb-empty"><p style="font-size:36px;margin:0 0 10px">&#128722;</p><p style="font-size:15px;font-weight:700;color:var(--c-slate-700);margin:0 0 8px">' . esc_html__( 'No feed generated yet', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p><p style="margin:0 0 20px">' . esc_html__( 'Generate your feed first to see it previewed here with issue highlights.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p><button type="button" class="cs-mfb-btn cs-mfb-btn--primary" data-cs-action="regenerate" data-cs-reload-after="1">' . esc_html__( 'Generate Feed Now', 'merchant-feed-booster-lite-for-woocommerce' ) . '</button></div></div>';
			return;
		}

		$all_items   = CodeSolz_MFB_Feed_Preview::get_items();
		$all_items   = CodeSolz_MFB_Feed_Preview::enrich_with_issues( $all_items );
		$total_items = count( $all_items );
		$total_pages = (int) ceil( $total_items / $per_page );
		$offset      = ( $current_page - 1 ) * $per_page;
		$items       = array_slice( $all_items, $offset, $per_page );

		// Count issues across all items
		$preview_errors   = 0;
		$preview_warnings = 0;
		$preview_notices  = 0;
		foreach ( $all_items as $itm ) {
			foreach ( $itm['issues'] ?? array() as $iss ) {
				if ( 'error'   === $iss['severity'] ) $preview_errors++;
				elseif ( 'warning' === $iss['severity'] ) $preview_warnings++;
				else $preview_notices++;
			}
		}

		// ── Page header card ──────────────────────────────────────────────
		echo '<div class="cs-mfb-page-hdr">';
		echo '<div class="cs-mfb-page-hdr-top">';
		echo '<div class="cs-mfb-page-hdr-left">';
		echo '<div class="cs-mfb-page-hdr-icon" style="background:linear-gradient(135deg,#eff6ff,#dbeafe)">';
		echo '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
		echo '</div>';
		echo '<div><h1 class="cs-mfb-page-hdr-title">' . esc_html__( 'Feed Preview', 'merchant-feed-booster-lite-for-woocommerce' ) . '</h1>';
		echo '<p class="cs-mfb-page-hdr-sub">' . esc_html__( 'See exactly what Google receives — every field, every issue highlighted inline.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</p></div>';
		echo '</div>';
		echo '<span class="cs-mfb-hdr-status-badge"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' . esc_html__( 'Feed ready', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';
		// Stats + actions row
		echo '<div class="cs-mfb-page-hdr-bottom">';
		echo '<div class="cs-mfb-stat-chips">';
		printf( '<span class="cs-mfb-stat-chip cs-mfb-stat-chip--scan">&#128270; %d %s</span>', $total_items, esc_html__( 'items in feed', 'merchant-feed-booster-lite-for-woocommerce' ) );
		if ( $preview_errors )   printf( '<span class="cs-mfb-stat-chip cs-mfb-stat-chip--error">&#10007; %d %s</span>', $preview_errors, esc_html__( 'errors', 'merchant-feed-booster-lite-for-woocommerce' ) );
		if ( $preview_warnings ) printf( '<span class="cs-mfb-stat-chip cs-mfb-stat-chip--warning">&#9651; %d %s</span>', $preview_warnings, esc_html__( 'warnings', 'merchant-feed-booster-lite-for-woocommerce' ) );
		echo '</div>';
		echo '<div class="cs-mfb-page-hdr-btns">';
		echo '<button type="button" class="cs-mfb-btn cs-mfb-btn--primary cs-mfb-btn--sm" data-cs-action="regenerate" data-cs-reload-after="1"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>' . esc_html__( 'Regenerate Feed', 'merchant-feed-booster-lite-for-woocommerce' ) . '</button>';
		echo '<a class="cs-mfb-btn cs-mfb-btn--secondary cs-mfb-btn--sm" href="' . esc_url( admin_url( 'admin.php?page=' . self::HEALTH_SLUG ) ) . '">' . esc_html__( 'Health Report', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
		if ( $feed_url ) {
			echo '<a class="cs-mfb-btn cs-mfb-btn--ghost cs-mfb-btn--sm" href="' . esc_url( $feed_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open XML ↗', 'merchant-feed-booster-lite-for-woocommerce' ) . '</a>';
		}
		echo '</div>';
		echo '</div>'; // hdr-bottom
		echo '</div>'; // page-hdr

		// ── Metric cards ──────────────────────────────────────────────────
		echo '<div class="cs-mfb-metric-cards">';

		printf(
			'<div class="cs-mfb-mcard cs-mfb-mcard--scanned"><div class="cs-mfb-mcard-hdr"><div class="cs-mfb-mcard-icon" style="background:#f5f3ff"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></div><p class="cs-mfb-mcard-label">%s</p></div><div class="cs-mfb-mcard-num">%d</div><p class="cs-mfb-mcard-desc">%s</p></div>',
			esc_html__( 'Items in Feed', 'merchant-feed-booster-lite-for-woocommerce' ),
			$total_items,
			esc_html__( 'Products sent to Google', 'merchant-feed-booster-lite-for-woocommerce' )
		);
		printf(
			'<div class="cs-mfb-mcard cs-mfb-mcard--error"><div class="cs-mfb-mcard-hdr"><div class="cs-mfb-mcard-icon" style="background:#fef2f2"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><p class="cs-mfb-mcard-label">%s</p></div><div class="cs-mfb-mcard-num">%d</div><p class="cs-mfb-mcard-desc">%s</p></div>',
			esc_html__( 'Error Fields', 'merchant-feed-booster-lite-for-woocommerce' ),
			$preview_errors,
			esc_html__( 'Field errors across all items', 'merchant-feed-booster-lite-for-woocommerce' )
		);
		printf(
			'<div class="cs-mfb-mcard cs-mfb-mcard--warning"><div class="cs-mfb-mcard-hdr"><div class="cs-mfb-mcard-icon" style="background:#fffbeb"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div><p class="cs-mfb-mcard-label">%s</p></div><div class="cs-mfb-mcard-num">%d</div><p class="cs-mfb-mcard-desc">%s</p></div>',
			esc_html__( 'Warning Fields', 'merchant-feed-booster-lite-for-woocommerce' ),
			$preview_warnings,
			esc_html__( 'Field warnings across all items', 'merchant-feed-booster-lite-for-woocommerce' )
		);
		printf(
			'<div class="cs-mfb-mcard cs-mfb-mcard--notice"><div class="cs-mfb-mcard-hdr"><div class="cs-mfb-mcard-icon" style="background:#eff6ff"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div><p class="cs-mfb-mcard-label">%s</p></div><div class="cs-mfb-mcard-num">%d</div><p class="cs-mfb-mcard-desc">%s</p></div>',
			esc_html__( 'Notice Fields', 'merchant-feed-booster-lite-for-woocommerce' ),
			$preview_notices,
			esc_html__( 'Informational field notices', 'merchant-feed-booster-lite-for-woocommerce' )
		);

		echo '</div>'; // metric-cards

		// ── Legend ────────────────────────────────────────────────────────
		echo '<div class="cs-mfb-legend-row">';
		echo '<span class="cs-mfb-legend-label">' . esc_html__( 'Cell highlight key:', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-legend-item"><span class="cs-mfb-legend-dot cs-mfb-legend-dot--error"></span>' . esc_html__( 'Error', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-legend-item"><span class="cs-mfb-legend-dot cs-mfb-legend-dot--warning"></span>' . esc_html__( 'Warning', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '<span class="cs-mfb-legend-item"><span class="cs-mfb-legend-dot cs-mfb-legend-dot--notice"></span>' . esc_html__( 'Notice', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
		echo '</div>';

		if ( empty( $items ) ) {
			echo '<div class="cs-mfb-empty">' . esc_html__( 'No items found in the feed.', 'merchant-feed-booster-lite-for-woocommerce' ) . '</div>';
		} else {
			$columns = array(
				'id'           => __( 'ID/SKU',     'merchant-feed-booster-lite-for-woocommerce' ),
				'title'        => __( 'Title',       'merchant-feed-booster-lite-for-woocommerce' ),
				'image_link'   => __( 'Image',       'merchant-feed-booster-lite-for-woocommerce' ),
				'price'        => __( 'Price',       'merchant-feed-booster-lite-for-woocommerce' ),
				'sale_price'   => __( 'Sale',        'merchant-feed-booster-lite-for-woocommerce' ),
				'brand'        => __( 'Brand',       'merchant-feed-booster-lite-for-woocommerce' ),
				'gtin'         => __( 'GTIN',        'merchant-feed-booster-lite-for-woocommerce' ),
				'mpn'          => __( 'MPN',         'merchant-feed-booster-lite-for-woocommerce' ),
				'availability' => __( 'Avail.',      'merchant-feed-booster-lite-for-woocommerce' ),
				'condition'    => __( 'Cond.',       'merchant-feed-booster-lite-for-woocommerce' ),
				'description'  => __( 'Description', 'merchant-feed-booster-lite-for-woocommerce' ),
			);

			echo '<div class="cs-mfb-table-wrap cs-mfb-preview-wrap"><table class="widefat cs-mfb-health-table cs-mfb-preview-table"><thead><tr>';
			echo '<th class="col-score">' . esc_html__( 'Score', 'merchant-feed-booster-lite-for-woocommerce' ) . '</th>';
			foreach ( $columns as $col_label ) {
				echo '<th>' . esc_html( $col_label ) . '</th>';
			}
			echo '</tr></thead><tbody>';

			$sev_rank = array( 'error' => 3, 'warning' => 2, 'notice' => 1 );

			foreach ( $items as $item ) {
				$field_sev = array();
				if ( ! empty( $item['issues'] ) ) {
					foreach ( $item['issues'] as $issue ) {
						foreach ( CodeSolz_MFB_Feed_Preview::rule_to_fields( $issue['rule_id'] ) as $fld ) {
							$cur_rank = isset( $field_sev[ $fld ] ) ? ( $sev_rank[ $field_sev[ $fld ] ] ?? 0 ) : 0;
							$new_rank = $sev_rank[ $issue['severity'] ] ?? 0;
							if ( $new_rank > $cur_rank ) {
								$field_sev[ $fld ] = $issue['severity'];
							}
						}
					}
				}

				$score = isset( $item['score'] ) && null !== $item['score'] ? (int) $item['score'] : null;
				$tier  = null !== $score ? CodeSolz_MFB_Health_Score::get_tier( $score ) : null;
				$color = null !== $tier ? CodeSolz_MFB_Health_Score::get_tier_color( $tier ) : '#94a3b8';

				echo '<tr>';
				echo '<td class="col-score">';
				if ( null !== $score ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML returned by render_score_gauge_mini() is internally escaped.
					echo self::render_score_gauge_mini( $score, $color );
				} else {
					echo '<span style="color:var(--c-slate-300)">—</span>';
				}
				echo '</td>';

				foreach ( $columns as $col_key => $col_label ) {
					$cell_class = isset( $field_sev[ $col_key ] ) ? 'cs-mfb-preview-cell--' . $field_sev[ $col_key ] : '';
					$value      = $item[ $col_key ] ?? '';
					echo '<td' . ( $cell_class ? ' class="' . esc_attr( $cell_class ) . '"' : '' ) . '>';
					if ( 'image_link' === $col_key ) {
						if ( $value ) {
							echo '<img class="cs-mfb-thumb" src="' . esc_url( $value ) . '" loading="lazy" alt="" />';
						} else {
							echo '<div class="cs-mfb-thumb-none"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>';
						}
					} elseif ( 'title' === $col_key || 'description' === $col_key ) {
						echo '<span title="' . esc_attr( $value ) . '">' . esc_html( $value ? mb_strimwidth( $value, 0, 48, '…' ) : '—' ) . '</span>';
					} else {
						echo esc_html( $value ?: '—' );
					}
					echo '</td>';
				}
				echo '</tr>';
			}

			echo '</tbody></table></div>';

			// ── Table footer: rows-per-page + pagination ───────────────────
			$showing_from = $offset + 1;
			$showing_to   = min( $offset + $per_page, $total_items );

			echo '<div class="cs-mfb-table-footer">';
			printf( '<span class="cs-mfb-table-showing">%s</span>', esc_html( sprintf(
				/* translators: 1: start, 2: end, 3: total */
				__( 'Showing %1$d–%2$d of %3$d items', 'merchant-feed-booster-lite-for-woocommerce' ),
				$showing_from, $showing_to, $total_items
			) ) );

			echo '<div class="cs-mfb-table-footer-right">';
			echo '<div class="cs-mfb-rows-per-page">';
			echo '<label for="cs-mfb-preview-per-page">' . esc_html__( 'Rows per page:', 'merchant-feed-booster-lite-for-woocommerce' ) . '</label>';
			echo '<select id="cs-mfb-preview-per-page" onchange="window.location=this.value" class="cs-mfb-select-sm">';
			foreach ( array( 10, 20, 50 ) as $opt_val ) {
				$url = esc_url( add_query_arg( array( 'per_page' => $opt_val, 'paged' => 1 ) ) );
				printf( '<option value="%s"%s>%d</option>', $url, selected( $per_page, $opt_val, false ), $opt_val );
			}
			echo '</select></div>';

			if ( $total_pages > 1 ) {
				echo '<nav class="cs-mfb-pagination-nav">';
				$prev_url = $current_page > 1 ? add_query_arg( 'paged', $current_page - 1 ) : null;
				$next_url = $current_page < $total_pages ? add_query_arg( 'paged', $current_page + 1 ) : null;
				printf( '<a href="%s" class="cs-mfb-pager-btn%s">&laquo;</a>', esc_url( add_query_arg( 'paged', 1 ) ), $current_page <= 1 ? ' disabled' : '' );
				printf( '<a href="%s" class="cs-mfb-pager-btn%s">&lsaquo;</a>', $prev_url ? esc_url( $prev_url ) : '#', ! $prev_url ? ' disabled' : '' );
				printf( '<span class="cs-mfb-pager-current">%d</span>', $current_page );
				printf( '<a href="%s" class="cs-mfb-pager-btn%s">&rsaquo;</a>', $next_url ? esc_url( $next_url ) : '#', ! $next_url ? ' disabled' : '' );
				printf( '<a href="%s" class="cs-mfb-pager-btn%s">&raquo;</a>', esc_url( add_query_arg( 'paged', $total_pages ) ), $current_page >= $total_pages ? ' disabled' : '' );
				echo '</nav>';
			}

			echo '</div>'; // footer-right
			echo '</div>'; // table-footer
		}

		echo '</div>'; // .wrap
	}

}
