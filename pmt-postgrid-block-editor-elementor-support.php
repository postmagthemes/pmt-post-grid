<?php
/**
 * Plugin Name:       PMT PostGrid for block editor with elementor support
 * Plugin URI:        https://www.postmagthemes.com/downloads/post-grid-blocks-addons-gutenberg-and-elementor/
 * Description:       A dynamic post-grid block/addons -- works in both Gutenberg (as a block) and Elementor (as a widget), sharing one PHP render function so both stay visually identical.
 * Version:           2.42.4
 * Author:            Postmagthemes
 * Author URI:        https://postmagthemes.com/
 * Text Domain:       pmt-postgrid-block-editor-elementor-support
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PMTPOFOB_POST_GRID_VER', '2.42.3' );
define( 'PMTPOFOB_POST_GRID_URL', plugin_dir_url( __FILE__ ) );
define( 'PMTPOFOB_POST_GRID_PATH', plugin_dir_path( __FILE__ ) );

/**
 * View count for a post. Prefers this plugin's own tracking (see
 * pmtpofob_post_grid_maybe_count_view() below) since it's guaranteed
 * accurate for anyone running this plugin. Falls back to whichever
 * third-party "post views" plugin's meta key is present, so a site
 * migrating from one of those plugins doesn't lose its existing
 * historical counts. Returns 0 if neither is present.
 */
function pmtpofob_post_grid_get_views( $post_id ) {
	$own = get_post_meta( $post_id, '_pmtpofob_views', true );
	if ( '' !== $own && null !== $own ) {
		return (int) $own;
	}

	$view_meta_keys = array( 'post_views_count', 'views', 'wpb_post_views_count', 'page_views_count' );
	foreach ( $view_meta_keys as $meta_key ) {
		$val = get_post_meta( $post_id, $meta_key, true );
		if ( $val !== '' && $val !== null ) {
			return (int) $val;
		}
	}
	return 0;
}

/**
 * Rough reading time in minutes, based on a 200 words-per-minute average.
 */
function pmtpofob_post_grid_get_reading_time( $post_id ) {
	$content    = get_post_field( 'post_content', $post_id );
	$plain      = wp_strip_all_tags( strip_shortcodes( $content ) );
	$word_count = str_word_count( $plain );
	return max( 1, (int) ceil( $word_count / 200 ) );
}

/**
 * Tracks a view for the post currently being viewed on the front end.
 * Hooked on 'template_redirect' -- WordPress's own hook system is the
 * integration point, so nothing needs adding to single.php or any other
 * theme template.
 *
 * Counts once per visitor per post per day (a cookie set the first time
 * prevents a refresh, or repeat visits the same day, from inflating the
 * number), and skips:
 * - anything that isn't a real single 'post' view (post previews, feeds,
 *   AJAX/cron requests, archive/home/page views),
 * - logged-in users who can edit posts (so the author/editors/admins
 *   browsing their own site don't count themselves),
 * - requests whose user agent matches a common, well-behaved crawler
 *   (not an exhaustive bot list -- no such list is -- just enough to
 *   catch the frequent, self-identifying ones so they don't dominate
 *   the count on a low-traffic site).
 *
 * Writes to this plugin's own post meta key, '_pmtpofob_views' (leading
 * underscore hides it from the Custom Fields UI, standard WP
 * convention).
 */
function pmtpofob_post_grid_maybe_count_view() {
	if ( ! is_singular( 'post' ) || is_preview() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
		return;
	}

	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
	if ( '' === $user_agent ) {
		return;
	}
	$bot_signatures = array(
		'bot', 'spider', 'crawl', 'slurp', 'archiver', 'facebookexternalhit',
		'embedly', 'quora link preview', 'outbrain', 'pinterest', 'slackbot',
		'vkshare', 'whatsapp', 'flipboard', 'tumblr', 'bitlybot', 'nuzzel',
		'discordbot', 'w3c_validator', 'headlesschrome', 'phantomjs',
	);
	foreach ( $bot_signatures as $signature ) {
		if ( false !== strpos( $user_agent, $signature ) ) {
			return;
		}
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$cookie_name = 'pmtpofob_pg_viewed_' . $post_id;
	if ( isset( $_COOKIE[ $cookie_name ] ) ) {
		return;
	}

	if ( ! headers_sent() ) {
		setcookie( $cookie_name, '1', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	}

	$current = (int) get_post_meta( $post_id, '_pmtpofob_views', true );
	update_post_meta( $post_id, '_pmtpofob_views', $current + 1 );
}
add_action( 'template_redirect', 'pmtpofob_post_grid_maybe_count_view' );

/**
 * Returns the current post's title, trimmed to a maximum of 9 words --
 * anything beyond that is cut and replaced with a trailing ellipsis, so
 * long titles can never blow out a card's layout.
 */
function pmtpofob_post_grid_get_trimmed_title() {
	return wp_trim_words( get_the_title(), 9, '…' );
}

/**
 * Deterministically assigns a category a color pair (light background +
 * dark, readable foreground) from a fixed palette, based on the
 * category's term_id. Same category always gets the same color, with
 * zero configuration required. Used for the category badge, meta text/
 * icons, tag pills, and the "Read more" button, all tied to whatever
 * category the post is in -- so everything on a given card matches.
 */
function pmtpofob_post_grid_get_category_color( $term_id ) {
	static $palette = array(
		array(
			'bg' => '#D7D5F6',
			'fg' => '#4C497A',
		), // purple ok
		array(
			'bg' => '#F6C9D8',
			'fg' => '#6D4050',
		), // pink ok
		array(
			'bg' => '#D0F6E6',
			'fg' => '#365E58',
		), // teal ok
		array(
			'bg' => '#C7E0F8',
			'fg' => '#345677',
		), // blue ok
	);

	if ( empty( $term_id ) ) {
		$fallback_color = array(
			'bg' => '#d3d1c7',
			'fg' => '#444441',
		);

		/**
		 * Filters the color pair used for a category badge, meta text,
		 * tags, and the "Read more" button. Lets developers override the
		 * auto-rotated palette with their own brand colors, either for a
		 * specific category (check $term_id) or across the board.
		 *
		 * @param array $color   array( 'bg' => '#hex', 'fg' => '#hex' ).
		 * @param int   $term_id The category's term_id, or 0 for the
		 *                        "no category" fallback color.
		 */
		return apply_filters( 'pmtpofob_post_grid_category_color', $fallback_color, 0 );
	}

	$index = abs( (int) $term_id ) % count( $palette );

	/** This filter is documented above, in the empty-$term_id branch. */
	return apply_filters( 'pmtpofob_post_grid_category_color', $palette[ $index ], (int) $term_id );
}

/**
 * Strictly validates a CSS color value before it's ever echoed into an
 * inline style attribute. Only accepts #hex / #hexa / rgb() / rgba()
 * forms; anything else (including any attempt at CSS/HTML injection)
 * falls back to $default.
 */
function pmtpofob_post_grid_sanitize_css_color( $color, $default ) {
	$color = is_string( $color ) ? trim( $color ) : '';

	if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color ) ) {
		return $color;
	}

	if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $color ) ) {
		return $color;
	}

	if ( in_array( strtolower( $color ), array_keys( pmtpofob_post_grid_named_color_map() ), true ) ) {
		return strtolower( $color );
	}

	return $default;
}

/**
 * A small, fixed allow-list of CSS named colors mapped to their RGB
 * components. Used both to let pmtpofob_post_grid_sanitize_css_color() accept
 * friendly keywords like "grey" (the box-border default) and to resolve
 * any accepted color -- hex, rgb()/rgba(), or one of these keywords --
 * down to plain r/g/b components so an independent opacity value can be
 * combined with it (see pmtpofob_post_grid_color_to_rgb()).
 */
function pmtpofob_post_grid_named_color_map() {
	return array(
		'transparent' => array( 0, 0, 0 ),
		'black'       => array( 0, 0, 0 ),
		'white'       => array( 255, 255, 255 ),
		'grey'        => array( 128, 128, 128 ),
		'gray'        => array( 128, 128, 128 ),
		'red'         => array( 255, 0, 0 ),
		'green'       => array( 0, 128, 0 ),
		'blue'        => array( 0, 0, 255 ),
		'yellow'      => array( 255, 255, 0 ),
		'orange'      => array( 255, 165, 0 ),
		'purple'      => array( 128, 0, 128 ),
		'pink'        => array( 255, 192, 203 ),
		'brown'       => array( 165, 42, 42 ),
		'cyan'        => array( 0, 255, 255 ),
		'magenta'     => array( 255, 0, 255 ),
		'silver'      => array( 192, 192, 192 ),
		'gold'        => array( 255, 215, 0 ),
		'navy'        => array( 0, 0, 128 ),
		'teal'        => array( 0, 128, 128 ),
		'maroon'      => array( 128, 0, 0 ),
		'olive'       => array( 128, 128, 0 ),
		'lime'        => array( 0, 255, 0 ),
		'indigo'      => array( 75, 0, 130 ),
		'violet'      => array( 238, 130, 238 ),
		'beige'       => array( 245, 245, 220 ),
		'tan'         => array( 210, 180, 140 ),
		'coral'       => array( 255, 127, 80 ),
		'salmon'      => array( 250, 128, 114 ),
		'khaki'       => array( 240, 230, 140 ),
		'orchid'      => array( 218, 112, 214 ),
		'plum'        => array( 221, 160, 221 ),
		'crimson'     => array( 220, 20, 60 ),
		'chocolate'   => array( 210, 105, 30 ),
		'turquoise'   => array( 64, 224, 208 ),
		'lavender'    => array( 230, 230, 250 ),
		'ivory'       => array( 255, 255, 240 ),
		'azure'       => array( 240, 255, 255 ),
	);
}

/**
 * Resolves any color already accepted by pmtpofob_post_grid_sanitize_css_color()
 * -- hex (#rgb / #rrggbb / #rrggbbaa), rgb()/rgba(), or a named keyword --
 * down to a plain array( $r, $g, $b ). Any existing alpha component (e.g.
 * on an #rrggbbaa or rgba() value) is intentionally discarded here, since
 * callers combine the result with their own separate opacity control.
 */
function pmtpofob_post_grid_color_to_rgb( $color ) {
	$color = strtolower( trim( (string) $color ) );

	$named = pmtpofob_post_grid_named_color_map();
	if ( isset( $named[ $color ] ) ) {
		return $named[ $color ];
	}

	if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(?:0|1|0?\.\d+)\s*)?\)$/', $color, $m ) ) {
		return array( min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
	}

	if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $color, $m ) ) {
		$hex = $m[1];
		if ( 3 === strlen( $hex ) || 4 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$hex = substr( $hex, 0, 6 );
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	return array( 0, 0, 0 );
}

/**
 * Clamps an opacity value to the valid 0-1 range, falling back to
 * $default for anything non-numeric.
 */
function pmtpofob_post_grid_sanitize_opacity( $opacity, $default ) {
	if ( ! is_numeric( $opacity ) ) {
		return $default;
	}
	return max( 0, min( 1, (float) $opacity ) );
}

/**
 * Normalizes a card element order into a complete, valid, de-duplicated
 * list containing all six known element keys exactly once.
 *
 * @param mixed $order Raw value to normalize (expected: array of strings).
 * @return array Complete list of all five keys, each exactly once.
 */
function pmtpofob_post_grid_sanitize_layout_order( $order ) {
	$known   = array( 'image', 'badge', 'title', 'excerpt', 'tags' );
	$cleaned = array();

	if ( is_array( $order ) ) {
		foreach ( $order as $key ) {
			if ( in_array( $key, $known, true ) && ! in_array( $key, $cleaned, true ) ) {
				$cleaned[] = $key;
			}
		}
	}

	foreach ( $known as $key ) {
		if ( ! in_array( $key, $cleaned, true ) ) {
			$cleaned[] = $key;
		}
	}

	return $cleaned;
}

/**
 * Same normalization as pmtpofob_post_grid_sanitize_layout_order(), but for
 * Design 3's own separate order setting.
 *
 * @param mixed $order Raw value to normalize (expected: array of strings).
 * @return array Complete list of all four keys, each exactly once.
 */
function pmtpofob_post_grid_sanitize_layout_order_design3( $order ) {
	$known   = array( 'badge', 'title', 'excerpt', 'tags' );
	$cleaned = array();

	if ( is_array( $order ) ) {
		foreach ( $order as $key ) {
			if ( in_array( $key, $known, true ) && ! in_array( $key, $cleaned, true ) ) {
				$cleaned[] = $key;
			}
		}
	}

	foreach ( $known as $key ) {
		if ( ! in_array( $key, $cleaned, true ) ) {
			$cleaned[] = $key;
		}
	}

	return $cleaned;
}

/**
 * Applies the author filter and exclude-posts list onto a WP_Query args
 * array -- shared by both designs' category/order query branch.
 */
function pmtpofob_post_grid_apply_author_and_exclusions( $query_args, $args ) {
	if ( ! empty( $args['author'] ) ) {
		$query_args['author'] = (int) $args['author'];
	}

	if ( ! empty( $args['excludePostIds'] ) && is_array( $args['excludePostIds'] ) ) {
		$exclude_ids = array();
		foreach ( $args['excludePostIds'] as $maybe_id ) {
			$int_id = (int) $maybe_id;
			if ( $int_id > 0 ) {
				$exclude_ids[] = $int_id;
			}
		}
		if ( ! empty( $exclude_ids ) ) {
			$query_args['post__not_in'] = $exclude_ids;
		}
	}

	return $query_args;
}

/**
 * Picks which of a post's categories to actually display as badges,
 * capped at $limit.
 */
function pmtpofob_post_grid_get_display_categories( $cats, $selected_category_id, $limit = 2 ) {
	if ( empty( $cats ) ) {
		return array();
	}

	$cats                 = array_values( $cats );
	$selected_category_id = (int) $selected_category_id;

	if ( $selected_category_id > 0 ) {
		foreach ( $cats as $index => $cat ) {
			if ( (int) $cat->term_id === $selected_category_id ) {
				array_unshift( $cats, $cat );
				unset( $cats[ $index + 1 ] );
				$cats = array_values( $cats );
				break;
			}
		}
	}

	return array_slice( $cats, 0, max( 1, (int) $limit ) );
}

/**
 * Renders the optional main section title.
 */
function pmtpofob_post_grid_render_main_title( $args ) {
	if ( empty( $args['showMainTitle'] ) || '' === trim( (string) $args['mainTitleText'] ) ) {
		return '';
	}

	$allowed_tags = array( 'h1', 'h2' );
	$tag          = in_array( $args['mainTitleTag'], $allowed_tags, true ) ? $args['mainTitleTag'] : 'h2';

	return sprintf(
		'<%1$s class="pmtpofob-main-title">%2$s</%1$s>',
		esc_html( $tag ),
		esc_html( $args['mainTitleText'] )
	);
}

/**
 * Renders the live category filter dropdown.
 */
function pmtpofob_post_grid_render_category_dropdown( $args, $instance_id ) {
	$categories = get_categories( array( 'hide_empty' => true ) );
	if ( empty( $categories ) ) {
		return '';
	}

	$current = (string) ( ! empty( $args['category'] ) ? $args['category'] : '' );

	$html  = '<div class="pmtpofob-category-filter-wrap">';
	$html .= '<select class="pmtpofob-category-filter" data-pmtpofob-instance="' . esc_attr( $instance_id ) . '" aria-label="' . esc_attr__( 'Filter by category', 'pmt-postgrid-block-editor-elementor-support' ) . '">';
	$html .= '<option value=""' . selected( $current, '', false ) . '>' . esc_html__( 'All categories', 'pmt-postgrid-block-editor-elementor-support' ) . '</option>';
	foreach ( $categories as $cat ) {
		$html .= '<option value="' . esc_attr( $cat->term_id ) . '"' . selected( $current, (string) $cat->term_id, false ) . '>' . esc_html( $cat->name ) . '</option>';
	}
	$html .= '</select></div>';

	return $html;
}

/**
 * Renders the live sort-order dropdown.
 */
function pmtpofob_post_grid_render_orderby_dropdown( $args, $instance_id ) {
	$current = ( 'comment_count' === $args['orderBy'] ) ? 'comment_count' : 'date';

	$html  = '<div class="pmtpofob-orderby-filter-wrap">';
	$html .= '<select class="pmtpofob-orderby-filter" data-pmtpofob-instance="' . esc_attr( $instance_id ) . '" aria-label="' . esc_attr__( 'Sort posts', 'pmt-postgrid-block-editor-elementor-support' ) . '">';
	$html .= '<option value="date"' . selected( $current, 'date', false ) . '>' . esc_html__( 'Most recent', 'pmt-postgrid-block-editor-elementor-support' ) . '</option>';
	$html .= '<option value="comment_count"' . selected( $current, 'comment_count', false ) . '>' . esc_html__( 'Most commented', 'pmt-postgrid-block-editor-elementor-support' ) . '</option>';
	$html .= '</select></div>';

	return $html;
}

/**
 * Wraps the main title and the two filter dropdowns in one flex row.
 */
function pmtpofob_post_grid_render_section_header( $args, $instance_id, $has_specific_posts ) {
	$title_html    = pmtpofob_post_grid_render_main_title( $args );
	$show_orderby  = $has_specific_posts ? false : ( ! isset( $args['showOrderByDropdown'] ) || ! empty( $args['showOrderByDropdown'] ) );
	$show_category = $has_specific_posts ? false : ( ! isset( $args['showCategoryDropdown'] ) || ! empty( $args['showCategoryDropdown'] ) );
	$orderby_html  = $show_orderby ? pmtpofob_post_grid_render_orderby_dropdown( $args, $instance_id ) : '';
	$category_html = $show_category ? pmtpofob_post_grid_render_category_dropdown( $args, $instance_id ) : '';

	$controls_html = '';
	if ( '' !== $orderby_html || '' !== $category_html ) {
		$controls_html = '<div class="pmtpofob-filter-controls">' . $orderby_html . $category_html . '</div>';
	}

	if ( '' === $title_html && '' === $controls_html ) {
		return '';
	}

	return '<div class="pmtpofob-section-header">' . $title_html . $controls_html . '</div>';
}

/**
 * Allowed HTML for the section header (main title + filter dropdowns).
 * Used with wp_kses() at every echo of pmtpofob_post_grid_render_section_header(),
 * since wp_kses_post() would strip the <select>/<option> elements.
 *
 * @return array
 */
function pmtpofob_post_grid_allowed_header_html() {
	return array(
		'div'    => array( 'class' => true ),
		'h1'     => array( 'class' => true ),
		'h2'     => array( 'class' => true ),
		'select' => array(
			'class'             => true,
			'data-pmtpofob-instance' => true,
			'aria-label'        => true,
		),
		'option' => array(
			'value'    => true,
			'selected' => true,
		),
	);
}

/**
 * Allowed HTML for a full build_markup() output -- header (with its
 * <select>/<option> filter dropdowns) plus grid content combined.
 * Starts from the standard post allowlist (which already covers the
 * cards) and adds the two tags the header needs that 'post' lacks.
 * Used where the caller only gets one echo of the whole string and
 * can't apply wp_kses_post() and the header allowlist separately.
 *
 * @return array
 */
function pmtpofob_post_grid_allowed_output_html() {
	$allowed = wp_kses_allowed_html( 'post' );

	$allowed['select'] = array(
		'class'             => true,
		'data-pmtpofob-instance' => true,
		'aria-label'        => true,
	);
	$allowed['option'] = array(
		'value'    => true,
		'selected' => true,
	);

	return $allowed;
}

function pmtpofob_post_grid_compute_style_vars( $args ) {
	$column_margin       = (int) $args['columnMargin'];
	$row_margin          = (int) $args['rowMargin'];
	$image_border_radius = max( 0, (int) $args['imageBorderRadius'] );
	$shadow_h            = (int) $args['boxShadowHOffset'];
	$shadow_v            = (int) $args['boxShadowVOffset'];
	$shadow_blur         = max( 0, (int) $args['boxShadowBlur'] );
	$shadow_spread       = (int) $args['boxShadowSpread'];
	$shadow_color_input  = pmtpofob_post_grid_sanitize_css_color( $args['boxShadowColor'], '#000000' );
	$shadow_opacity      = pmtpofob_post_grid_sanitize_opacity( $args['boxShadowOpacity'], 0.09 );
	$shadow_rgb          = pmtpofob_post_grid_color_to_rgb( $shadow_color_input );
	$shadow_color        = sprintf( 'rgba(%d,%d,%d,%s)', $shadow_rgb[0], $shadow_rgb[1], $shadow_rgb[2], $shadow_opacity );
	$shadow_inset        = ! empty( $args['boxShadowInset'] ) ? 'inset ' : '';
	$box_shadow_value    = $shadow_inset . $shadow_h . 'px ' . $shadow_v . 'px ' . $shadow_blur . 'px ' . $shadow_spread . 'px ' . $shadow_color;

	$allowed_border_styles = array( 'none', 'solid', 'dashed', 'dotted', 'double' );
	$border_width           = max( 0, (int) $args['borderWidth'] );
	$border_style           = in_array( $args['borderStyle'], $allowed_border_styles, true ) ? $args['borderStyle'] : 'solid';
	$border_color           = pmtpofob_post_grid_sanitize_css_color( $args['borderColor'], 'grey' );
	$border_value           = $border_width . 'px ' . $border_style . ' ' . $border_color;

	$border_radius_top    = max( 0, (int) $args['borderRadiusTop'] );
	$border_radius_bottom = max( 0, (int) $args['borderRadiusBottom'] );

	$title_font_size      = max( 1, (int) $args['titleFontSize'] );
	$title_scale_b        = max( 0, min( 200, (int) $args['titleFontSizeScaleB'] ) ) / 100;
	$related_title_scale  = max( 0, min( 200, (int) $args['relatedTitleScale'] ) ) / 100;

	return sprintf(
		'--pmtpofob-column-margin:%dpx;--pmtpofob-row-margin:%dpx;--pmtpofob-image-radius:%dpx;--pmtpofob-box-shadow:%s;--pmtpofob-box-border:%s;--pmtpofob-box-radius-top:%dpx;--pmtpofob-box-radius-bottom:%dpx;--pmtpofob-title-font-size:%dpx;--pmtpofob-title-scale-b:%s;--pmtpofob-related-title-scale:%s;',
		$column_margin,
		$row_margin,
		$image_border_radius,
		$box_shadow_value,
		$border_value,
		$border_radius_top,
		$border_radius_bottom,
		$title_font_size,
		number_format( $title_scale_b, 2 ),
		number_format( $related_title_scale, 2 )
	);
}

/**
 * Finds up to $limit other posts in the same category as $post_id.
 */
function pmtpofob_post_grid_get_related_posts( $post_id, $limit = 4 ) {
	$cats = get_the_category( $post_id );
	if ( empty( $cats ) ) {
		return array();
	}

	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $limit ),
			'post__not_in'        => array( $post_id ),
			'cat'                 => $cats[0]->term_id,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	return $query->posts;
}

/**
 * Related post titles are always one heading level below the parent
 * post's own title tag.
 */
function pmtpofob_post_grid_get_related_title_tag( $parent_tag ) {
	$map = array(
		'h1' => 'h2',
		'h2' => 'h3',
		'h3' => 'h4',
		'h4' => 'h5',
		'h5' => 'h6',
		'h6' => 'h6',
	);

	return isset( $map[ $parent_tag ] ) ? $map[ $parent_tag ] : 'h5';
}

/**
 * Renders one related-post item.
 */
function pmtpofob_post_grid_render_related_post_item( $related_post, $title_tag, $show_image, $show_title ) {
	$post_id = $related_post->ID;
	ob_start();
	?>
	<a class="pmtpofob-related-post" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<?php if ( $show_image ) : ?>
			<div class="pmtpofob-related-post__image">
				<?php
				if ( has_post_thumbnail( $post_id ) ) {
					echo wp_kses_post (get_the_post_thumbnail( $post_id, 'thumbnail', array( 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ) ) ); 
				}
				?>
			</div>
		<?php endif; ?>
		<?php if ( $show_title ) : ?>
			<<?php echo esc_html( $title_tag ); ?> class="pmtpofob-related-post__title"><?php echo esc_html( wp_trim_words( get_the_title( $post_id ), 6, '…' ) ); ?></<?php echo esc_html( $title_tag ); ?>>
		<?php endif; ?>
	</a>
	<?php
	return ob_get_clean();
}

/**
 * Renders the full related-posts block for one Design 3 row.
 */
function pmtpofob_post_grid_render_related_posts_block( $post_id, $args ) {
	if ( empty( $args['showRelatedImage'] ) && empty( $args['showRelatedTitle'] ) ) {
		return '';
	}

	$related = pmtpofob_post_grid_get_related_posts( $post_id, 4 );
	if ( empty( $related ) ) {
		return '';
	}

	$allowed_title_tags = array( 'h1', 'h2', 'h3', 'h4' );
	$parent_tag          = in_array( $args['titleTag'], $allowed_title_tags, true ) ? $args['titleTag'] : 'h3';
	$related_tag          = pmtpofob_post_grid_get_related_title_tag( $parent_tag );

	$items_html = '';
	foreach ( $related as $related_post ) {
		$items_html .= pmtpofob_post_grid_render_related_post_item( $related_post, $related_tag, ! empty( $args['showRelatedImage'] ), ! empty( $args['showRelatedTitle'] ) );
	}

	$missing     = 4 - count( $related );
	$empty_class = ! empty( $args['showRelatedImage'] ) ? ' pmtpofob-related-post--empty' : '';
	for ( $i = 0; $i < $missing; $i++ ) {
		$items_html .= '<div class="pmtpofob-related-post' . $empty_class . '" aria-hidden="true"></div>';
	}

	$label_html = '';
	if ( ! empty( $args['relatedPostsSectionTitle'] ) ) {
		$label_html = '<p class="pmtpofob-related-posts__label">' . esc_html( $args['relatedPostsSectionTitle'] ) . '</p>';
	}

	return '<div class="pmtpofob-related-posts">' . $label_html . '<div class="pmtpofob-related-posts__list">' . $items_html . '</div></div>';
}

/**
 * Builds a card's meta-info items (author, date, comments, views,
 * reading time) as an array of pre-composed '<li>...</li>' strings.
 * Every dynamic value is escaped where it's inserted -- esc_url() on
 * links, esc_html()/esc_attr() on text and the datetime attribute,
 * absint()/(int) casts on numbers -- exactly as before. This is the
 * "build" half of the split: it only assembles data, nothing here is
 * echoed. Shared by the 'row' and 'full' card variants, which use
 * identical meta info (previously duplicated inline in both).
 *
 * @param array $args    The grid settings.
 * @param int   $post_id The post this card represents.
 * @return array List items, each a complete pre-escaped '<li>' string.
 */
function pmtpofob_post_grid_build_meta_items( $args, $post_id ) {
	$meta_items = array();

	if ( ! empty( $args['showAuthor'] ) ) {
		$author_id   = (int) get_post_field( 'post_author', $post_id );
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_link = get_author_posts_url( $author_id );
		$avatar_html = get_avatar( $author_id, 20, '', '', array( 'class' => 'pmtpofob-author-avatar' ) );

		$meta_items[] = '<li class="pmtpofob-meta-author">' . $avatar_html . '<span class="posted-by"> '
			. '<a href="' . esc_url( $author_link ) . '">' . esc_html( $author_name ) . '</a>'
			. '</span></li>';
	}

	if ( $args['showDate'] ) {
		$archive_link = get_day_link(
			get_the_time( 'Y', $post_id ),
			get_the_time( 'm', $post_id ),
			get_the_time( 'd', $post_id )
		);
		$meta_items[] = '<li><span><i class="fa-regular fa-calendar"></i></span> <span class="posted-on"> '
			. '<a href="' . esc_url( $archive_link ) . '">'
			. '<time class="entry-date published updated" datetime="' . esc_attr( get_the_date( 'c', $post_id ) ) . '">' . esc_html( get_the_date( '', $post_id ) ) . '</time>'
			. '</a></span></li>';
	}
	if ( $args['showComments'] ) {
		$comment_count = (int) get_comments_number( $post_id );
		$comments_link = get_comments_link( $post_id );

		$meta_items[] = '<li><span><i class="fa-regular fa-comment"></i></span><span class="comments-link"> '
			. '<a href="' . esc_url( $comments_link ) . '">'
			. absint( $comment_count )
			. '</a></span></li>';
	}
	if ( $args['showViews'] ) {
		$meta_items[] = '<li><span><i class="fa-regular fa-eye"></i></span><span> ' . (int) pmtpofob_post_grid_get_views( $post_id ) . ' ' . esc_html__( 'Views', 'pmt-postgrid-block-editor-elementor-support' ) . '</span></li>';
	}
	if ( $args['showReadingTime'] ) {
		$meta_items[] = '<li><span><i class="fa-regular fa-clock"></i></span><span> ' . (int) pmtpofob_post_grid_get_reading_time( $post_id ) . ' ' . esc_html__( 'min read', 'pmt-postgrid-block-editor-elementor-support' ) . '</span></li>';
	}

	return $meta_items;
}

/**
 * Wraps a card's meta items (see pmtpofob_post_grid_build_meta_items()) in
 * the <ul> that displays them. This is the "render" half of the split:
 * one wp_kses_post() call on the assembled string, in one place, is
 * what WPCS's static scanner can actually trace as real escaping --
 * unlike the previous inline echo, which concatenated an array built
 * across five separate conditional branches with no single call the
 * scanner could follow. Every tag here (ul/li/span/a/time/img) is
 * within the standard post allowlist, so nothing here is stripped.
 *
 * @param array  $meta_items Items from pmtpofob_post_grid_build_meta_items().
 * @param string $color      Hex/rgb color for the wrapper's text color.
 * @return string The <ul> markup, or '' if there are no items.
 */
function pmtpofob_post_grid_render_meta_list( $meta_items, $color ) {
	if ( empty( $meta_items ) ) {
		return '';
	}

	$html = '<ul class="pmtpofob-extra-info" style="color:' . esc_attr( $color ) . ';">' . implode( '', $meta_items ) . '</ul>';

	return wp_kses_post( $html );
}

/**
 * Renders a single post's card markup. Must be called with the post loop
 * already positioned on the target post (i.e. after the_post()).
 */
function pmtpofob_post_grid_render_card( $args, $layout_order, $title_tag, $variant = 'full', $row_index = 0 ) {
	$post_id      = get_the_ID();
	$has_image    = $args['showImage'] && has_post_thumbnail( $post_id );
	$cats         = get_the_category( $post_id );
	$display_cats = pmtpofob_post_grid_get_display_categories( $cats, isset( $args['category'] ) ? $args['category'] : '', 2 );

	$category_name = ! empty( $display_cats ) ? $display_cats[0]->name : '';
	$cat_term_id    = ! empty( $display_cats ) ? $display_cats[0]->term_id : 0;
	$cat_color      = pmtpofob_post_grid_get_category_color( $cat_term_id );

	ob_start();

	if ( 'row' === $variant ) {
		$content_order = array_values( array_intersect( $layout_order, array( 'badge', 'title', 'excerpt', 'tags' ) ) );
		if ( empty( $content_order ) ) {
			$content_order = array( 'badge', 'title', 'excerpt', 'tags' );
		}

		$image_on_right = ! empty( $args['design3AlternateImage'] ) && ( 1 === $row_index % 2 );
		?>
		<div class="pmtpofob-blog-snippet pmtpofob-blog-snippet--row<?php echo $image_on_right ? ' pmtpofob-row-reverse' : ''; ?>">
			<?php if ( $args['showImage'] ) : ?>
				<a class="pmtpofob-img-holder" href="<?php the_permalink(); ?>">
					<?php
					if ( $has_image ) {
						the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'alt' => get_the_title() ) );
					}
					?>
				</a>
			<?php endif; ?>
			<div class="pmtpofob-blog-content <?php echo $has_image ? 'yes_image' : 'no_image'; ?>">
				<?php
				foreach ( $content_order as $element ) :

					if ( 'badge' === $element ) {
						if ( $args['showCategoryBadge'] && ! empty( $display_cats ) ) :
							?>
							<div class="pmtpofob-category-tag">
								<span class="pmtpofob-category-tag__prefix" style="background:<?php echo esc_attr( $cat_color['fg'] ); ?>;color:<?php echo esc_attr( $cat_color['bg'] ); ?>;"><?php esc_html_e( 'In', 'pmt-postgrid-block-editor-elementor-support' ); ?></span>
								<?php foreach ( $display_cats as $cat ) : ?>
									<?php $this_color = pmtpofob_post_grid_get_category_color( $cat->term_id ); ?>
									<a class="pmtpofob-category-tag__label" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" style="background:<?php echo esc_attr( $this_color['bg'] ); ?>;color:<?php echo esc_attr( $this_color['fg'] ); ?>;"><?php echo esc_html( $cat->name ); ?></a>
								<?php endforeach; ?>
							</div>
							<?php
						endif;

					} elseif ( 'title' === $element ) {
						?>
						<<?php echo esc_html( $title_tag ); ?> class="pmtpofob-title">
							<a href="<?php the_permalink(); ?>"><?php echo esc_html( pmtpofob_post_grid_get_trimmed_title() ); ?></a>
						</<?php echo esc_html( $title_tag ); ?>>
						<?php

					} elseif ( 'excerpt' === $element ) {
						if ( $args['showExcerpt'] ) :
							?>
							<p class="pmtpofob-excerpt">
								<?php
								$excerpt_length = max( 1, (int) $args['excerptLength'] );
								echo wp_kses_post( wp_trim_words( get_the_excerpt(), $excerpt_length, '&hellip;' ) );
								?>
							</p>
							<?php
						endif;

					} elseif ( 'tags' === $element ) {
						if ( $args['showTags'] ) {
							$tags = get_the_tags( $post_id );
							if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
								echo '<ul class="pmtpofob-tag-list">';
								foreach ( $tags as $tag ) {
									echo '<li><a href="' . esc_url( get_tag_link( $tag->term_id ) ) . '" style="background:' . esc_attr( $cat_color['bg'] ) . ';color:' . esc_attr( $cat_color['fg'] ) . ';">' . esc_html( $tag->name ) . '</a></li>';
								}
								echo '</ul>';
							}
						}
					}
				endforeach;

				echo wp_kses_post(
					pmtpofob_post_grid_render_meta_list(
						pmtpofob_post_grid_build_meta_items( $args, $post_id ),
						$cat_color['fg']
					)
				);
				?>
			</div>
		</div>
		<?php
		echo wp_kses_post( pmtpofob_post_grid_render_related_posts_block( $post_id, $args ) );		?>
		<?php
		/**
		 * Filters a single rendered card's HTML before it's returned.
		 *
		 * @param string $html      The card's rendered HTML.
		 * @param int    $post_id   The post this card represents.
		 * @param string $variant   'full', 'simple', or 'row'.
		 * @param array  $args      The full, already-filtered settings array.
		 */
		return apply_filters( 'pmtpofob_post_grid_card_html', ob_get_clean(), get_the_ID(), 'row', $args );
	}

	if ( 'simple' === $variant ) {
		$simple_order = array_values( array_intersect( $layout_order, array( 'image', 'badge', 'title' ) ) );
		if ( empty( $simple_order ) ) {
			$simple_order = array( 'image', 'badge', 'title' );
		}

		$image_is_first = 'image' === $simple_order[0];
		?>
		<div class="pmtpofob-blog-snippet pmtpofob-blog-snippet--simple">
			<?php if ( $args['showImage'] && $image_is_first ) : ?>
				<a class="pmtpofob-img-holder" href="<?php the_permalink(); ?>">
					<?php
					if ( $has_image ) {
						the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'alt' => get_the_title() ) );
					}
					?>
				</a>
			<?php endif; ?>
			<div class="pmtpofob-blog-content <?php echo $has_image ? 'yes_image' : 'no_image'; ?>">
				<?php
				foreach ( $simple_order as $element ) :

					if ( 'image' === $element ) {
						if ( $image_is_first || ! $args['showImage'] ) {
							continue;
						}
						?>
						<a class="pmtpofob-img-holder" href="<?php the_permalink(); ?>">
							<?php
							if ( $has_image ) {
								the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'alt' => get_the_title() ) );
							}
							?>
						</a>
						<?php

					} elseif ( 'badge' === $element ) {
						if ( $args['showCategoryBadge'] && ! empty( $display_cats ) ) :
							?>
							<div class="pmtpofob-category-tag">
								<span class="pmtpofob-category-tag__prefix" style="background:<?php echo esc_attr( $cat_color['fg'] ); ?>;color:<?php echo esc_attr( $cat_color['bg'] ); ?>;"><?php esc_html_e( 'In', 'pmt-postgrid-block-editor-elementor-support' ); ?></span>
								<?php foreach ( $display_cats as $cat ) : ?>
									<?php $this_color = pmtpofob_post_grid_get_category_color( $cat->term_id ); ?>
									<a class="pmtpofob-category-tag__label" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" style="background:<?php echo esc_attr( $this_color['bg'] ); ?>;color:<?php echo esc_attr( $this_color['fg'] ); ?>;"><?php echo esc_html( $cat->name ); ?></a>
								<?php endforeach; ?>
							</div>
							<?php
						endif;

					} elseif ( 'title' === $element ) {
						?>
						<<?php echo esc_html( $title_tag ); ?> class="pmtpofob-title">
							<a href="<?php the_permalink(); ?>"><?php echo esc_html( pmtpofob_post_grid_get_trimmed_title() ); ?></a>
						</<?php echo esc_html( $title_tag ); ?>>
						<?php
					}
				endforeach;

				if ( $args['showDate'] ) :
					$archive_link = get_day_link( get_the_time( 'Y' ), get_the_time( 'm' ), get_the_time( 'd' ) );
					?>
					<ul class="pmtpofob-extra-info pmtpofob-extra-info--simple" style="color:<?php echo esc_attr( $cat_color['fg'] ); ?>;">
						<li>
							<span><i class="fa-regular fa-calendar"></i></span>
							<span class="posted-on">
								<a href="<?php echo esc_url( $archive_link ); ?>">
									<time class="entry-date published updated" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
								</a>
							</span>
						</li>
					</ul>
					<?php
				endif;
				?>
			</div>
		</div>
		<?php
		/** This filter is documented in the 'row' branch above. */
		return apply_filters( 'pmtpofob_post_grid_card_html', ob_get_clean(), get_the_ID(), 'simple', $args );
	}

	// 'full' variant.
	$image_is_first = ! empty( $layout_order ) && 'image' === $layout_order[0];
	?>
	<div class="pmtpofob-blog-snippet">
		<?php if ( $args['showImage'] && $image_is_first ) : ?>
			<a class="pmtpofob-img-holder" href="<?php the_permalink(); ?>">
				<?php
				if ( $has_image ) {
					the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'alt' => get_the_title() ) );
				}
				?>
			</a>
		<?php endif; ?>
		<div class="pmtpofob-blog-content <?php echo $has_image ? 'yes_image' : 'no_image'; ?>">
			<?php
			foreach ( $layout_order as $element ) :

				if ( 'image' === $element ) {
					if ( $image_is_first || ! $args['showImage'] ) {
						continue;
					}
					?>
					<a class="pmtpofob-img-holder" href="<?php the_permalink(); ?>">
						<?php
						if ( $has_image ) {
							the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'alt' => get_the_title() ) );
						}
						?>
					</a>
					<?php

				} elseif ( 'badge' === $element ) {
					if ( $args['showCategoryBadge'] && ! empty( $display_cats ) ) :
						?>
						<div class="pmtpofob-category-tag">
							<span class="pmtpofob-category-tag__prefix" style="background:<?php echo esc_attr( $cat_color['fg'] ); ?>;color:<?php echo esc_attr( $cat_color['bg'] ); ?>;"><?php esc_html_e( 'In', 'pmt-postgrid-block-editor-elementor-support' ); ?></span>
							<?php foreach ( $display_cats as $cat ) : ?>
								<?php $this_color = pmtpofob_post_grid_get_category_color( $cat->term_id ); ?>
								<a class="pmtpofob-category-tag__label" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" style="background:<?php echo esc_attr( $this_color['bg'] ); ?>;color:<?php echo esc_attr( $this_color['fg'] ); ?>;"><?php echo esc_html( $cat->name ); ?></a>
							<?php endforeach; ?>
						</div>
						<?php
					endif;

				} elseif ( 'title' === $element ) {
					?>
					<<?php echo esc_html( $title_tag ); ?> class="pmtpofob-title">
						<a href="<?php the_permalink(); ?>"><?php echo esc_html( pmtpofob_post_grid_get_trimmed_title() ); ?></a>
					</<?php echo esc_html( $title_tag ); ?>>
					<?php

				} elseif ( 'excerpt' === $element ) {
					if ( $args['showExcerpt'] ) :
						?>
						<p class="pmtpofob-excerpt">
							<?php
							$excerpt_length = max( 1, (int) $args['excerptLength'] );
							echo wp_kses_post( wp_trim_words( get_the_excerpt(), $excerpt_length, '&hellip;' ) );
							?>
						</p>
						<?php
					endif;

				} elseif ( 'tags' === $element ) {
					if ( $args['showTags'] ) {
						$tags = get_the_tags( $post_id );
						if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
							echo '<ul class="pmtpofob-tag-list">';
							foreach ( $tags as $tag ) {
								echo '<li><a href="' . esc_url( get_tag_link( $tag->term_id ) ) . '" style="background:' . esc_attr( $cat_color['bg'] ) . ';color:' . esc_attr( $cat_color['fg'] ) . ';">' . esc_html( $tag->name ) . '</a></li>';
							}
							echo '</ul>';
						}
					}
				}
			endforeach;

			echo wp_kses_post(
				pmtpofob_post_grid_render_meta_list(
					pmtpofob_post_grid_build_meta_items( $args, $post_id ),
					$cat_color['fg']
				)
			);

			if ( $args['showReadMore'] ) {
				$read_more_text = ! empty( $args['readMoreText'] ) ? $args['readMoreText'] : __( 'Read More', 'pmt-postgrid-block-editor-elementor-support' );
				?>
				<a class="pmtpofob-read-more btn btn-text" href="<?php the_permalink(); ?>" style="background:<?php echo esc_attr( $cat_color['fg'] ); ?>;">
					<?php echo esc_html( $read_more_text ); ?> <span aria-hidden="true">&rarr;</span>
				</a>
				<?php
			}
			?>
		</div>
	</div>
	<?php
	/** This filter is documented in the 'row' branch above. */
	return apply_filters( 'pmtpofob_post_grid_card_html', ob_get_clean(), get_the_ID(), 'full', $args );
}

/**
 * Where the "Show more" button should link to.
 */
function pmtpofob_post_grid_get_more_link( $category_id ) {
	$category_id = (int) $category_id;

	if ( $category_id > 0 ) {
		$link = get_category_link( $category_id );
		if ( $link && ! is_wp_error( $link ) ) {
			return $link;
		}
	}

	if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ) {
		$blog_link = get_permalink( get_option( 'page_for_posts' ) );
		if ( $blog_link ) {
			return $blog_link;
		}
	}

	return home_url( '/' );
}

/**
 * Renders the "Show more" button.
 */
function pmtpofob_post_grid_render_show_more_button( $url ) {
	return sprintf(
		'<div class="pmtpofob-show-more-wrap"><a class="pmtpofob-show-more" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span aria-hidden="true">&rarr;</span></a></div>',
		esc_url( $url ),
		esc_html__( 'Show more', 'pmt-postgrid-block-editor-elementor-support' )
	);
}

/**
 * Builds one post's structured-data summary.
 */
function pmtpofob_post_grid_get_structured_data_item( $post_id, $position ) {
	$item = array(
		'@type'    => 'ListItem',
		'position' => (int) $position,
		'item'     => array(
			'@type'         => 'Article',
			'headline'      => get_the_title( $post_id ),
			'url'           => get_permalink( $post_id ),
			'datePublished' => get_the_date( 'c', $post_id ),
		),
	);

	$image_id = get_post_thumbnail_id( $post_id );
	if ( $image_id ) {
		$image_url = wp_get_attachment_image_url( $image_id, 'large' );
		if ( $image_url ) {
			$item['item']['image'] = $image_url;
		}
	}

	$author_name = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
	if ( $author_name ) {
		$item['item']['author'] = array(
			'@type' => 'Person',
			'name'  => $author_name,
		);
	}

	return $item;
}

/**
 * Builds the ItemList structured-data array for a grid instance, from
 * the list of per-post items collected via
 * pmtpofob_post_grid_get_structured_data_item(). Returns an array, not a
 * printed tag -- see pmtpofob_post_grid_print_structured_data() for output.
 */
function pmtpofob_post_grid_render_structured_data( $items ) {
	if ( empty( $items ) ) {
		return array();
	}

	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'itemListElement' => $items,
	);

	/**
	 * Filters the ItemList structured-data array before it's serialized,
	 * so developers can add/remove fields or drop it entirely (return an
	 * empty array or false) for a specific grid instance.
	 *
	 * @param array $schema The full ItemList schema.org array.
	 * @param array $items  The raw per-post items that built it.
	 */
	$schema = apply_filters( 'pmtpofob_post_grid_structured_data', $schema, $items );

	return ( empty( $schema ) || ! is_array( $schema ) ) ? array() : $schema;
}

/**
 * Prints the ItemList JSON-LD <script> tag for a grid instance, if
 * enabled and there's anything to print. Always called OUTSIDE any
 * kses-filtered HTML string -- never pass its output through
 * wp_kses_post() or wp_kses(), which would strip the <script> tag and
 * leave the raw JSON visible on the page.
 *
 * @param array $args  The grid settings.
 * @param array $items Per-post items from pmtpofob_post_grid_get_structured_data_item().
 */
function pmtpofob_post_grid_print_structured_data( $args, $items ) {
	if ( empty( $args['showStructuredData'] ) ) {
		return;
	}

	$schema = pmtpofob_post_grid_render_structured_data( $items );
	if ( ! $schema ) {
		return;
	}

	wp_print_inline_script_tag( wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ), array( 'type' => 'application/ld+json' ) );
	
}

/**
 * Stores this grid instance's full args in a transient, keyed by a
 * freshly generated instance ID, so the category-filter AJAX endpoint
 * can look up "everything about this specific grid" from just an ID.
 */
function pmtpofob_post_grid_store_instance( $args ) {
	$instance_id = 'i' . substr( md5( wp_json_encode( $args ) . microtime() ), 0, 16 );
	set_transient( 'pmtpofob_grid_' . $instance_id, $args, 12 * HOUR_IN_SECONDS );
	return $instance_id;
}

/**
 * REST callback for the live category filter. Accepts only an
 * instance_id and the new category to switch to, then re-renders just
 * that instance's grid content with the category swapped in.
 */
function pmtpofob_post_grid_rest_filter_callback( WP_REST_Request $request ) {
	$instance_id = sanitize_text_field( (string) $request->get_param( 'instance_id' ) );
	$category    = sanitize_text_field( (string) $request->get_param( 'category' ) );
	$order_by    = sanitize_text_field( (string) $request->get_param( 'order_by' ) );

	if ( '' === $instance_id || ! preg_match( '/^i[a-f0-9]{16}$/', $instance_id ) ) {
		return new WP_Error( 'pmtpofob_invalid_instance', __( 'Invalid grid instance.', 'pmt-postgrid-block-editor-elementor-support' ), array( 'status' => 400 ) );
	}

	$args = get_transient( 'pmtpofob_grid_' . $instance_id );
	if ( false === $args || ! is_array( $args ) ) {
		return new WP_Error( 'pmtpofob_expired_instance', __( 'This grid has expired -- please reload the page.', 'pmt-postgrid-block-editor-elementor-support' ), array( 'status' => 404 ) );
	}

	$args['category'] = ( '' !== $category && ctype_digit( $category ) ) ? $category : '';
	$args['orderBy']  = ( 'comment_count' === $order_by ) ? 'comment_count' : 'date';

	set_transient( 'pmtpofob_grid_' . $instance_id, $args, 12 * HOUR_IN_SECONDS );

	$structured_data_items = array();

	if ( 'design_2' === $args['design'] ) {
		$html = pmtpofob_post_grid_build_design2_grid_content( $args, $structured_data_items );
	} elseif ( 'design_3' === $args['design'] ) {
		$html = pmtpofob_post_grid_build_design3_grid_content( $args, $structured_data_items );
	} else {
		$html = pmtpofob_post_grid_build_design1_grid_content( $args, $structured_data_items );
	}

	return rest_ensure_response( array( 'html' => wp_kses_post( $html ) ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'pmtpofob-post-grid/v1',
			'/filter',
			array(
				'methods'             => 'POST',
				'callback'            => 'pmtpofob_post_grid_rest_filter_callback',
				'permission_callback' => '__return_true', // Public: only reads published posts, same as the page itself already shows.
				'args'                => array(
					'instance_id' => array(
						'required' => true,
						'type'     => 'string',
					),
					'category'    => array(
						'required' => false,
						'type'     => 'string',
					),
					'order_by'    => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			)
		);
	}
);

/**
 * Design 2's grid content only -- the two-column layout itself, no
 * outer .home-section wrapper and no title/dropdown header.
 *
 * Returns HTML only. Structured-data items are filled into
 * $structured_data_items (passed by reference) so the caller can print
 * the JSON-LD separately -- it can never be run through kses alongside
 * the grid HTML, or the <script> tag gets stripped.
 *
 * @param array $args                  The grid settings.
 * @param array $structured_data_items Filled with per-post schema items.
 * @return string Grid HTML.
 */
function pmtpofob_post_grid_build_design2_grid_content( $args, &$structured_data_items = array() ) {
	$allowed_title_tags = array( 'h1', 'h2', 'h3', 'h4' );
	$title_tag           = in_array( $args['titleTag'], $allowed_title_tags, true ) ? $args['titleTag'] : 'h3';
	$layout_order         = pmtpofob_post_grid_sanitize_layout_order( $args['layoutOrder'] );

	$structured_data_items = array();

	$post_ids = array();
	if ( ! empty( $args['postIds'] ) && is_array( $args['postIds'] ) ) {
		foreach ( $args['postIds'] as $maybe_id ) {
			$int_id = (int) $maybe_id;
			if ( $int_id > 0 ) {
				$post_ids[] = $int_id;
			}
		}
	}

	if ( ! empty( $post_ids ) ) {
		$post_ids   = array_slice( $post_ids, 0, 5 );
		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__in'            => $post_ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $post_ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
	} else {
		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 5,
			'orderby'             => sanitize_key( $args['orderBy'] ),
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['cat'] = (int) $args['category'];
		}

		$query_args = pmtpofob_post_grid_apply_author_and_exclusions( $query_args, $args );
	}

	/** This filter is documented in pmtpofob_post_grid_build_design1_grid_content(). */
	$query_args = apply_filters( 'pmtpofob_post_grid_query_args', $query_args, $args, 'design_2' );

	$query = new WP_Query( $query_args );

	ob_start();

	if ( ! $query->have_posts() ) {
		?>
		<div class="pmtpofob-post-grid-block pmtpofob-post-grid-block--empty">
			<p><?php esc_html_e( 'No posts found for this selection.', 'pmt-postgrid-block-editor-elementor-support' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
	?>
	<div class="pmtpofob-design2-grid">
		<?php
		$post_index = 0;
		$col_b_rows = array( array(), array() ); // 2 rows x up to 2 posts each.

		while ( $query->have_posts() && $post_index < 5 ) :
			$query->the_post();
			$structured_data_items[] = pmtpofob_post_grid_get_structured_data_item( get_the_ID(), $post_index + 1 );

			if ( 0 === $post_index ) {
				?>
				<div class="pmtpofob-design2-col-a">
					<?php echo wp_kses_post( pmtpofob_post_grid_render_card( $args, $layout_order, $title_tag, 'full' ) ); ?>
				</div>
				<div class="pmtpofob-design2-col-b">
				<?php
			} else {
				$slot                 = $post_index - 1;          // 0..3
				$row                  = (int) floor( $slot / 2 ); // 0 or 1
				$col_b_rows[ $row ][] = pmtpofob_post_grid_render_card( $args, $layout_order, $title_tag, 'simple' );
			}

			$post_index++;
		endwhile;

		foreach ( $col_b_rows as $row_cards ) {
			echo '<div class="pmtpofob-design2-row">';
			for ( $cell_index = 0; $cell_index < 2; $cell_index++ ) {
				if ( isset( $row_cards[ $cell_index ] ) ) {
					echo '<div class="pmtpofob-design2-cell">' . wp_kses_post( $row_cards[ $cell_index ] ) . '</div>';
				} else {
					echo '<div class="pmtpofob-design2-cell pmtpofob-design2-cell--empty" aria-hidden="true"></div>';
				}
			}
			echo '</div>';
		}
		?>
		</div>
	</div>
	<?php
	if ( empty( $post_ids ) && (int) $query->found_posts > 4 ) {
		echo wp_kses_post( pmtpofob_post_grid_render_show_more_button( pmtpofob_post_grid_get_more_link( $args['category'] ) ) );
	}

	wp_reset_postdata();

	return ob_get_clean();
}

/**
 * Design 2: golden-ratio two-column layout, 5 posts total.
 */
function pmtpofob_post_grid_build_design2_markup( $args, &$structured_data_items = array() ) {
	$style_vars = pmtpofob_post_grid_compute_style_vars( $args );
	$align      = in_array( $args['contentAlign'], array( 'left', 'center', 'right' ), true ) ? $args['contentAlign'] : 'left';

	$has_specific_posts = ! empty( $args['postIds'] ) && is_array( $args['postIds'] ) && count( array_filter( $args['postIds'], function ( $v ) {
		return (int) $v > 0;
	} ) ) > 0;

	$instance_id = pmtpofob_post_grid_store_instance( $args );

	ob_start();
	?>
	<div class="home-section pmtpofob-thumb-blog pmtpofob-post-grid-block pmtpofob-design2 pmtpofob-align-<?php echo esc_attr( $align ); ?><?php echo esc_attr( $args['align_class'] ); ?>" style="<?php echo esc_attr( $style_vars ); ?>">
		<?php echo wp_kses( pmtpofob_post_grid_render_section_header( $args, $instance_id, $has_specific_posts ), pmtpofob_post_grid_allowed_header_html() ); ?>
		<div class="pmtpofob-grid-content" data-pmtpofob-instance="<?php echo esc_attr( $instance_id ); ?>">
			<?php
			$structured_data_items = array();
			$grid_html             = pmtpofob_post_grid_build_design2_grid_content( $args, $structured_data_items );
			echo wp_kses_post( $grid_html );
			?>
		</div>
	</div>
	<?php
	/**
	 * Filters the complete rendered HTML of a grid instance, right
	 * before it's returned. JSON-LD is not included here -- it's printed
	 * separately by the caller via pmtpofob_post_grid_print_structured_data(),
	 * since it can never be run through wp_kses_post() alongside this
	 * markup without its <script> tag being stripped.
	 *
	 * @param string $html   The rendered markup (no JSON-LD script).
	 * @param array  $args   The full, already-filtered settings array.
	 * @param string $design Always 'design_2' here.
	 */
	return apply_filters( 'pmtpofob_post_grid_output', ob_get_clean(), $args, 'design_2' );
}

function pmtpofob_post_grid_build_markup( $args, &$structured_data_items = array() ) {
	$defaults = array(
		'design'            => 'design_1',
		'columns'           => 2,
		'postsToShow'       => 4,
		'category'          => '',
		'orderBy'           => 'date',
		'postIds'           => array(),
		'author'            => '',
		'excludePostIds'    => array(),
		'showAuthor'        => true,
		'showDate'          => true,
		'showComments'      => true,
		'showViews'         => true,
		'showReadingTime'   => true,
		'showCategoryBadge' => true,
		'showImage'         => true,
		'showExcerpt'       => true,
		'excerptLength'     => 20,
		'showTags'          => false,
		'contentAlign'      => 'left',
		'titleTag'          => 'h3',
		'columnMargin'      => 15,
		'rowMargin'         => 15,
		'imageBorderRadius' => 10,
		'boxShadowHOffset'  => 0,
		'boxShadowVOffset'  => 2,
		'boxShadowBlur'     => 12,
		'boxShadowSpread'   => 0,
		'boxShadowColor'    => '#000000',
		'boxShadowOpacity'  => 0.09,
		'boxShadowInset'    => false,
		'borderWidth'       => 0,
		'borderStyle'       => 'solid',
		'borderColor'       => 'grey',
		'borderRadiusTop'   => 10,
		'borderRadiusBottom' => 10,
		'titleFontSize'      => 20,
		'titleFontSizeScaleB' => 75,
		'showMainTitle'     => false,
		'mainTitleText'     => '',
		'mainTitleTag'      => 'h2',
		'showReadMore'      => true,
		'readMoreText'      => 'Read More',
		'layoutOrder'       => array( 'image', 'badge', 'title', 'excerpt', 'tags' ),
		'design3PostsToShow'    => 3,
		'design3AlternateImage' => false,
		'layoutOrderDesign3'    => array( 'badge', 'title', 'excerpt', 'tags' ),
		'relatedPostsSectionTitle' => 'Related Posts',
		'showRelatedImage'      => true,
		'showRelatedTitle'      => true,
		'relatedTitleScale'     => 80,
		'showOrderByDropdown'   => true,
		'showCategoryDropdown'  => true,
		'showStructuredData'    => true,
		'align_class'       => '',
	);
	$args = wp_parse_args( $args, $defaults );

	/**
	 * Filters the fully-merged args array before it's used for anything.
	 *
	 * @param array $args The fully-merged settings array (every default
	 *                     already applied).
	 */
	$args = apply_filters( 'pmtpofob_post_grid_args', $args );

	if ( 'design_2' === $args['design'] ) {
		return pmtpofob_post_grid_build_design2_markup( $args, $structured_data_items );
	}

	if ( 'design_3' === $args['design'] ) {
		return pmtpofob_post_grid_build_design3_markup( $args, $structured_data_items );
	}

	$style_vars = pmtpofob_post_grid_compute_style_vars( $args );
	$align      = in_array( $args['contentAlign'], array( 'left', 'center', 'right' ), true ) ? $args['contentAlign'] : 'left';

	$has_specific_posts = ! empty( $args['postIds'] ) && is_array( $args['postIds'] ) && count( array_filter( $args['postIds'], function ( $v ) {
		return (int) $v > 0;
	} ) ) > 0;

	$instance_id = pmtpofob_post_grid_store_instance( $args );

	ob_start();
	?>
	<div class="home-section pmtpofob-thumb-blog pmtpofob-grid-column-block pmtpofob-post-grid-block pmtpofob-align-<?php echo esc_attr( $align ); ?><?php echo esc_attr( $args['align_class'] ); ?>" style="<?php echo esc_attr( $style_vars ); ?>">
		<?php echo wp_kses( pmtpofob_post_grid_render_section_header( $args, $instance_id, $has_specific_posts ), pmtpofob_post_grid_allowed_header_html() ); ?>
		<div class="pmtpofob-grid-content" data-pmtpofob-instance="<?php echo esc_attr( $instance_id ); ?>">
			<?php
			$structured_data_items = array();
			$grid_html             = pmtpofob_post_grid_build_design1_grid_content( $args, $structured_data_items );
			echo wp_kses_post( $grid_html );
			?>
		</div>
	</div>
	<?php
	/** This filter is documented in pmtpofob_post_grid_build_design2_markup(). */
	return apply_filters( 'pmtpofob_post_grid_output', ob_get_clean(), $args, 'design_1' );
}

/**
 * Design 1's grid content only -- the responsive row of cards, no outer
 * .home-section wrapper and no title/dropdown header.
 *
 * @param array $args                  The grid settings.
 * @param array $structured_data_items Filled with per-post schema items.
 * @return string Grid HTML.
 */
function pmtpofob_post_grid_build_design1_grid_content( $args, &$structured_data_items = array() ) {
	$allowed_title_tags = array( 'h1', 'h2', 'h3', 'h4' );
	$title_tag           = in_array( $args['titleTag'], $allowed_title_tags, true ) ? $args['titleTag'] : 'h3';
	$layout_order         = pmtpofob_post_grid_sanitize_layout_order( $args['layoutOrder'] );

	$structured_data_items = array();

	$post_ids = array();
	if ( ! empty( $args['postIds'] ) && is_array( $args['postIds'] ) ) {
		foreach ( $args['postIds'] as $maybe_id ) {
			$int_id = (int) $maybe_id;
			if ( $int_id > 0 ) {
				$post_ids[] = $int_id;
			}
		}
	}

	if ( ! empty( $post_ids ) ) {
		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__in'            => $post_ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $post_ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
	} else {
		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => (int) $args['postsToShow'],
			'orderby'             => sanitize_key( $args['orderBy'] ),
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['cat'] = (int) $args['category'];
		}

		$query_args = pmtpofob_post_grid_apply_author_and_exclusions( $query_args, $args );
	}

	/**
	 * Filters the WP_Query args before the query actually runs -- lets
	 * developers add a meta_query, tax_query, restrict/expand post
	 * types, or otherwise change what this grid instance pulls from
	 * the database, without touching this file.
	 *
	 * @param array  $query_args The WP_Query arguments about to be used.
	 * @param array  $args       The full, already-filtered settings array.
	 * @param string $design     Which design is querying: 'design_1',
	 *                           'design_2', or 'design_3'.
	 */
	$query_args = apply_filters( 'pmtpofob_post_grid_query_args', $query_args, $args, 'design_1' );

	$query = new WP_Query( $query_args );

	$columns   = max( 1, (int) $args['columns'] );
	$col_width = max( 1, (int) round( 12 / $columns ) );
	$col_class = 'pmtpofob-col-lg-' . $col_width . ' pmtpofob-col-md-6 pmtpofob-col-sm-6';

	ob_start();

	if ( ! $query->have_posts() ) {
		?>
		<div class="pmtpofob-post-grid-block pmtpofob-post-grid-block--empty">
			<p><?php esc_html_e( 'No posts found for this selection.', 'pmt-postgrid-block-editor-elementor-support' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
	?>
	<div class="pmtpofob-row">
		<?php
		$position = 0;
		while ( $query->have_posts() ) :
			$query->the_post();
			$position++;
			$structured_data_items[] = pmtpofob_post_grid_get_structured_data_item( get_the_ID(), $position );
			?>
			<div class="<?php echo esc_attr( $col_class ); ?>">
				<?php echo wp_kses_post( pmtpofob_post_grid_render_card( $args, $layout_order, $title_tag, 'full' ) ); ?>
			</div>
		<?php endwhile; ?>
	</div>
	<?php
	if ( empty( $post_ids ) && (int) $query->found_posts > (int) $args['postsToShow'] ) {
		echo wp_kses_post( pmtpofob_post_grid_render_show_more_button( pmtpofob_post_grid_get_more_link( $args['category'] ) ) );
	}

	wp_reset_postdata();

	return ob_get_clean();
}

/**
 * Design 3: stacked rows, each post split image + content at the golden
 * ratio (38.2% / 61.8%).
 */
function pmtpofob_post_grid_build_design3_markup( $args, &$structured_data_items = array() ) {
	$style_vars = pmtpofob_post_grid_compute_style_vars( $args );
	$align      = in_array( $args['contentAlign'], array( 'left', 'center', 'right' ), true ) ? $args['contentAlign'] : 'left';

	$has_specific_posts = ! empty( $args['postIds'] ) && is_array( $args['postIds'] ) && count( array_filter( $args['postIds'], function ( $v ) {
		return (int) $v > 0;
	} ) ) > 0;

	$instance_id = pmtpofob_post_grid_store_instance( $args );

	ob_start();
	?>
	<div class="home-section pmtpofob-thumb-blog pmtpofob-post-grid-block pmtpofob-design3 pmtpofob-align-<?php echo esc_attr( $align ); ?><?php echo esc_attr( $args['align_class'] ); ?>" style="<?php echo esc_attr( $style_vars ); ?>">
		<?php echo wp_kses( pmtpofob_post_grid_render_section_header( $args, $instance_id, $has_specific_posts ), pmtpofob_post_grid_allowed_header_html() ); ?>
		<div class="pmtpofob-grid-content" data-pmtpofob-instance="<?php echo esc_attr( $instance_id ); ?>">
			<?php
			$structured_data_items = array();
			$grid_html             = pmtpofob_post_grid_build_design3_grid_content( $args, $structured_data_items );
			echo wp_kses_post( $grid_html );
			?>
		</div>
	</div>
	<?php
	/** This filter is documented in pmtpofob_post_grid_build_design2_markup(). */
	return apply_filters( 'pmtpofob_post_grid_output', ob_get_clean(), $args, 'design_3' );
}

/**
 * Design 3's grid content only -- the stacked list of image+content
 * rows, no outer .home-section wrapper and no title/dropdown header.
 *
 * @param array $args                  The grid settings.
 * @param array $structured_data_items Filled with per-post schema items.
 * @return string Grid HTML.
 */
function pmtpofob_post_grid_build_design3_grid_content( $args, &$structured_data_items = array() ) {
	$allowed_title_tags = array( 'h1', 'h2', 'h3', 'h4' );
	$title_tag           = in_array( $args['titleTag'], $allowed_title_tags, true ) ? $args['titleTag'] : 'h3';
	$layout_order         = pmtpofob_post_grid_sanitize_layout_order_design3( $args['layoutOrderDesign3'] );
	$posts_to_show        = max( 1, (int) $args['design3PostsToShow'] );

	$structured_data_items = array();

	$post_ids = array();
	if ( ! empty( $args['postIds'] ) && is_array( $args['postIds'] ) ) {
		foreach ( $args['postIds'] as $maybe_id ) {
			$int_id = (int) $maybe_id;
			if ( $int_id > 0 ) {
				$post_ids[] = $int_id;
			}
		}
	}

	if ( ! empty( $post_ids ) ) {
		$post_ids   = array_slice( $post_ids, 0, $posts_to_show );
		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__in'            => $post_ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $post_ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
	} else {
		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $posts_to_show,
			'orderby'             => sanitize_key( $args['orderBy'] ),
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['cat'] = (int) $args['category'];
		}

		$query_args = pmtpofob_post_grid_apply_author_and_exclusions( $query_args, $args );
	}

	/** This filter is documented in pmtpofob_post_grid_build_design1_grid_content(). */
	$query_args = apply_filters( 'pmtpofob_post_grid_query_args', $query_args, $args, 'design_3' );

	$query = new WP_Query( $query_args );

	ob_start();

	if ( ! $query->have_posts() ) {
		?>
		<div class="pmtpofob-post-grid-block pmtpofob-post-grid-block--empty">
			<p><?php esc_html_e( 'No posts found for this selection.', 'pmt-postgrid-block-editor-elementor-support' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
	?>
	<div class="pmtpofob-design3-list">
		<?php
		$row_index = 0;
		while ( $query->have_posts() ) :
			$query->the_post();
			$row_index++;
			$structured_data_items[] = pmtpofob_post_grid_get_structured_data_item( get_the_ID(), $row_index );
			?>
			<div class="pmtpofob-design3-item">
				<?php echo wp_kses_post( pmtpofob_post_grid_render_card( $args, $layout_order, $title_tag, 'row', $row_index - 1 ) ); ?>
			</div>
			<?php
		endwhile;
		?>
	</div>
	<?php
	if ( empty( $post_ids ) && (int) $query->found_posts > $posts_to_show ) {
		echo wp_kses_post( pmtpofob_post_grid_render_show_more_button( pmtpofob_post_grid_get_more_link( $args['category'] ) ) );
	}

	wp_reset_postdata();

	return ob_get_clean();
}

/**
 * Server-side render for the pmt/post-grid Gutenberg block.
 */
function pmtpofob_post_grid_render_callback( $attributes ) {
	$align_class = '';
	if ( ! empty( $attributes['align'] ) ) {
		$align_class = ' align' . sanitize_html_class( $attributes['align'] );
	}
	$attributes['align_class'] = $align_class;

	$structured_data_items = array();
	$html                   = pmtpofob_post_grid_build_markup( $attributes, $structured_data_items );

	// Gutenberg echoes render_callback's return value directly -- it
	// never runs it through wp_kses_post() the way the AJAX filter
	// endpoint does -- so the JSON-LD script can be safely reattached
	// here rather than staying stripped out for good.
	ob_start();
	pmtpofob_post_grid_print_structured_data( $attributes, $structured_data_items );
	$script = ob_get_clean();

	return $html . $script;
}

function pmtpofob_post_grid_register_block_category( $categories ) {
	return array_merge(
		array(
			array(
				'slug'  => 'postmagthemes',
				'title' => __( 'Post Grid by Postmagthemes', 'pmt-postgrid-block-editor-elementor-support' ),
				'icon'  => 'grid-view',
			),
		),
		$categories
	);
}
add_filter( 'block_categories_all', 'pmtpofob_post_grid_register_block_category', 999999999 );
add_filter( 'block_categories', 'pmtpofob_post_grid_register_block_category', 999999999 );

add_action(
	'init',
	function () {
		wp_register_script(
			'pmtpofob-post-grid-editor',
			PMTPOFOB_POST_GRID_URL . 'block/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-api-fetch', 'wp-i18n' ),
			PMTPOFOB_POST_GRID_VER,
			true
		);

		wp_register_script(
			'pmtpofob-post-grid-frontend-filter',
			PMTPOFOB_POST_GRID_URL . 'block/frontend-filter.js',
			array(),
			PMTPOFOB_POST_GRID_VER,
			true
		);
		wp_localize_script(
			'pmtpofob-post-grid-frontend-filter',
			'pmtPostGridData',
			array(
				'restUrl' => esc_url_raw( rest_url( 'pmtpofob-post-grid/v1/filter' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_register_style(
			'pmtpofob-post-grid-editor-style',
			PMTPOFOB_POST_GRID_URL . 'block/editor.css',
			array( 'wp-edit-blocks' ),
			PMTPOFOB_POST_GRID_VER
		);

		wp_register_style(
			'pmtpofob-post-grid-style',
			PMTPOFOB_POST_GRID_URL . 'block/style.css',
			array( 'dashicons' ),
			PMTPOFOB_POST_GRID_VER
		);

		wp_register_style(
			'pmtpofob-post-grid-fontawsome-style',
			PMTPOFOB_POST_GRID_URL . 'block/fontawesome.css',
			array(),
			PMTPOFOB_POST_GRID_VER
		);
		wp_register_style(
			'pmtpofob-post-grid-fontawsome-regular-style',
			PMTPOFOB_POST_GRID_URL . 'block/regular.css',
			array(),
			PMTPOFOB_POST_GRID_VER
		);

		register_block_type(
			'pmt/post-grid',
			array(
				'api_version'     => 3,
				'category'        => 'postmagthemes',
				'editor_script'   => 'pmtpofob-post-grid-editor',
				'editor_style'    => array( 'pmtpofob-post-grid-editor-style', 'pmtpofob-post-grid-fontawsome-style', 'pmtpofob-post-grid-fontawsome-regular-style' ),
				'style'           => array( 'pmtpofob-post-grid-style', 'pmtpofob-post-grid-fontawsome-style', 'pmtpofob-post-grid-fontawsome-regular-style' ),
				'script'          => 'pmtpofob-post-grid-frontend-filter',
				'render_callback' => 'pmtpofob_post_grid_render_callback',
				'attributes'      => array(
					'align' => array(
						'type'    => 'string',
						'default' => '',
					),
					'columns'           => array(
						'type'    => 'number',
						'default' => 2,
					),
					'design'            => array(
						'type'    => 'string',
						'default' => 'design_1',
					),
					'postsToShow'       => array(
						'type'    => 'number',
						'default' => 4,
					),
					'category'          => array(
						'type'    => 'string',
						'default' => '',
					),
					'categoryLabel'     => array(
						'type'    => 'string',
						'default' => 'All categories',
					),
					'postIds'           => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type' => 'number',
						),
					),
					'author'            => array(
						'type'    => 'string',
						'default' => '',
					),
					'excludePostIds'    => array(
						'type'    => 'array',
						'default' => array(),
						'items'   => array(
							'type' => 'number',
						),
					),
					'orderBy'           => array(
						'type'    => 'string',
						'default' => 'date',
					),
					'showAuthor'        => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showDate'          => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showComments'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showViews'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showReadingTime'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showCategoryBadge' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showImage'         => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showExcerpt'       => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'excerptLength'     => array(
						'type'    => 'number',
						'default' => 20,
					),
					'showTags'          => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'contentAlign'      => array(
						'type'    => 'string',
						'default' => 'left',
					),
					'titleTag'          => array(
						'type'    => 'string',
						'default' => 'h3',
					),
					'columnMargin'      => array(
						'type'    => 'number',
						'default' => 15,
					),
					'rowMargin'         => array(
						'type'    => 'number',
						'default' => 15,
					),
					'imageBorderRadius' => array(
						'type'    => 'number',
						'default' => 10,
					),
					'boxShadowHOffset'  => array(
						'type'    => 'number',
						'default' => 0,
					),
					'boxShadowVOffset'  => array(
						'type'    => 'number',
						'default' => 2,
					),
					'boxShadowBlur'     => array(
						'type'    => 'number',
						'default' => 12,
					),
					'boxShadowSpread'   => array(
						'type'    => 'number',
						'default' => 0,
					),
					'boxShadowColor'    => array(
						'type'    => 'string',
						'default' => '#000000',
					),
					'boxShadowOpacity'  => array(
						'type'    => 'number',
						'default' => 0.09,
					),
					'boxShadowInset'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'borderWidth'       => array(
						'type'    => 'number',
						'default' => 0,
					),
					'borderStyle'       => array(
						'type'    => 'string',
						'default' => 'solid',
					),
					'borderColor'       => array(
						'type'    => 'string',
						'default' => 'grey',
					),
					'borderRadiusTop'    => array(
						'type'    => 'number',
						'default' => 10,
					),
					'borderRadiusBottom' => array(
						'type'    => 'number',
						'default' => 10,
					),
					'titleFontSize'      => array(
						'type'    => 'number',
						'default' => 20,
					),
					'titleFontSizeScaleB' => array(
						'type'    => 'number',
						'default' => 75,
					),
					'showMainTitle'     => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'mainTitleText'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'mainTitleTag'      => array(
						'type'    => 'string',
						'default' => 'h2',
					),
					'showReadMore'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'readMoreText'      => array(
						'type'    => 'string',
						'default' => 'Read More',
					),
					'layoutOrder'       => array(
						'type'    => 'array',
						'default' => array( 'image', 'badge', 'title', 'excerpt', 'tags' ),
						'items'   => array(
							'type' => 'string',
						),
					),
					'design3PostsToShow'    => array(
						'type'    => 'number',
						'default' => 3,
					),
					'design3AlternateImage' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'layoutOrderDesign3'    => array(
						'type'    => 'array',
						'default' => array( 'badge', 'title', 'excerpt', 'tags' ),
						'items'   => array(
							'type' => 'string',
						),
					),
					'relatedPostsSectionTitle' => array(
						'type'    => 'string',
						'default' => 'Related Posts',
					),
					'showRelatedImage'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showRelatedTitle'      => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'relatedTitleScale'     => array(
						'type'    => 'number',
						'default' => 80,
					),
					'showOrderByDropdown'   => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showCategoryDropdown'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'showStructuredData'    => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
	}
);

add_action(
	'enqueue_block_assets',
	function () {
		wp_enqueue_style( 'pmtpofob-post-grid-fontawsome-style' );
		wp_enqueue_style( 'pmtpofob-post-grid-fontawsome-regular-style' );
	}
);

/**
 * Elementor support.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
			add_action(
				'elementor/widgets/register',
				function ( $widgets_manager ) {
					require_once PMTPOFOB_POST_GRID_PATH . 'includes/class-pmtpofob-post-grid-elementor-widget.php';
					if ( class_exists( 'PMTPOFOB_Post_Grid_Elementor_Widget' ) ) {
						$widgets_manager->register( new PMTPOFOB_Post_Grid_Elementor_Widget() );
					}
				}
			);
		} else {
			add_action(
				'elementor/widgets/widgets_registered',
				function ( $widgets_manager ) {
					require_once PMTPOFOB_POST_GRID_PATH . 'includes/class-pmtpofob-post-grid-elementor-widget.php';
					if ( class_exists( 'PMTPOFOB_Post_Grid_Elementor_Widget' ) ) {
						$widgets_manager->register_widget_type( new PMTPOFOB_Post_Grid_Elementor_Widget() );
					}
				}
			);
		}

		add_action(
			'elementor/elements/categories_registered',
			function ( $elements_manager ) {
				$elements_manager->add_category(
					'postmagthemes',
					array(
						'title' => __( 'Post Grid by Postmagthemes', 'pmt-postgrid-block-editor-elementor-support' ),
						'icon'  => 'eicon-posts-grid',
					)
				);
			}
		);

		add_action(
			'elementor/editor/after_enqueue_scripts',
			function () {
				wp_enqueue_script(
					'pmtpofob-post-grid-elementor-editor',
					PMTPOFOB_POST_GRID_URL . 'block/elementor-editor.js',
					array( 'jquery', 'elementor-editor' ),
					PMTPOFOB_POST_GRID_VER,
					true
				);
				wp_localize_script(
					'pmtpofob-post-grid-elementor-editor',
					'pmtPostGridElementorL10n',
					array(
						'confirmText' => __( 'Reset', 'pmt-postgrid-block-editor-elementor-support' ),
					)
				);
			}
		);
	}
);

/**
 * "Our Products" admin page.
 */
function pmtpofob_post_grid_get_other_plugins() {
	return array(
		array(
			'name'    => __( 'PostmagThemes Demo Import', 'pmt-postgrid-block-editor-elementor-support' ),
			'desc'    => __( 'One-click demo content importer for postmagthemes themes.', 'pmt-postgrid-block-editor-elementor-support' ),
			'url'     => 'https://wordpress.org/plugins/postmagthemes-demo-import/',
			'installs' => '1,000+',
		),
		array(
			'name'    => __( 'WP Theme Statistic', 'pmt-postgrid-block-editor-elementor-support' ),
			'desc'    => __( 'Shows a theme\'s WordPress.org stats (downloads, active installs, ratings) via a shortcode.', 'pmt-postgrid-block-editor-elementor-support' ),
			'url'     => 'https://wordpress.org/plugins/wp-theme-statistic/',
			'installs' => '50+',
		),
	);
}

function pmtpofob_post_grid_get_other_themes() {
	return array(
		array( 'name' => 'Color Newsmagazine', 'slug' => 'color-newsmagazine', 'version' => '1.4.4', 'installs' => '500+' ),
		array( 'name' => 'New Blog', 'slug' => 'new-blog', 'version' => '1.6.2', 'installs' => '600+' ),
		array( 'name' => 'Context Blog', 'slug' => 'context-blog', 'version' => '1.3.7', 'installs' => '900+' ),
		array( 'name' => 'Newsmag Context Blog', 'slug' => 'newsmag-context-blog', 'version' => '1.0.6', 'installs' => '400+' ),
		array( 'name' => 'Ink Context Blog', 'slug' => 'ink-context-blog', 'version' => '1.1.3', 'installs' => '600+' ),
		array( 'name' => 'Voice Blog', 'slug' => 'voice-blog', 'version' => '1.3.9', 'installs' => '200+' ),
		array( 'name' => 'New Blog Jr', 'slug' => 'new-blog-jr', 'version' => '1.1.3', 'installs' => '200+' ),
		array( 'name' => 'New Blog Lite', 'slug' => 'new-blog-lite', 'version' => '1.1.0', 'installs' => '200+' ),
		array( 'name' => 'Queens Magazine Blog', 'slug' => 'queens-magazine-blog', 'version' => '1.2.6', 'installs' => '100+' ),
		array( 'name' => 'Best News', 'slug' => 'best-news', 'version' => '1.2.0', 'installs' => '100+' ),
		array( 'name' => 'Glamour magazine', 'slug' => 'glamour-magazine', 'version' => '1.0.8', 'installs' => '60+' ),
		array( 'name' => 'Isha', 'slug' => 'isha', 'version' => '1.1.2', 'installs' => '60+' ),
		array( 'name' => 'Voice Blog Lite', 'slug' => 'voice-blog-lite', 'version' => '1.1.1', 'installs' => '80+' ),
		array( 'name' => 'Creative Business Blog', 'slug' => 'creative-business-blog', 'version' => '1.1.1', 'installs' => '30+' ),
	);
}

add_action(
	'admin_menu',
	function () {
		add_menu_page(
			__( 'PMT PostGrid for block editor with elementor support', 'pmt-postgrid-block-editor-elementor-support' ),
			__( 'PMT PostGrid for block editor with elementor support', 'pmt-postgrid-block-editor-elementor-support' ),
			'manage_options',
			'pmtpofob-post-grid-products',
			'pmtpofob_post_grid_render_products_page',
			'dashicons-grid-view',
			58
		);
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook_suffix ) {
		if ( 'toplevel_page_pmtpofob-post-grid-products' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'pmtpofob-post-grid-products-page',
			PMTPOFOB_POST_GRID_URL . 'admin/products-page.css',
			array(),
			PMTPOFOB_POST_GRID_VER
		);
	}
);

function pmtpofob_post_grid_render_products_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$plugins = pmtpofob_post_grid_get_other_plugins();
	$themes  = pmtpofob_post_grid_get_other_themes();
	?>
	<div class="wrap pmtpofob-products-page">
		<h1><?php esc_html_e( 'PMT PostGrid for block editor with elementor support', 'pmt-postgrid-block-editor-elementor-support' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %s: plugin version number. */
				esc_html__( 'Version %s. Thanks for using this plugin -- below are our other WordPress.org plugins and themes, in case they\'re useful too.', 'pmt-postgrid-block-editor-elementor-support' ),
				esc_html( PMTPOFOB_POST_GRID_VER )
			);
			?>
		</p>

		<div class="pmtpofob-products-columns">

			<div class="pmtpofob-products-column pmtpofob-products-column--plugins">
				<h2><?php esc_html_e( 'Our Plugins', 'pmt-postgrid-block-editor-elementor-support' ); ?></h2>
				<?php foreach ( $plugins as $plugin ) : ?>
					<div class="pmtpofob-plugin-item">
						<a href="<?php echo esc_url( $plugin['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="pmtpofob-plugin-item__name">
							<?php echo esc_html( $plugin['name'] ); ?>
						</a>
						<p class="pmtpofob-plugin-item__desc"><?php echo esc_html( $plugin['desc'] ); ?></p>
						<span class="pmtpofob-installs">
							<?php
							/* translators: %s: active install count, e.g. "1,000+". */
							printf( esc_html__( '%s active installs', 'pmt-postgrid-block-editor-elementor-support' ), esc_html( $plugin['installs'] ) );
							?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="pmtpofob-products-column pmtpofob-products-column--themes">
				<h2><?php esc_html_e( 'Our Themes', 'pmt-postgrid-block-editor-elementor-support' ); ?></h2>
				<?php foreach ( array_slice( $themes, 0, 8 ) as $theme ) : ?>
					<div class="pmtpofob-plugin-item">
						<a href="<?php echo esc_url( 'https://wordpress.org/themes/' . $theme['slug'] . '/' ); ?>" target="_blank" rel="noopener noreferrer" class="pmtpofob-plugin-item__name">
							<?php echo esc_html( $theme['name'] ); ?>
						</a>
						<span class="pmtpofob-installs">
							<?php
							/* translators: %s: active install count, e.g. "900+". */
							printf( esc_html__( '%s active installs', 'pmt-postgrid-block-editor-elementor-support' ), esc_html( $theme['installs'] ) );
							?>
						</span>
					</div>
				<?php endforeach; ?>
				<p class="pmtpofob-more-themes">
					<a href="https://wordpress.org/themes/author/postmagthemes/" target="_blank" rel="noopener noreferrer" class="button">
						<?php esc_html_e( 'More themes', 'pmt-postgrid-block-editor-elementor-support' ); ?>
					</a>
				</p>
			</div>

		</div>

		<p class="pmtpofob-products-footer">
			<?php
			printf(
				/* translators: 1: WordPress.org profile link, 2: postmagthemes.com link. */
				wp_kses_post( __( 'See everything we have published on our %1$s, or visit %2$s.', 'pmt-postgrid-block-editor-elementor-support' ) ),
				'<a href="https://profiles.wordpress.org/postmagthemes/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'WordPress.org profile', 'pmt-postgrid-block-editor-elementor-support' ) . '</a>',
				'<a href="https://www.postmagthemes.com" target="_blank" rel="noopener noreferrer">postmagthemes.com</a>'
			);
			?>
		</p>
	</div>
	<?php
}