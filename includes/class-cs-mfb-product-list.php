<?php
/**
 * Adds a sortable Feed Health column and an issues filter to the
 * WooCommerce Products admin list table.
 *
 * @package CodeSolz_MFB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeSolz_MFB_Product_List {

	const FILTER_QUERY_VAR = 'cs_mfb_filter';

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( __CLASS__, 'sortable_column' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_sort_and_filter' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_filter_dropdown' ) );
	}

	/**
	 * Insert a "Feed Health" column after Price.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'price' === $key ) {
				$new['cs_mfb_score'] = __( 'Feed Health', 'merchant-feed-booster-lite-for-woocommerce' );
			}
		}
		if ( ! isset( $new['cs_mfb_score'] ) ) {
			$new['cs_mfb_score'] = __( 'Feed Health', 'merchant-feed-booster-lite-for-woocommerce' );
		}
		return $new;
	}

	/**
	 * Render the score pill (or "Not scanned") for the column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 */
	public static function render_column( $column, $post_id ) {
		if ( 'cs_mfb_score' !== $column ) {
			return;
		}

		$raw_score = get_post_meta( $post_id, CS_MFB_SCORE_META_KEY, true );
		if ( '' === $raw_score ) {
			echo '<span style="color:#94a3b8">' . esc_html__( 'Not scanned', 'merchant-feed-booster-lite-for-woocommerce' ) . '</span>';
			return;
		}

		$score = (int) $raw_score;
		$tier  = CodeSolz_MFB_Health_Score::get_tier( $score );
		$color = CodeSolz_MFB_Health_Score::get_tier_color( $tier );
		$url   = admin_url( 'admin.php?page=' . CodeSolz_MFB_Admin::HEALTH_SLUG );

		printf(
			'<a href="%1$s" title="%2$s" style="display:inline-block;min-width:28px;text-align:center;padding:2px 10px;border-radius:99px;font-weight:600;font-size:12px;line-height:1.7;color:#fff;background:%3$s;text-decoration:none">%4$d</a>',
			esc_url( $url ),
			esc_attr( CodeSolz_MFB_Health_Score::get_tier_label( $tier ) ),
			esc_attr( $color ),
			$score
		);
	}

	/**
	 * Mark the column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable_column( $columns ) {
		$columns['cs_mfb_score'] = 'cs_mfb_score';
		return $columns;
	}

	/**
	 * Apply sort-by-score and the issues filter dropdown to the products list query.
	 *
	 * @param WP_Query $query Current query.
	 */
	public static function apply_sort_and_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}

		if ( 'cs_mfb_score' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', CS_MFB_SCORE_META_KEY );
			$query->set( 'orderby', 'meta_value_num' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter = isset( $_GET[ self::FILTER_QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER_QUERY_VAR ] ) ) : '';
		if ( '' === $filter ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query' );

		if ( 'issues' === $filter ) {
			$meta_query[] = array(
				'key'   => CS_MFB_HAS_ISSUES_META_KEY,
				'value' => '1',
			);
			$query->set( 'meta_query', $meta_query );
		} elseif ( 'unscanned' === $filter ) {
			$meta_query[] = array(
				'key'     => CS_MFB_SCORE_META_KEY,
				'compare' => 'NOT EXISTS',
			);
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Render the "Feed Health" filter dropdown above the products table.
	 *
	 * @param string $post_type Current list table post type.
	 */
	public static function render_filter_dropdown( $post_type ) {
		if ( 'product' !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET[ self::FILTER_QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER_QUERY_VAR ] ) ) : '';
		?>
		<select name="<?php echo esc_attr( self::FILTER_QUERY_VAR ); ?>">
			<option value=""><?php esc_html_e( 'All feed health', 'merchant-feed-booster-lite-for-woocommerce' ); ?></option>
			<option value="issues" <?php selected( $current, 'issues' ); ?>><?php esc_html_e( 'Has feed issues', 'merchant-feed-booster-lite-for-woocommerce' ); ?></option>
			<option value="unscanned" <?php selected( $current, 'unscanned' ); ?>><?php esc_html_e( 'Not yet scanned', 'merchant-feed-booster-lite-for-woocommerce' ); ?></option>
		</select>
		<?php
	}
}
