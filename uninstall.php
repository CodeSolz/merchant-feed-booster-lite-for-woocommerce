<?php
/**
 * Uninstall: remove all plugin data when the user deletes the plugin.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove plugin options.
delete_option( 'cs_mfb_settings' );
delete_option( 'cs_mfb_db_version' );
delete_option( 'cs_mfb_last_run' );
delete_option( 'cs_mfb_activity_log' );

// Remove transients.
delete_transient( 'cs_mfb_scan_state' );

// Remove per-user GTIN notice transients.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$gtin_transient_keys = $wpdb->get_col(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_cs_mfb_gtin_notice_%'"
);
foreach ( $gtin_transient_keys as $option_name ) {
	$transient_key = str_replace( '_transient_', '', $option_name );
	delete_transient( $transient_key );
}

// Remove the audit cache table.
$table = $wpdb->prefix . 'cs_mfb_audit_cache';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

// Remove all post meta added by this plugin.
$meta_keys = array(
	'_cs_mfb_health_score',
	'_cs_mfb_health_score_ts',
	'_cs_mfb_img_dims_cache',
	'_cs_mfb_brand',
	'_cs_mfb_gtin',
	'_cs_mfb_mpn',
	'_cs_mfb_google_category',
	'_cs_mfb_condition',
);

foreach ( $meta_keys as $key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $key ), array( '%s' ) );
}

// Clear any scheduled cron events.
$cron_hooks = array(
	'cs_mfb_refresh_feed_cron',
	'cs_mfb_scan_refresh_cron',
);

foreach ( $cron_hooks as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
	wp_clear_scheduled_hook( $hook );
}
