<?php
/**
 * WP-Cron scheduling for periodic feed refresh.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Cron {

	/** Hook name for the daily scan cache refresh. */
	const SCAN_REFRESH_HOOK = 'cs_mfb_scan_refresh_cron';

	/**
	 * Wire up the cron hooks on every request.
	 */
	public static function register() {
		add_action( CS_MFB_CRON_HOOK, array( __CLASS__, 'run' ) );
		add_action( self::SCAN_REFRESH_HOOK, array( __CLASS__, 'run_scan_refresh' ) );

		// Self-heal: if enabled but not scheduled, schedule it.
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );

		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_staleness_notice' ) );
	}

	/**
	 * Warn on our own admin pages when the feed hasn't refreshed for well
	 * beyond its scheduled interval — usually means WP-Cron isn't firing
	 * (low-traffic site with no real server cron configured).
	 */
	public static function maybe_render_staleness_notice() {
		if ( ! current_user_can( CodeSolz_MFB_Admin::cap() ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( $screen->id, 'cs-mfb' ) ) {
			return;
		}
		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			return;
		}

		$settings = CodeSolz_MFB_Settings::all();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}

		$last_run = get_option( CodeSolz_MFB_Feed_Generator::LAST_RUN_KEY );
		if ( empty( $last_run['time'] ) ) {
			return; // Never generated yet — the dashboard already flags "No feed yet".
		}

		$slug      = self::valid_frequency( $settings['refresh_frequency'] );
		$schedules = wp_get_schedules();
		$interval  = isset( $schedules[ $slug ]['interval'] ) ? (int) $schedules[ $slug ]['interval'] : DAY_IN_SECONDS;
		$threshold = ( $interval * 2 ) + HOUR_IN_SECONDS;

		$age = time() - (int) $last_run['time'];
		if ( $age < $threshold ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'Merchant Feed Booster:', 'merchant-feed-booster-lite-for-woocommerce' ),
			esc_html(
				sprintf(
					/* translators: %s = human-readable time since the feed last refreshed */
					__( 'Your Google Merchant feed hasn\'t refreshed in %s, well past its scheduled interval. WP-Cron only runs on site visits — if traffic is low, consider a real server cron.', 'merchant-feed-booster-lite-for-woocommerce' ),
					human_time_diff( (int) $last_run['time'] )
				)
			),
			esc_url( CodeSolz_MFB_Admin::regenerate_url() ),
			esc_html__( 'Regenerate now', 'merchant-feed-booster-lite-for-woocommerce' )
		);
	}

	/**
	 * Ensure both cron events are scheduled when the plugin is enabled.
	 */
	public static function maybe_schedule() {
		$settings = CodeSolz_MFB_Settings::all();
		if ( empty( $settings['enabled'] ) ) {
			self::clear();
			return;
		}
		if ( ! wp_next_scheduled( CS_MFB_CRON_HOOK ) ) {
			$freq = self::valid_frequency( $settings['refresh_frequency'] );
			wp_schedule_event( time() + HOUR_IN_SECONDS, $freq, CS_MFB_CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::SCAN_REFRESH_HOOK ) ) {
			// Offset by 30 min so it doesn't fire at the same time as the feed refresh.
			wp_schedule_event( time() + DAY_IN_SECONDS + 1800, 'daily', self::SCAN_REFRESH_HOOK );
		}
	}

	/**
	 * Reschedule the feed cron with a (possibly new) frequency.
	 *
	 * @param string|null $frequency Optional override; falls back to settings.
	 */
	public static function reschedule( $frequency = null ) {
		self::clear();
		if ( null === $frequency ) {
			$settings  = CodeSolz_MFB_Settings::all();
			$frequency = $settings['refresh_frequency'];
		}
		$freq = self::valid_frequency( $frequency );
		wp_schedule_event( time() + HOUR_IN_SECONDS, $freq, CS_MFB_CRON_HOOK );
		wp_schedule_event( time() + DAY_IN_SECONDS + 1800, 'daily', self::SCAN_REFRESH_HOOK );
	}

	/**
	 * Clear all scheduled cron events for this plugin.
	 */
	public static function clear() {
		foreach ( array( CS_MFB_CRON_HOOK, self::SCAN_REFRESH_HOOK ) as $hook ) {
			$ts = wp_next_scheduled( $hook );
			while ( $ts ) {
				wp_unschedule_event( $ts, $hook );
				$ts = wp_next_scheduled( $hook );
			}
		}
	}

	/**
	 * Validate a frequency slug and return the real WP-Cron recurrence slug.
	 *
	 * When frequency is 'custom' the slug is derived from the saved interval
	 * so WP-Cron can match it to the schedule registered via cron_schedules.
	 *
	 * @param string $frequency Frequency setting value.
	 * @return string
	 */
	protected static function valid_frequency( $frequency ) {
		$frequency = sanitize_key( $frequency );
		$allowed   = array_keys( CodeSolz_MFB_Settings::allowed_frequencies() );
		if ( ! in_array( $frequency, $allowed, true ) ) {
			return 'daily';
		}
		if ( 'custom' === $frequency ) {
			return CodeSolz_MFB_Settings::custom_cron_slug();
		}
		return $frequency;
	}

	/**
	 * Cron callback: regenerate the feed.
	 */
	public static function run() {
		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			return;
		}
		$settings = CodeSolz_MFB_Settings::all();
		if ( empty( $settings['enabled'] ) ) {
			return;
		}
		CodeSolz_MFB_Feed_Generator::generate();
	}

	/**
	 * Cron callback: purge stale audit cache entries so changed products get
	 * rescored on the next health page visit.
	 *
	 * Iterates all rows in the audit cache, computes the current product hash,
	 * and deletes any row whose stored hash no longer matches. Products that
	 * are unchanged are left in the cache (still valid).
	 */
	public static function run_scan_refresh() {
		if ( ! CodeSolz_MFB_Plugin::is_wc_ready() ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . CS_MFB_AUDIT_TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT product_id, product_hash FROM {$table}", ARRAY_A );

		if ( empty( $rows ) ) {
			return;
		}

		$stale_ids = array();
		foreach ( $rows as $row ) {
			$product = wc_get_product( (int) $row['product_id'] );
			if ( ! $product ) {
				$stale_ids[] = (int) $row['product_id'];
				continue;
			}
			if ( CodeSolz_MFB_Health_Score::product_hash( $product ) !== $row['product_hash'] ) {
				$stale_ids[] = (int) $row['product_id'];
			}
		}

		if ( ! empty( $stale_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $stale_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM `' . $table . '` WHERE product_id IN (' . $placeholders . ')', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$stale_ids
				)
			);
		}
	}
}
