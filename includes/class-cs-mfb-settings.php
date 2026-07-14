<?php
/**
 * Plugin settings: defaults, registration, sanitization, accessors.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Settings {

	const OPTION_KEY = 'cs_mfb_settings';

	/**
	 * Hook settings registration.
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
	}

	/**
	 * Default values used when no option exists yet.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enabled'                  => 1,
			'feed_title'               => get_bloginfo( 'name' ),
			'feed_prefix'              => '',
			'default_brand'            => '',
			'default_google_category'  => '',
			'include_oos'              => 0,
			'refresh_frequency'        => 'daily',
			'refresh_time'             => '02:00',
			'refresh_custom_interval'  => 6,
			'refresh_custom_unit'      => 'hours',
			'enforce_gtin_mpn'         => 1,
			'enable_image_checks'      => 1,
			'enable_smart_suggestions' => 1,
		);
	}

	/**
	 * Allowed cron schedule slugs.
	 *
	 * @return array<string, string>
	 */
	public static function allowed_frequencies() {
		return array(
			'twicedaily' => __( 'Twice Daily', 'merchant-feed-booster-lite-for-woocommerce' ),
			'daily'      => __( 'Daily', 'merchant-feed-booster-lite-for-woocommerce' ),
			'weekly'     => __( 'Weekly', 'merchant-feed-booster-lite-for-woocommerce' ),
			'custom'     => __( 'Custom', 'merchant-feed-booster-lite-for-woocommerce' ),
		);
	}

	/**
	 * The WP-Cron recurrence slug used when frequency is 'custom'.
	 *
	 * @param int    $interval Number of units.
	 * @param string $unit     'hours'|'days'|'weeks'.
	 * @return string
	 */
	public static function custom_cron_slug( $interval = 0, $unit = '' ) {
		if ( ! $interval || ! $unit ) {
			$s        = self::all();
			$interval = (int) $s['refresh_custom_interval'];
			$unit     = $s['refresh_custom_unit'];
		}
		return 'cs_mfb_custom_' . (int) $interval . '_' . sanitize_key( $unit );
	}

	/**
	 * Register weekly + dynamic custom cron schedules.
	 */
	public static function add_weekly_cron_schedule( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'merchant-feed-booster-lite-for-woocommerce' ),
			);
		}

		// Register the user-defined custom interval so WP-Cron accepts it.
		$s        = self::all();
		$n        = max( 1, (int) $s['refresh_custom_interval'] );
		$unit     = $s['refresh_custom_unit'];
		$unit_map = array(
			'hours' => HOUR_IN_SECONDS,
			'days'  => DAY_IN_SECONDS,
			'weeks' => WEEK_IN_SECONDS,
		);
		$secs = $n * ( isset( $unit_map[ $unit ] ) ? $unit_map[ $unit ] : DAY_IN_SECONDS );
		$slug = self::custom_cron_slug( $n, $unit );

		if ( ! isset( $schedules[ $slug ] ) ) {
			/* translators: 1: number, 2: unit label (hours/days/weeks) */
			$schedules[ $slug ] = array(
				'interval' => $secs,
				'display'  => sprintf( __( 'Every %1$d %2$s', 'merchant-feed-booster-lite-for-woocommerce' ), $n, $unit ),
			);
		}

		return $schedules;
	}

	/**
	 * Ensure defaults are written to the database (used on activation).
	 */
	public static function ensure_defaults() {
		$existing = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $existing ) ) {
			update_option( self::OPTION_KEY, self::defaults(), false );
		}
	}

	/**
	 * Get a single setting with fallback to defaults.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$option = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $option ) ) {
			$option = array();
		}
		return array_merge( self::defaults(), $option );
	}

	/**
	 * Register the option with the Settings API.
	 */
	public static function register_setting() {
		register_setting(
			'cs_mfb_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize settings input from the settings form.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$current = self::all();
		$out     = $current;

		if ( ! is_array( $input ) ) {
			return $out;
		}

		$out['enabled']                  = ! empty( $input['enabled'] ) ? 1 : 0;
		$out['include_oos']              = ! empty( $input['include_oos'] ) ? 1 : 0;
		$out['enforce_gtin_mpn']         = ! empty( $input['enforce_gtin_mpn'] ) ? 1 : 0;
		$out['enable_image_checks']      = ! empty( $input['enable_image_checks'] ) ? 1 : 0;
		$out['enable_smart_suggestions'] = ! empty( $input['enable_smart_suggestions'] ) ? 1 : 0;
		$out['feed_title']               = isset( $input['feed_title'] ) ? sanitize_text_field( wp_unslash( $input['feed_title'] ) ) : '';
		$out['feed_prefix']              = isset( $input['feed_prefix'] ) ? sanitize_text_field( wp_unslash( $input['feed_prefix'] ) ) : '';
		$out['default_brand']            = isset( $input['default_brand'] ) ? sanitize_text_field( wp_unslash( $input['default_brand'] ) ) : '';
		$out['default_google_category']  = isset( $input['default_google_category'] ) ? sanitize_text_field( wp_unslash( $input['default_google_category'] ) ) : '';

		// Validate refresh time (HH:MM format).
		$time_raw = isset( $input['refresh_time'] ) ? sanitize_text_field( wp_unslash( $input['refresh_time'] ) ) : '02:00';
		$out['refresh_time'] = preg_match( '/^\d{2}:\d{2}$/', $time_raw ) ? $time_raw : '02:00';

		// Custom interval fields.
		$out['refresh_custom_interval'] = isset( $input['refresh_custom_interval'] )
			? max( 1, min( 999, (int) $input['refresh_custom_interval'] ) )
			: 6;
		$allowed_units = array( 'hours', 'days', 'weeks' );
		$raw_unit = isset( $input['refresh_custom_unit'] ) ? sanitize_key( $input['refresh_custom_unit'] ) : 'hours';
		$out['refresh_custom_unit'] = in_array( $raw_unit, $allowed_units, true ) ? $raw_unit : 'hours';

		$freq = isset( $input['refresh_frequency'] ) ? sanitize_key( $input['refresh_frequency'] ) : 'daily';
		if ( ! array_key_exists( $freq, self::allowed_frequencies() ) ) {
			$freq = 'daily';
		}
		$out['refresh_frequency'] = $freq;

		// Reschedule cron if frequency or custom interval changed.
		$freq_changed = $current['refresh_frequency'] !== $out['refresh_frequency'];
		$custom_changed = $out['refresh_frequency'] === 'custom' && (
			$current['refresh_custom_interval'] !== $out['refresh_custom_interval'] ||
			$current['refresh_custom_unit']     !== $out['refresh_custom_unit']
		);
		if ( $freq_changed || $custom_changed ) {
			add_action(
				'updated_option',
				function ( $option ) use ( $out ) {
					if ( self::OPTION_KEY === $option ) {
						CodeSolz_MFB_Cron::reschedule( $out['refresh_frequency'] );
					}
				}
			);
		}

		return $out;
	}
}
