<?php
/**
 * Shared helper functions used across the plugin.
 *
 * Naming: all helpers are prefixed `mcwccn_` and live in the global namespace
 * so they are easy to reference from anywhere in the plugin.
 *
 * @package Mad_Cow_Checkout_Notices_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validate a Y-m-d date string. Returns '' when it does not match.
 *
 * @param mixed $raw Raw input value.
 * @return string
 */
function mcwccn_sanitize_date( $raw ) {
	$raw = sanitize_text_field( (string) $raw );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';
}

/**
 * Parse a comma-or-whitespace-separated string of IDs into a unique int[].
 *
 * @param mixed $raw Raw input value.
 * @return int[]
 */
function mcwccn_parse_ids( $raw ) {
	$parts = preg_split( '/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
	$ids   = array_map( 'absint', (array) $parts );
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Format a Y-m-d date into "Tuesday, December 16" for display when the admin
 * did not supply a custom label. Returns '' on parse failure.
 *
 * @param string $ymd Y-m-d date.
 * @return string
 */
function mcwccn_format_date_label( $ymd ) {
	$ymd = (string) $ymd;
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
		return '';
	}
	$ts = strtotime( $ymd );
	if ( ! $ts ) {
		return '';
	}
	return date_i18n( 'l, F j', $ts );
}

/**
 * Return today's date in the site timezone as Y-m-d.
 *
 * @return string
 */
function mcwccn_today() {
	return current_datetime()->format( 'Y-m-d' );
}

/**
 * Render a responsive checkbox grid of every product_cat term. Used by the
 * "Include Categories" field on the notice editor.
 *
 * @param string   $name           Form field name (checkboxes are name="{name}[]").
 * @param string[] $selected_slugs Slugs currently selected.
 */
function mcwccn_render_category_grid( $name, $selected_slugs ) {
	$categories = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);

	if ( is_wp_error( $categories ) || empty( $categories ) ) {
		echo '<p><em>' . esc_html__( 'No product categories found.', 'mad-cow-checkout-notices-for-woocommerce' ) . '</em></p>';
		return;
	}

	$selected_slugs = array_map( 'strval', (array) $selected_slugs );

	echo '<div class="mcwccn-cat-grid">';
	foreach ( $categories as $cat ) {
		$id      = 'mcwccn_cat_' . sanitize_html_class( $name ) . '_' . sanitize_html_class( $cat->slug );
		$checked = in_array( $cat->slug, $selected_slugs, true );
		?>
		<label for="<?php echo esc_attr( $id ); ?>" class="mcwccn-cat-item">
			<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $cat->slug ); ?>" <?php checked( $checked ); ?>>
			<span><?php echo esc_html( $cat->name ); ?></span>
		</label>
		<?php
	}
	echo '</div>';
}

/**
 * Fetch every published, enabled mcwccn_notice ordered by menu_order.
 *
 * @return WP_Post[]
 */
function mcwccn_get_active_notices() {
	$notices = mcwccn_get_notices( 'publish' );

	return array_values(
		array_filter(
			$notices,
			static function ( $notice ) {
				return '1' === (string) get_post_meta( $notice->ID, '_mcwccn_enabled', true );
			}
		)
	);
}

/**
 * Fetch every mcwccn_notice regardless of enabled/publish status. Used to render
 * stored order-meta values on the admin order screen and in emails, so an
 * order's saved answer still shows up after its notice is disabled or trashed.
 *
 * @return WP_Post[]
 */
function mcwccn_get_all_notices() {
	return mcwccn_get_notices( 'any' );
}

/**
 * Shared notice fetcher. Results are cached for the length of the request
 * because both callers run inside loops - the order-display collector is
 * invoked once per order email and once per admin order screen.
 *
 * The number of notices returned is capped. A site with more than
 * MCWCCN_MAX_NOTICES notices is misconfigured rather than served slowly, and
 * an unbounded query here would run on every checkout page load.
 *
 * @param string $status Post status to fetch ('publish' or 'any').
 * @return WP_Post[]
 */
function mcwccn_get_notices( $status ) {
	static $cache = array();

	if ( isset( $cache[ $status ] ) ) {
		return $cache[ $status ];
	}

	$query = new WP_Query(
		array(
			'post_type'              => MCWCCN_POST_TYPE,
			'post_status'            => $status,
			'posts_per_page'         => MCWCCN_MAX_NOTICES,
			'orderby'                => array(
				'menu_order' => 'ASC',
				'date'       => 'ASC',
			),
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		)
	);

	$cache[ $status ] = $query->posts;

	return $cache[ $status ];
}

/**
 * Appearance defaults. These reproduce the look the stylesheet used to hard-code,
 * so notices saved before the appearance controls existed render unchanged.
 *
 * @return array
 */
function mcwccn_appearance_defaults() {
	return array(
		'heading_size'    => 18,
		'heading_weight'  => '700',
		'heading_inherit' => 1,
		'heading_color'   => '#222222',

		'qlabel_size'     => 15,
		'qlabel_weight'   => '600',
		'qlabel_inherit'  => 1,
		'qlabel_color'    => '#222222',

		'bg_transparent' => 0,
		'bg_color'       => '#fafafa',
		'border_width'   => 1,
		'border_style'   => 'solid',
		'border_color'   => '#e0e0e0',
		'accent_width'   => 4,
		'border_radius'  => 3,
		'padding_top'    => 16,
		'padding_right'  => 20,
		'padding_bottom' => 16,
		'padding_left'   => 20,
		'margin_top'     => 0,
		'margin_right'   => 0,
		'margin_bottom'  => 24,
		'margin_left'    => 0,
	);
}

/**
 * Font weights a notice heading may use.
 *
 * @return string[]
 */
function mcwccn_heading_weights() {
	return array( '400', '500', '600', '700', '800' );
}

/**
 * Border styles a notice may use.
 *
 * @return string[]
 */
function mcwccn_border_styles() {
	return array( 'solid', 'dashed', 'dotted', 'double', 'none' );
}

/**
 * Read an integer meta value, falling back to a default only when the key has
 * never been written. A stored 0 is a real value and must survive.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param int    $default Default.
 * @return int
 */
function mcwccn_meta_int( $post_id, $key, $default ) {
	$raw = get_post_meta( $post_id, $key, true );
	return ( '' === $raw || null === $raw ) ? (int) $default : (int) $raw;
}

/**
 * Read a boolean meta value, defaulting only when never written.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param int    $default Default.
 * @return int
 */
function mcwccn_meta_bool( $post_id, $key, $default ) {
	$raw = get_post_meta( $post_id, $key, true );
	return ( '' === $raw || null === $raw ) ? (int) (bool) $default : (int) (bool) $raw;
}

/**
 * Read a hex colour, falling back to the default when absent or malformed.
 * sanitize_hex_color() returns null for an invalid value and '' for an empty one.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param string $default Default hex colour.
 * @return string
 */
function mcwccn_meta_color( $post_id, $key, $default ) {
	$color = sanitize_hex_color( (string) get_post_meta( $post_id, $key, true ) );
	return $color ? $color : $default;
}

/**
 * Load a notice's full configuration array from post_meta with all defaults
 * applied. Callers should pass the WP_Post or a post ID.
 *
 * @param WP_Post|int $notice Notice.
 * @return array
 */
function mcwccn_get_notice_config( $notice ) {
	$post_id = $notice instanceof WP_Post ? $notice->ID : (int) $notice;
	$d       = mcwccn_appearance_defaults();

	$border_style = (string) get_post_meta( $post_id, '_mcwccn_border_style', true );
	if ( ! in_array( $border_style, mcwccn_border_styles(), true ) ) {
		$border_style = $d['border_style'];
	}

	$heading_weight = (string) get_post_meta( $post_id, '_mcwccn_heading_weight', true );
	if ( ! in_array( $heading_weight, mcwccn_heading_weights(), true ) ) {
		$heading_weight = $d['heading_weight'];
	}

	$qlabel_weight = (string) get_post_meta( $post_id, '_mcwccn_qlabel_weight', true );
	if ( ! in_array( $qlabel_weight, mcwccn_heading_weights(), true ) ) {
		$qlabel_weight = $d['qlabel_weight'];
	}

	return array(
		'id'                   => $post_id,
		'enabled'              => (int) get_post_meta( $post_id, '_mcwccn_enabled', true ),
		'heading'              => (string) get_post_meta( $post_id, '_mcwccn_heading', true ),
		'message'              => (string) get_post_meta( $post_id, '_mcwccn_message', true ),
		'show_icon'            => (int) get_post_meta( $post_id, '_mcwccn_show_icon', true ),
		'start_date'           => (string) get_post_meta( $post_id, '_mcwccn_start_date', true ),
		'end_date'             => (string) get_post_meta( $post_id, '_mcwccn_end_date', true ),
		'include_cats'         => (array) get_post_meta( $post_id, '_mcwccn_include_cats', true ),
		'include_ids'          => (array) get_post_meta( $post_id, '_mcwccn_include_ids', true ),
		'question_enabled'     => (int) get_post_meta( $post_id, '_mcwccn_question_enabled', true ),
		'question_required'    => (int) get_post_meta( $post_id, '_mcwccn_question_required', true ),
		'question_type'        => (string) ( get_post_meta( $post_id, '_mcwccn_question_type', true ) ?: 'dropdown' ),
		'question_label'       => (string) get_post_meta( $post_id, '_mcwccn_question_label', true ),
		'question_options'     => (array) get_post_meta( $post_id, '_mcwccn_question_options', true ),
		'question_default'     => (string) get_post_meta( $post_id, '_mcwccn_question_default', true ),
		'question_meta_key'    => (string) get_post_meta( $post_id, '_mcwccn_question_meta_key', true ),
		'question_admin_label' => (string) get_post_meta( $post_id, '_mcwccn_question_admin_label', true ),

		// Appearance - heading.
		'heading_size'         => mcwccn_meta_int( $post_id, '_mcwccn_heading_size', $d['heading_size'] ),
		'heading_weight'       => $heading_weight,
		'heading_inherit'      => mcwccn_meta_bool( $post_id, '_mcwccn_heading_inherit', $d['heading_inherit'] ),
		'heading_color'        => mcwccn_meta_color( $post_id, '_mcwccn_heading_color', $d['heading_color'] ),

		// Appearance - question label.
		'qlabel_size'          => mcwccn_meta_int( $post_id, '_mcwccn_qlabel_size', $d['qlabel_size'] ),
		'qlabel_weight'        => $qlabel_weight,
		'qlabel_inherit'       => mcwccn_meta_bool( $post_id, '_mcwccn_qlabel_inherit', $d['qlabel_inherit'] ),
		'qlabel_color'         => mcwccn_meta_color( $post_id, '_mcwccn_qlabel_color', $d['qlabel_color'] ),

		// Appearance - box.
		'bg_transparent'       => mcwccn_meta_bool( $post_id, '_mcwccn_bg_transparent', $d['bg_transparent'] ),
		'bg_color'             => mcwccn_meta_color( $post_id, '_mcwccn_bg_color', $d['bg_color'] ),
		'border_width'         => mcwccn_meta_int( $post_id, '_mcwccn_border_width', $d['border_width'] ),
		'border_style'         => $border_style,
		'border_color'         => mcwccn_meta_color( $post_id, '_mcwccn_border_color', $d['border_color'] ),
		'accent_width'         => mcwccn_meta_int( $post_id, '_mcwccn_accent_width', $d['accent_width'] ),
		'border_radius'        => mcwccn_meta_int( $post_id, '_mcwccn_border_radius', $d['border_radius'] ),
		'padding_top'          => mcwccn_meta_int( $post_id, '_mcwccn_padding_top', $d['padding_top'] ),
		'padding_right'        => mcwccn_meta_int( $post_id, '_mcwccn_padding_right', $d['padding_right'] ),
		'padding_bottom'       => mcwccn_meta_int( $post_id, '_mcwccn_padding_bottom', $d['padding_bottom'] ),
		'padding_left'         => mcwccn_meta_int( $post_id, '_mcwccn_padding_left', $d['padding_left'] ),
		'margin_top'           => mcwccn_meta_int( $post_id, '_mcwccn_margin_top', $d['margin_top'] ),
		'margin_right'         => mcwccn_meta_int( $post_id, '_mcwccn_margin_right', $d['margin_right'] ),
		'margin_bottom'        => mcwccn_meta_int( $post_id, '_mcwccn_margin_bottom', $d['margin_bottom'] ),
		'margin_left'          => mcwccn_meta_int( $post_id, '_mcwccn_margin_left', $d['margin_left'] ),
	);
}

/**
 * Filter a notice's question options down to those still visible: any option
 * with a date that has already passed is removed. Options without a date pass
 * through unchanged.
 *
 * @param array $options Question options.
 * @return array
 */
function mcwccn_future_options( $options ) {
	$today = mcwccn_today();
	$out   = array();

	foreach ( (array) $options as $opt ) {
		$date = isset( $opt['date'] ) ? (string) $opt['date'] : '';
		if ( '' !== $date && $date < $today ) {
			continue;
		}
		$out[] = $opt;
	}
	return $out;
}

/**
 * Whether every option carries a past date (i.e. the whole notice can be
 * hidden because nothing selectable remains). Notices with no dated options
 * are never expired via this check.
 *
 * @param array $options Question options.
 * @return bool
 */
function mcwccn_all_dates_expired( $options ) {
	$options = array_values( array_filter(
		(array) $options,
		function ( $opt ) {
			return ! empty( $opt['date'] );
		}
	) );

	if ( empty( $options ) ) {
		return false;
	}

	$today = mcwccn_today();
	foreach ( $options as $opt ) {
		if ( $opt['date'] >= $today ) {
			return false;
		}
	}
	return true;
}

/**
 * Check whether a notice's date window is currently open.
 *
 * @param array $config Notice config array.
 * @return bool
 */
function mcwccn_in_date_window( $config ) {
	$today = mcwccn_today();

	if ( ! empty( $config['start_date'] ) && $today < $config['start_date'] ) {
		return false;
	}
	if ( ! empty( $config['end_date'] ) && $today > $config['end_date'] ) {
		return false;
	}
	return true;
}

/**
 * Decide whether a notice should render based on cart contents. If the notice
 * has no include-cats and no include-ids configured, it shows on every cart.
 *
 * @param array $config Notice config array.
 * @return bool
 */
function mcwccn_cart_matches( $config ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return false;
	}

	$include_cats = array_values( array_filter( (array) $config['include_cats'] ) );
	$include_ids  = array_values( array_filter( array_map( 'absint', (array) $config['include_ids'] ) ) );

	// No targeting rules → show on every non-empty checkout page.
	if ( empty( $include_cats ) && empty( $include_ids ) ) {
		return true;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product_id = (int) $cart_item['product_id'];

		if ( ! empty( $include_ids ) && in_array( $product_id, $include_ids, true ) ) {
			return true;
		}

		if ( ! empty( $include_cats ) ) {
			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( in_array( $term->slug, $include_cats, true ) ) {
						return true;
					}
				}
			}
		}
	}

	return false;
}

/**
 * Sanitize a user-supplied meta key. Falls back to a per-post default when
 * empty so we never write to an empty key on the order.
 *
 * @param string $raw     Raw input.
 * @param int    $post_id Notice post ID (used to build the fallback).
 * @return string
 */
function mcwccn_sanitize_meta_key( $raw, $post_id ) {
	$key = sanitize_key( (string) $raw );
	if ( '' === $key ) {
		$key = 'mcwccn_notice_' . (int) $post_id;
	}
	return $key;
}
