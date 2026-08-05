<?php
/**
 * Plugin Name:       Post Grid for Gutenberg and Elementor
 * Plugin URI:        https://postmagthemes.com/
 * Description:       A dynamic post-grid block/addons -- works in both Gutenberg (as a block) and Elementor (as a widget), sharing one PHP render function so both stay visually identical.
 * Version:           2.34.0
 * Author:            Postmagthemes
 * Author URI:        https://postmagthemes.com/
 * Text Domain:       pmt-post-grid
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Tested up to:      7.0
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PMT_POST_GRID_VER', '2.34.0' );
define( 'PMT_POST_GRID_URL', plugin_dir_url( __FILE__ ) );
define( 'PMT_POST_GRID_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Look up a view count from whichever "post views" plugin/meta key is in
 * use on this site (checked in order, first match wins). Returns 0 if
 * none are present.
 */
function pmt_post_grid_get_views( $post_id ) {
	$view_meta_keys = array( 'post_views_count', 'views', 'wpb_post_views_count', '_pmt_views', 'page_views_count' );
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
function pmt_post_grid_get_reading_time( $post_id ) {
	$content    = get_post_field( 'post_content', $post_id );
	$plain      = wp_strip_all_tags( strip_shortcodes( $content ) );
	$word_count = str_word_count( $plain );
	return max( 1, (int) ceil( $word_count / 200 ) );
}

/**
 * Returns the current post's title, trimmed to a maximum of 9 words --
 * anything beyond that is cut and replaced with a trailing ellipsis, so
 * long titles can never blow out a card's layout.
 */
function pmt_post_grid_get_trimmed_title() {
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
function pmt_post_grid_get_category_color( $term_id ) {
	static $palette = array(
		array(
			'bg' => '#D7D5F6',
			'fg' => '#4C497A',
		), // purple ok
		array(
			'bg' => '#F6C9D8',
			'fg' => '#6D4050',
		), // pink ok
		// array(
		// 	'bg' => '#D3E9B3',
		// 	'fg' => '#45602F',
		// ), // green ok 50%, 20% less
		array(
			'bg' => '#D0F6E6',
			'fg' => '#365E58',
		), // teal ok
		// array(
		// 	'bg' => '#bdcff7',
		// 	'fg' => '#033090',
		// ), // coral ok
		// array(
		// 	'bg' => '#F9DB95',
		// 	'fg' => '#6D502E',
		// ), // amber ok
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
		return apply_filters( 'pmt_post_grid_category_color', $fallback_color, 0 );
	}

	$index = abs( (int) $term_id ) % count( $palette );

	/** This filter is documented above, in the empty-$term_id branch. */
	return apply_filters( 'pmt_post_grid_category_color', $palette[ $index ], (int) $term_id );
}

/**
 * Strictly validates a CSS color value before it's ever echoed into an
 * inline style attribute. Only accepts #hex / #hexa / rgb() / rgba()
 * forms; anything else (including any attempt at CSS/HTML injection)
 * falls back to $default.
 */
function pmt_post_grid_sanitize_css_color( $color, $default ) {
	$color = is_string( $color ) ? trim( $color ) : '';

	if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color ) ) {
		return $color;
	}

	if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $color ) ) {
		return $color;
	}

	if ( in_array( strtolower( $color ), array_keys( pmt_post_grid_named_color_map() ), true ) ) {
		return strtolower( $color );
	}

	return $default;
}

/**
 * A small, fixed allow-list of CSS named colors mapped to their RGB
 * components. Used both to let pmt_post_grid_sanitize_css_color() accept
 * friendly keywords like "grey" (the box-border default) and to resolve
 * any accepted color -- hex, rgb()/rgba(), or one of these keywords --
 * down to plain r/g/b components so an independent opacity value can be
 * combined with it (see pmt_post_grid_color_to_rgb()).
 */
function pmt_post_grid_named_color_map() {
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
 * Resolves any color already accepted by pmt_post_grid_sanitize_css_color()
 * -- hex (#rgb / #rrggbb / #rrggbbaa), rgb()/rgba(), or a named keyword --
 * down to a plain array( $r, $g, $b ). Any existing alpha component (e.g.
 * on an #rrggbbaa or rgba() value) is intentionally discarded here, since
 * callers combine the result with their own separate opacity control.
 */
function pmt_post_grid_color_to_rgb( $color ) {
	$color = strtolower( trim( (string) $color ) );

	$named = pmt_post_grid_named_color_map();
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
function pmt_post_grid_sanitize_opacity( $opacity, $default ) {
	if ( ! is_numeric( $opacity ) ) {
		return $default;
	}
	return max( 0, min( 1, (float) $opacity ) );
}

/**
 * Normalizes a card element order into a complete, valid, de-duplicated
 * list containing all six known element keys exactly once.
 *
 * Any entry that isn't one of the six recognized keys is dropped, and
 * any duplicate is dropped after its first occurrence. Any recognized
 * key missing from the input is appended at the end, in the default
 * order -- so a partial, empty, or garbled value (e.g. an old saved
 * attribute, or a widget imported from elsewhere) always resolves to a
 * complete order rather than silently hiding an element from the card.
 *
 * @param mixed $order Raw value to normalize (expected: array of strings).
 * @return array Complete list of all five keys, each exactly once.
 */
function pmt_post_grid_sanitize_layout_order( $order ) {
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
 * Same normalization as pmt_post_grid_sanitize_layout_order(), but for
 * Design 3's own separate order setting -- 'image' is not part of the
 * known set at all here, since Design 3's image is always its own
 * fixed column (left or right, per the Alternate image side toggle),
 * never a position within the reorderable content stack. This keeps
 * "image" from ever appearing as a draggable row in Design 3's Card
 * element order control in the first place, rather than merely being
 * ignored if present.
 *
 * @param mixed $order Raw value to normalize (expected: array of strings).
 * @return array Complete list of all four keys, each exactly once.
 */
function pmt_post_grid_sanitize_layout_order_design3( $order ) {
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
 * array -- shared by both designs' category/order query branch (never
 * applied when Specific Posts include-mode is active, since choosing
 * exact posts to include already makes both of these moot).
 */
function pmt_post_grid_apply_author_and_exclusions( $query_args, $args ) {
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
 * capped at $limit. If a category filter is active in the block/widget
 * settings and the post belongs to that category, it's guaranteed to
 * be included (and shown first) rather than potentially getting bumped
 * by WordPress's own default category ordering.
 */
function pmt_post_grid_get_display_categories( $cats, $selected_category_id, $limit = 2 ) {
	if ( empty( $cats ) ) {
		return array();
	}

	$cats                  = array_values( $cats );
	$selected_category_id  = (int) $selected_category_id;

	if ( $selected_category_id > 0 ) {
		foreach ( $cats as $index => $cat ) {
			if ( (int) $cat->term_id === $selected_category_id ) {
				// Move the selected category to the front.
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
 * Builds the post grid markup from a normalized args array. This is the
 * single source of truth for the grid's HTML -- both the Gutenberg
 * render_callback and the Elementor widget call this function, so the
 * two builders can never visually drift apart.
 *
 * Expected keys in $args (all optional, sensible defaults applied):
 * columns, postsToShow, category, orderBy, order, postIds (array of
 * specific post IDs -- when non-empty, this OVERRIDES category/orderBy/
 * order/postsToShow entirely and shows exactly those posts in the order
 * given), showDate, showComments, showViews, showReadingTime,
 * showCategoryBadge, showImage, showExcerpt, excerptLength, showTags,
 * contentAlign ('left'|'center'|'right'), titleTag ('h1'-'h4'),
 * columnMargin (px, horizontal gutter between cards in a row),
 * rowMargin (px, vertical space between rows of cards),
 * imageBorderRadius (px, featured image corner rounding),
 * boxShadowHOffset, boxShadowVOffset, boxShadowBlur, boxShadowSpread
 * (all px, may be negative for offset/spread), boxShadowColor (hex,
 * rgb()/rgba(), or a named color -- any existing alpha is ignored),
 * boxShadowOpacity (0-1, applied independently of boxShadowColor),
 * boxShadowInset (bool), borderWidth (px), borderStyle
 * ('none'|'solid'|'dashed'|'dotted'|'double'), borderColor (hex,
 * rgb()/rgba(), or a named color), borderRadiusTop (px, rounds the
 * card's top-left + top-right corners), borderRadiusBottom (px, rounds
 * the bottom-left + bottom-right corners), layoutOrder (array listing
 * some/all of 'image', 'badge', 'title', 'excerpt', 'tags' -- controls
 * the stacking order of these elements within each card; any omitted or
 * unrecognized entries are appended in the default order, so a partial
 * or empty array never drops an element). Meta info and the Read more
 * button are NOT part of layoutOrder -- they always render in a fixed
 * position (meta, then Read more) after everything in layoutOrder, not
 * user-reorderable.
 * align_class (a pre-built class string like ' alignwide', or '').
 *
 * DEFAULT VALUES (also shown as hints next to each control in both the
 * Gutenberg and Elementor editors, so it's always clear what "original"
 * looks like even after a value has been changed):
 * columns: 4, postsToShow: 8, excerptLength: 20, columnMargin: 15,
 * rowMargin: 15, imageBorderRadius: 10, boxShadowHOffset: 0,
 * boxShadowVOffset: 2, boxShadowBlur: 12, boxShadowSpread: 0,
 * boxShadowColor: #000000, boxShadowOpacity: 0.09, boxShadowInset: false,
 * borderWidth: 0, borderStyle: solid, borderColor: grey,
 * borderRadiusTop: 0, borderRadiusBottom: 0, layoutOrder: [ image, badge,
 * title, excerpt, tags ] (meta and Read more always follow, fixed).
 */
/**
 * Computes the wrapper's inline CSS custom properties (column/row margin,
 * image radius, box shadow, box border/corners) from a normalized args
 * array. Shared by both Design 1 and Design 2 so visual style options
 * behave identically regardless of which layout is active.
 */
/**
 * Renders the optional main section title -- a heading (H1 or H2, always
 * higher/more important than the post title's own H1-H4 choice) that
 * sits directly inside .home-section, above the grid itself. Hidden
 * unless both the toggle is on and text has actually been entered.
 */
function pmt_post_grid_render_main_title( $args ) {
	if ( empty( $args['showMainTitle'] ) || '' === trim( (string) $args['mainTitleText'] ) ) {
		return '';
	}

	$allowed_tags = array( 'h1', 'h2' );
	$tag          = in_array( $args['mainTitleTag'], $allowed_tags, true ) ? $args['mainTitleTag'] : 'h2';

	return sprintf(
		'<%1$s class="pmt-main-title">%2$s</%1$s>',
		esc_html( $tag ),
		esc_html( $args['mainTitleText'] )
	);
}

/**
 * Renders the live category filter dropdown -- lets a visitor swap
 * which category's posts the grid shows, without a page reload (an
 * AJAX request re-renders just the grid content, see the REST route
 * and frontend-filter.js). Hidden entirely when "Specific posts" is
 * active, since there's no category-based query to represent/switch.
 */
function pmt_post_grid_render_category_dropdown( $args, $instance_id ) {
	$categories = get_categories( array( 'hide_empty' => false ) );
	if ( empty( $categories ) ) {
		return '';
	}

	$current = (string) ( ! empty( $args['category'] ) ? $args['category'] : '' );

	$html  = '<div class="pmt-category-filter-wrap">';
	$html .= '<select class="pmt-category-filter" data-pmt-instance="' . esc_attr( $instance_id ) . '" aria-label="' . esc_attr__( 'Filter by category', 'pmt-post-grid' ) . '">';
	$html .= '<option value=""' . selected( $current, '', false ) . '>' . esc_html__( 'All categories', 'pmt-post-grid' ) . '</option>';
	foreach ( $categories as $cat ) {
		$html .= '<option value="' . esc_attr( $cat->term_id ) . '"' . selected( $current, (string) $cat->term_id, false ) . '>' . esc_html( $cat->name ) . '</option>';
	}
	$html .= '</select></div>';

	return $html;
}

/**
 * Renders the live sort-order dropdown ("Most recent" / "Most
 * commented") -- same mechanism as the category dropdown: an AJAX
 * request re-renders just the grid content, no page reload. Hidden
 * entirely when "Specific posts" is active, since there's no sortable
 * query to represent/switch (exact posts, in the order picked).
 */
function pmt_post_grid_render_orderby_dropdown( $args, $instance_id ) {
	$current = ( 'comment_count' === $args['orderBy'] ) ? 'comment_count' : 'date';

	$html  = '<div class="pmt-orderby-filter-wrap">';
	$html .= '<select class="pmt-orderby-filter" data-pmt-instance="' . esc_attr( $instance_id ) . '" aria-label="' . esc_attr__( 'Sort posts', 'pmt-post-grid' ) . '">';
	$html .= '<option value="date"' . selected( $current, 'date', false ) . '>' . esc_html__( 'Most recent', 'pmt-post-grid' ) . '</option>';
	$html .= '<option value="comment_count"' . selected( $current, 'comment_count', false ) . '>' . esc_html__( 'Most commented', 'pmt-post-grid' ) . '</option>';
	$html .= '</select></div>';

	return $html;
}

/**
 * Wraps the main title and the two filter dropdowns (sort, then
 * category) in one flex row -- title on the left, dropdowns pinned
 * right as a group via margin-left:auto (so they sit at the right edge
 * whether or not a title is actually present). Each dropdown is its
 * own separate <select> -- not merged into one list. Renders nothing if
 * none of the three have anything to show.
 */
function pmt_post_grid_render_section_header( $args, $instance_id, $has_specific_posts ) {
	$title_html    = pmt_post_grid_render_main_title( $args );
	$show_orderby  = $has_specific_posts ? false : ( ! isset( $args['showOrderByDropdown'] ) || ! empty( $args['showOrderByDropdown'] ) );
	$show_category = $has_specific_posts ? false : ( ! isset( $args['showCategoryDropdown'] ) || ! empty( $args['showCategoryDropdown'] ) );
	$orderby_html  = $show_orderby ? pmt_post_grid_render_orderby_dropdown( $args, $instance_id ) : '';
	$category_html = $show_category ? pmt_post_grid_render_category_dropdown( $args, $instance_id ) : '';

	$controls_html = '';
	if ( '' !== $orderby_html || '' !== $category_html ) {
		$controls_html = '<div class="pmt-filter-controls">' . $orderby_html . $category_html . '</div>';
	}

	if ( '' === $title_html && '' === $controls_html ) {
		return '';
	}

	return '<div class="pmt-section-header">' . $title_html . $controls_html . '</div>';
}

function pmt_post_grid_compute_style_vars( $args ) {
	$column_margin       = (int) $args['columnMargin'];
	$row_margin          = (int) $args['rowMargin'];
	$image_border_radius = max( 0, (int) $args['imageBorderRadius'] );
	$shadow_h            = (int) $args['boxShadowHOffset'];
	$shadow_v            = (int) $args['boxShadowVOffset'];
	$shadow_blur         = max( 0, (int) $args['boxShadowBlur'] );
	$shadow_spread       = (int) $args['boxShadowSpread'];
	$shadow_color_input  = pmt_post_grid_sanitize_css_color( $args['boxShadowColor'], '#000000' );
	$shadow_opacity      = pmt_post_grid_sanitize_opacity( $args['boxShadowOpacity'], 0.09 );
	$shadow_rgb          = pmt_post_grid_color_to_rgb( $shadow_color_input );
	$shadow_color        = sprintf( 'rgba(%d,%d,%d,%s)', $shadow_rgb[0], $shadow_rgb[1], $shadow_rgb[2], $shadow_opacity );
	$shadow_inset        = ! empty( $args['boxShadowInset'] ) ? 'inset ' : '';
	$box_shadow_value    = $shadow_inset . $shadow_h . 'px ' . $shadow_v . 'px ' . $shadow_blur . 'px ' . $shadow_spread . 'px ' . $shadow_color;

	$allowed_border_styles = array( 'none', 'solid', 'dashed', 'dotted', 'double' );
	$border_width           = max( 0, (int) $args['borderWidth'] );
	$border_style           = in_array( $args['borderStyle'], $allowed_border_styles, true ) ? $args['borderStyle'] : 'solid';
	$border_color           = pmt_post_grid_sanitize_css_color( $args['borderColor'], 'grey' );
	$border_value           = $border_width . 'px ' . $border_style . ' ' . $border_color;

	$border_radius_top    = max( 0, (int) $args['borderRadiusTop'] );
	$border_radius_bottom = max( 0, (int) $args['borderRadiusBottom'] );

	// Title font size: one control (Column A / Design 1's actual size),
	// plus a scale factor that derives Column B's (Design 2 only) size
	// from it -- e.g. 75% means Column B is always 3/4 of whatever
	// Column A is set to, with no separate Column B size to maintain.
	// Related post titles (Design 3 only) get their own separate scale
	// the same way.
	$title_font_size = max( 1, (int) $args['titleFontSize'] );
	$title_scale_b    = max( 0, min( 200, (int) $args['titleFontSizeScaleB'] ) ) / 100;
	$related_title_scale = max( 0, min( 200, (int) $args['relatedTitleScale'] ) ) / 100;

	return sprintf(
		'--pmt-column-margin:%dpx;--pmt-row-margin:%dpx;--pmt-image-radius:%dpx;--pmt-box-shadow:%s;--pmt-box-border:%s;--pmt-box-radius-top:%dpx;--pmt-box-radius-bottom:%dpx;--pmt-title-font-size:%dpx;--pmt-title-scale-b:%s;--pmt-related-title-scale:%s;',
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
 * Finds up to $limit other posts in the same category as $post_id --
 * used for Design 3's "related posts" strip under each row. Uses the
 * post's own actual first category, regardless of whatever category
 * filter the grid itself is set to (a post's related posts are always
 * relative to itself, not to the grid's current filter).
 */
function pmt_post_grid_get_related_posts( $post_id, $limit = 4 ) {
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

	$related = $query->posts;

	return $related;
}

/**
 * Related post titles are always one heading level below the parent
 * post's own title tag -- automatic, not user-configurable. Capped at
 * h6 (the lowest legal HTML heading) so an h4 parent doesn't overflow
 * into something invalid.
 */
function pmt_post_grid_get_related_title_tag( $parent_tag ) {
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
 * Renders one related-post item: a square image and/or title, each
 * independently toggleable, never reorderable relative to each other
 * (image always first, title always second, when both are shown).
 */
function pmt_post_grid_render_related_post_item( $related_post, $title_tag, $show_image, $show_title ) {
	$post_id = $related_post->ID;
	ob_start();
	?>
	<a class="pmt-related-post" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
		<?php if ( $show_image ) : ?>
			<div class="pmt-related-post__image">
				<?php
				if ( has_post_thumbnail( $post_id ) ) {
					echo get_the_post_thumbnail( $post_id, 'thumbnail', array( 'loading' => 'lazy', 'alt' => get_the_title( $post_id ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- core-escaped.
				}
				?>
			</div>
		<?php endif; ?>
		<?php if ( $show_title ) : ?>
			<<?php echo esc_html( $title_tag ); ?> class="pmt-related-post__title"><?php echo esc_html( wp_trim_words( get_the_title( $post_id ), 6, '…' ) ); ?></<?php echo esc_html( $title_tag ); ?>>
		<?php endif; ?>
	</a>
	<?php
	return ob_get_clean();
}

/**
 * Renders the full related-posts block for one Design 3 row -- the
 * optional section label, then up to 4 items. Returns '' if the post
 * has no category (nothing to relate it to) or no other posts share
 * that category, or if both image and title are toggled off (nothing
 * would render anyway).
 */
function pmt_post_grid_render_related_posts_block( $post_id, $args ) {
	if ( empty( $args['showRelatedImage'] ) && empty( $args['showRelatedTitle'] ) ) {
		return '';
	}

	$related = pmt_post_grid_get_related_posts( $post_id, 4 );
	if ( empty( $related ) ) {
		return '';
	}

	$allowed_title_tags = array( 'h1', 'h2', 'h3', 'h4' );
	$parent_tag          = in_array( $args['titleTag'], $allowed_title_tags, true ) ? $args['titleTag'] : 'h3';
	$related_tag          = pmt_post_grid_get_related_title_tag( $parent_tag );

	$items_html = '';
	foreach ( $related as $related_post ) {
		$items_html .= pmt_post_grid_render_related_post_item( $related_post, $related_tag, ! empty( $args['showRelatedImage'] ), ! empty( $args['showRelatedTitle'] ) );
	}

	// Pad up to 4 total with empty placeholder slots, so a post with
	// fewer than 4 related posts still shows a visually complete row --
	// same pattern already used for Design 2's Column B. Only given the
	// square shape when images are actually shown -- otherwise it stays
	// unstyled/collapsed, matching the real items' own shrunk height
	// when the image toggle is off (an empty square here would
	// otherwise force every item in the row taller via flex stretch).
	$missing         = 4 - count( $related );
	$empty_class      = ! empty( $args['showRelatedImage'] ) ? ' pmt-related-post--empty' : '';
	for ( $i = 0; $i < $missing; $i++ ) {
		$items_html .= '<div class="pmt-related-post' . $empty_class . '" aria-hidden="true"></div>';
	}

	$label_html = '';
	if ( ! empty( $args['relatedPostsSectionTitle'] ) ) {
		$label_html = '<p class="pmt-related-posts__label">' . esc_html( $args['relatedPostsSectionTitle'] ) . '</p>';
	}

	return '<div class="pmt-related-posts">' . $label_html . '<div class="pmt-related-posts__list">' . $items_html . '</div></div>';
}

/**
 * Renders a single post's card markup. Must be called with the post loop
 * already positioned on the target post (i.e. after the_post()), since it
 * relies on the_permalink()/the_title()/etc. like the rest of this file.
 *
 * $variant 'full'   -- everything, in the user-configured layoutOrder
 *                       (image/badge/title/excerpt/meta/tags). Used for
 *                       Design 1's grid and Design 2's Column A post.
 * $variant 'simple' -- image + title + date only, smaller/lighter card.
 *                       Used for Design 2's four Column B posts.
 * $variant 'row'    -- image and content side by side at the golden
 *                       ratio (image is always its own column here, not
 *                       interspersed into the content flow -- the rest
 *                       of layoutOrder governs the content column's
 *                       internal order). No Read more, ever. Used for
 *                       Design 3. $row_index alternates which side the
 *                       image sits on, when that option is enabled.
 */
function pmt_post_grid_render_card( $args, $layout_order, $title_tag, $variant = 'full', $row_index = 0 ) {
	$post_id       = get_the_ID();
	$has_image     = $args['showImage'] && has_post_thumbnail( $post_id );
	$cats          = get_the_category( $post_id );
	$display_cats  = pmt_post_grid_get_display_categories( $cats, isset( $args['category'] ) ? $args['category'] : '', 2 );

	// Kept for anything that still wants "the" category (e.g. Read more
	// button color) -- always the first of the displayed categories.
	$category_name = ! empty( $display_cats ) ? $display_cats[0]->name : '';
	$cat_term_id    = ! empty( $display_cats ) ? $display_cats[0]->term_id : 0;
	$cat_color      = pmt_post_grid_get_category_color( $cat_term_id );

	ob_start();

	if ( 'row' === $variant ) {
		// Content order: layoutOrder filtered to badge/title/excerpt/tags.
		// Image is always its own column here (not interspersed into the
		// content flow), since that's structurally what a golden-ratio
		// row layout requires -- but which SIDE it's on (left/right) can
		// alternate per row. Meta is fixed last, same as 'full'. Read
		// more never appears in this variant, by design.
		$content_order = array_values( array_intersect( $layout_order, array( 'badge', 'title', 'excerpt', 'tags' ) ) );
		if ( empty( $content_order ) ) {
			$content_order = array( 'badge', 'title', 'excerpt', 'tags' );
		}

		$image_on_right = ! empty( $args['design3AlternateImage'] ) && ( 1 === $row_index % 2 );
		?>
		<div class="pmt-blog-snippet pmt-blog-snippet--row<?php echo $image_on_right ? ' pmt-row-reverse' : ''; ?>">
			<?php if ( $args['showImage'] ) : ?>
				<a class="pmt-img-holder" href="<?php the_permalink(); ?>">
					<?php
					if ( $has_image ) {
						the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'alt' => get_the_title() ) );
					}
					?>
				</a>
			<?php endif; ?>
			<div class="pmt-blog-content <?php echo $has_image ? 'yes_image' : 'no_image'; ?>">
				<?php
				foreach ( $content_order as $element ) :

					if ( 'badge' === $element ) {
						if ( $args['showCategoryBadge'] && ! empty( $display_cats ) ) :
							?>
							<div class="pmt-category-tag">
								<span class="pmt-category-tag__prefix" style="background:<?php echo esc_attr( $cat_color['fg'] ); ?>;color:<?php echo esc_attr( $cat_color['bg'] ); ?>;"><?php esc_html_e( 'In', 'pmt-post-grid' ); ?></span>
								<?php foreach ( $display_cats as $cat ) : ?>
									<?php $this_color = pmt_post_grid_get_category_color( $cat->term_id ); ?>
									<a class="pmt-category-tag__label" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" style="background:<?php echo esc_attr( $this_color['bg'] ); ?>;color:<?php echo esc_attr( $this_color['fg'] ); ?>;"><?php echo esc_html( $cat->name ); ?></a>
								<?php endforeach; ?>
							</div>
							<?php
						endif;

					} elseif ( 'title' === $element ) {
						?>
						<<?php echo esc_html( $title_tag ); ?> class="pmt-title">
							<a href="<?php the_permalink(); ?>"><?php echo esc_html( pmt_post_grid_get_trimmed_title() ); ?></a>
						</<?php echo esc_html( $title_tag ); ?>>
						<?php

					} elseif ( 'excerpt' === $element ) {
						if ( $args['showExcerpt'] ) :
							?>
							<p class="pmt-excerpt">
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
								echo '<ul class="pmt-tag-list">';
								foreach ( $tags as $tag ) {
									echo '<li><a href="' . esc_url( get_tag_link( $tag->term_id ) ) . '" style="background:' . esc_attr( $cat_color['bg'] ) . ';color:' . esc_attr( $cat_color['fg'] ) . ';">' . esc_html( $tag->name ) . '</a></li>';
								}
								echo '</ul>';
							}
						}
					}
				endforeach;

				// Meta: fixed position, always last, not reorderable --
				// same as the 'full' variant. No Read more in Design 3.
				$meta_items = array();

				if ( $args['showDate'] ) {
					$archive_link = get_day_link(
						get_the_time( 'Y' ),
						get_the_time( 'm' ),
						get_the_time( 'd' )
					);
					$display_date  = esc_html( get_the_date() );								
					$meta_items[] = '<li><span><i class="fa-regular fa-calendar"></i></span> <span class="posted-on"> '
						. '<a href="' . esc_url( $archive_link ) . '">'
						. '<time class="entry-date published updated" datetime="' . $display_date . '">' . $display_date . '</time>'
						. '</a></span></li>';
				}
				if ( $args['showComments'] ) {
					$comment_count = (int) get_comments_number( $post_id );
					$comments_link = get_comments_link( $post_id );
				
					$meta_items[] = '<li><span><i class="fa-regular fa-comment"></i></span><span class="comments-link"> '
						. '<a href="' . esc_url( $comments_link ) . '">'
						. $comment_count
						. '</a></span></li>';
				}
				if ( $args['showViews'] ) {
					$meta_items[] = '<li><span><i class="fa-regular fa-eye"></i></span><span> ' . (int) pmt_post_grid_get_views( $post_id ) . ' ' . esc_html__( 'Views', 'pmt-post-grid' ) . '</span></li>';
				}
				if ( $args['showReadingTime'] ) {
					$meta_items[] = '<li><span><i class="fa-regular fa-clock"></i></span><span> ' . (int) pmt_post_grid_get_reading_time( $post_id ) . ' ' . esc_html__( 'min read', 'pmt-post-grid' ) . '</span></li>';
				}

				if ( ! empty( $meta_items ) ) {
					echo '<ul class="pmt-extra-info" style="color:' . esc_attr( $cat_color['fg'] ) . ';">' . implode( '', $meta_items ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped above.
				}
				?>
			</div>
		</div>
		<?php
		// Related posts: fixed position, always after .pmt-blog-snippet
		// closes (a sibling, not nested inside it) -- up to 4 other
		// posts sharing this post's own category.
		echo pmt_post_grid_render_related_posts_block( $post_id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally.
		?>
		<?php
		/**
		 * Filters a single rendered card's HTML before it's returned --
		 * lets developers inject into, wrap, or otherwise modify individual
		 * card output (e.g. add a custom badge, tracking attribute, or
		 * schema markup) without touching this file. Runs for every card,
		 * in every design.
		 *
		 * @param string $html      The card's rendered HTML.
		 * @param int    $post_id   The post this card represents.
		 * @param string $variant   'full', 'simple', or 'row'.
		 * @param array  $args      The full, already-filtered settings array.
		 */
		return apply_filters( 'pmt_post_grid_card_html', ob_get_clean(), get_the_ID(), 'row', $args );
	}

	if ( 'simple' === $variant ) {
		// Filter the full Card element order down to just what this
		// lightweight variant supports (image/badge/title -- excerpt
		// and tags never apply here regardless of position), preserving
		// whatever relative order the user configured for those. The
		// category badge is controlled by the exact same "Show category
		// badge" toggle used for the full variant. Meta is NOT part of
		// this reorderable set -- it always renders last, fixed.
		$simple_order = array_values( array_intersect( $layout_order, array( 'image', 'badge', 'title' ) ) );
		if ( empty( $simple_order ) ) {
			$simple_order = array( 'image', 'badge', 'title' );
		}

		$image_is_first = 'image' === $simple_order[0];
		?>
		<div class="pmt-blog-snippet pmt-blog-snippet--simple">
			<?php if ( $args['showImage'] && $image_is_first ) : ?>
				<a class="pmt-img-holder" href="<?php the_permalink(); ?>">
					<?php
					if ( $has_image ) {
						the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'alt' => get_the_title() ) );
					}
					?>
				</a>
			<?php endif; ?>
			<div class="pmt-blog-content <?php echo $has_image ? 'yes_image' : 'no_image'; ?>">
				<?php
				foreach ( $simple_order as $element ) :

					if ( 'image' === $element ) {
						if ( $image_is_first || ! $args['showImage'] ) {
							continue;
						}
						?>
						<a class="pmt-img-holder" href="<?php the_permalink(); ?>">
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
							<div class="pmt-category-tag">
								<span class="pmt-category-tag__prefix" style="background:<?php echo esc_attr( $cat_color['fg'] ); ?>;color:<?php echo esc_attr( $cat_color['bg'] ); ?>;"><?php esc_html_e( 'In', 'pmt-post-grid' ); ?></span>
								<?php foreach ( $display_cats as $cat ) : ?>
									<?php $this_color = pmt_post_grid_get_category_color( $cat->term_id ); ?>
									<a class="pmt-category-tag__label" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" style="background:<?php echo esc_attr( $this_color['bg'] ); ?>;color:<?php echo esc_attr( $this_color['fg'] ); ?>;"><?php echo esc_html( $cat->name ); ?></a>
								<?php endforeach; ?>
							</div>
							<?php
						endif;

					} elseif ( 'title' === $element ) {
						?>
						<<?php echo esc_html( $title_tag ); ?> class="pmt-title">
							<a href="<?php the_permalink(); ?>"><?php echo esc_html( pmt_post_grid_get_trimmed_title() ); ?></a>
						</<?php echo esc_html( $title_tag ); ?>>
						<?php
					}
				endforeach;

				// Meta: fixed position, always last, not reorderable.
				if ( $args['showDate'] ) :
					?>
					<?php
					$archive_link = get_day_link( get_the_time( 'Y' ), get_the_time( 'm' ), get_the_time( 'd' ) );
					$display_date = esc_html( get_the_date() );
					?>
					<ul class="pmt-extra-info pmt-extra-info--simple" style="color:<?php echo esc_attr( $cat_color['fg'] ); ?>;">
						<li>
							<span><i class="fa-regular fa-calendar"></i></span>
							<span class="posted-on">
								<a href="<?php echo esc_url( $archive_link ); ?>">
									<time class="entry-date published updated" datetime="<?php echo $display_date; ?>"><?php echo $display_date; ?></time>
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
		/**
		 * Filters a single rendered card's HTML before it's returned --
		 * lets developers inject into, wrap, or otherwise modify individual
		 * card output (e.g. add a custom badge, tracking attribute, or
		 * schema markup) without touching this file. Runs for every card,
		 * in every design.
		 *
		 * @param string $html      The card's rendered HTML.
		 * @param int    $post_id   The post this card represents.
		 * @param string $variant   'full', 'simple', or 'row'.
		 * @param array  $args      The full, already-filtered settings array.
		 */
		return apply_filters( 'pmt_post_grid_card_html', ob_get_clean(), get_the_ID(), 'simple', $args );
	}

	// 'full' variant.
	$image_is_first = ! empty( $layout_order ) && 'image' === $layout_order[0];
	?>
	<div class="pmt-blog-snippet">
		<?php if ( $args['showImage'] && $image_is_first ) : ?>
			<a class="pmt-img-holder" href="<?php the_permalink(); ?>">
				<?php
				if ( $has_image ) {
					the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'alt' => get_the_title() ) );
				}
				?>
			</a>
		<?php endif; ?>
		<div class="pmt-blog-content <?php echo $has_image ? 'yes_image' : 'no_image'; ?>">
			<?php
			foreach ( $layout_order as $element ) :

				if ( 'image' === $element ) {
					if ( $image_is_first || ! $args['showImage'] ) {
						continue;
					}
					?>
					<a class="pmt-img-holder" href="<?php the_permalink(); ?>">
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
						<div class="pmt-category-tag">
							<span class="pmt-category-tag__prefix" style="background:<?php echo esc_attr( $cat_color['fg'] ); ?>;color:<?php echo esc_attr( $cat_color['bg'] ); ?>;"><?php esc_html_e( 'In', 'pmt-post-grid' ); ?></span>
							<?php foreach ( $display_cats as $cat ) : ?>
								<?php $this_color = pmt_post_grid_get_category_color( $cat->term_id ); ?>
								<a class="pmt-category-tag__label" href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>" style="background:<?php echo esc_attr( $this_color['bg'] ); ?>;color:<?php echo esc_attr( $this_color['fg'] ); ?>;"><?php echo esc_html( $cat->name ); ?></a>
							<?php endforeach; ?>
						</div>
						<?php
					endif;

				} elseif ( 'title' === $element ) {
					?>
					<<?php echo esc_html( $title_tag ); ?> class="pmt-title">
						<a href="<?php the_permalink(); ?>"><?php echo esc_html( pmt_post_grid_get_trimmed_title() ); ?></a>
					</<?php echo esc_html( $title_tag ); ?>>
					<?php

				} elseif ( 'excerpt' === $element ) {
					if ( $args['showExcerpt'] ) :
						?>
						<p class="pmt-excerpt">
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
							echo '<ul class="pmt-tag-list">';
							foreach ( $tags as $tag ) {
								echo '<li><a href="' . esc_url( get_tag_link( $tag->term_id ) ) . '" style="background:' . esc_attr( $cat_color['bg'] ) . ';color:' . esc_attr( $cat_color['fg'] ) . ';">' . esc_html( $tag->name ) . '</a></li>';
							}
							echo '</ul>';
						}
					}
				}
			endforeach;

			// Meta: fixed position, always after everything in layoutOrder
			// and right before Read more, not user-reorderable.
			$meta_items = array();

			if ( $args['showDate'] ) {
				$archive_link = get_day_link(
					get_the_time( 'Y' ),
					get_the_time( 'm' ),
					get_the_time( 'd' )
				);
				$display_date  = esc_html( get_the_date() );								
				$meta_items[] = '<li><span><i class="fa-regular fa-calendar"></i></span> <span class="posted-on"> '
					. '<a href="' . esc_url( $archive_link ) . '">'
					. '<time class="entry-date published updated" datetime="' . $display_date . '">' . $display_date . '</time>'
					. '</a></span></li>';
			}
			if ( $args['showComments'] ) {
				$comment_count = (int) get_comments_number( $post_id );
				$comments_link = get_comments_link( $post_id );
			
				$meta_items[] = '<li><span><i class="fa-regular fa-comment"></i></span><span class="comments-link"> '
					. '<a href="' . esc_url( $comments_link ) . '">'
					. $comment_count
					. '</a></span></li>';
			}
			if ( $args['showViews'] ) {
				$meta_items[] = '<li><span><i class="fa-regular fa-eye"></i></span><span> ' . (int) pmt_post_grid_get_views( $post_id ) . ' ' . esc_html__( 'Views', 'pmt-post-grid' ) . '</span></li>';
			}
			if ( $args['showReadingTime'] ) {
				$meta_items[] = '<li><span><i class="fa-regular fa-clock"></i></span><span> ' . (int) pmt_post_grid_get_reading_time( $post_id ) . ' ' . esc_html__( 'min read', 'pmt-post-grid' ) . '</span></li>';
			}

			if ( ! empty( $meta_items ) ) {
				echo '<ul class="pmt-extra-info" style="color:' . esc_attr( $cat_color['fg'] ) . ';">' . implode( '', $meta_items ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped above.
			}

			if ( $args['showReadMore'] ) {
				$read_more_text = ! empty( $args['readMoreText'] ) ? $args['readMoreText'] : __( 'Read More', 'pmt-post-grid' );
				?>
				<a class="pmt-read-more" href="<?php the_permalink(); ?>" style="background:<?php echo esc_attr( $cat_color['fg'] ); ?>;">
					<?php echo esc_html( $read_more_text ); ?> <span aria-hidden="true">&rarr;</span>
				</a>
				<?php
			}
			?>
		</div>
	</div>
	<?php
	/**
	 * Filters a single rendered card's HTML before it's returned --
	 * lets developers inject into, wrap, or otherwise modify individual
	 * card output (e.g. add a custom badge, tracking attribute, or
	 * schema markup) without touching this file. Runs for every card,
	 * in every design.
	 *
	 * @param string $html      The card's rendered HTML.
	 * @param int    $post_id   The post this card represents.
	 * @param string $variant   'full', 'simple', or 'row'.
	 * @param array  $args      The full, already-filtered settings array.
	 */
	return apply_filters( 'pmt_post_grid_card_html', ob_get_clean(), get_the_ID(), 'full', $args );
}

/**
 * Where the "Show more" button should link to: the selected category's
 * archive if one is set, otherwise the site's main blog listing (the
 * dedicated posts page if one is configured, or the homepage itself
 * when the front page already shows the latest posts).
 */
function pmt_post_grid_get_more_link( $category_id ) {
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
 * Renders the "Show more" button -- bottom-right of .home-section,
 * opens the archive/blog link in a new tab.
 */
function pmt_post_grid_render_show_more_button( $url ) {
	return sprintf(
		'<div class="pmt-show-more-wrap"><a class="pmt-show-more" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span aria-hidden="true">&rarr;</span></a></div>',
		esc_url( $url ),
		esc_html__( 'Show more', 'pmt-post-grid' )
	);
}

/**
 * Builds one post's structured-data summary -- called once per post as
 * each design's grid-content loop runs, gathering just enough for a
 * useful (but deliberately minimal) Article entry: full untrimmed
 * title, permalink, featured image, publish date, and author. Kept
 * minimal on purpose -- the full/rich Article schema belongs on the
 * post's own page (an SEO plugin's job), not duplicated here; this is
 * just enough for the listing page's ItemList to have real value.
 */
function pmt_post_grid_get_structured_data_item( $post_id, $position ) {
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
 * Renders the full ItemList JSON-LD block for a grid instance, from the
 * list of per-post items collected via pmt_post_grid_get_structured_data_item().
 * Output inline, right after the grid -- valid per Google's structured
 * data guidelines, which don't require JSON-LD to live in <head>.
 */
function pmt_post_grid_render_structured_data( $items ) {
	if ( empty( $items ) ) {
		return '';
	}

	$schema = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'ItemList',
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
	$schema = apply_filters( 'pmt_post_grid_structured_data', $schema, $items );

	if ( empty( $schema ) ) {
		return '';
	}

	$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES );
	if ( ! $json ) {
		return '';
	}

	// Defensive: a title/name containing a literal "</script>" could
	// otherwise prematurely close this script tag.
	$json = str_replace( '</script>', '<\/script>', $json );

	return '<script type="application/ld+json">' . $json . '</script>';
}

/**
 * Stores this grid instance's full args in a transient, keyed by a
 * freshly generated instance ID, so the category-filter AJAX endpoint
 * can look up "everything about this specific grid" from just an ID
 * (rather than trusting a full settings blob sent by the client, which
 * would be a much larger and riskier attack surface). Returns the ID.
 *
 * The transient is short-lived (12 hours) and gets refreshed every time
 * the endpoint is used, so an actively-browsed grid effectively never
 * expires, while an abandoned one cleans itself up automatically.
 */
function pmt_post_grid_store_instance( $args ) {
	$instance_id = 'i' . substr( md5( wp_json_encode( $args ) . microtime() ), 0, 16 );
	set_transient( 'pmt_grid_' . $instance_id, $args, 12 * HOUR_IN_SECONDS );
	return $instance_id;
}

/**
 * REST callback for the live category filter. Accepts only an
 * instance_id (looked up server-side, see pmt_post_grid_store_instance())
 * and the new category to switch to -- never a client-supplied settings
 * blob -- then re-renders just that instance's grid content with the
 * category swapped in.
 */
function pmt_post_grid_rest_filter_callback( WP_REST_Request $request ) {
	$instance_id = sanitize_text_field( (string) $request->get_param( 'instance_id' ) );
	$category    = sanitize_text_field( (string) $request->get_param( 'category' ) );
	$order_by    = sanitize_text_field( (string) $request->get_param( 'order_by' ) );

	if ( '' === $instance_id || ! preg_match( '/^i[a-f0-9]{16}$/', $instance_id ) ) {
		return new WP_Error( 'pmt_invalid_instance', __( 'Invalid grid instance.', 'pmt-post-grid' ), array( 'status' => 400 ) );
	}

	$args = get_transient( 'pmt_grid_' . $instance_id );
	if ( false === $args || ! is_array( $args ) ) {
		return new WP_Error( 'pmt_expired_instance', __( 'This grid has expired -- please reload the page.', 'pmt-post-grid' ), array( 'status' => 404 ) );
	}

	// Category is a numeric term ID, or '' for "All categories". Ignore
	// anything else rather than passing it through.
	$args['category'] = ( '' !== $category && ctype_digit( $category ) ) ? $category : '';

	// Order by is one of exactly two known values; anything else falls
	// back to the default rather than being passed through.
	$args['orderBy'] = ( 'comment_count' === $order_by ) ? 'comment_count' : 'date';

	// Refresh the transient under the same instance ID, extending its
	// life and keeping it in sync with whatever category/order is now
	// active.
	set_transient( 'pmt_grid_' . $instance_id, $args, 12 * HOUR_IN_SECONDS );

	if ( 'design_2' === $args['design'] ) {
		$html = pmt_post_grid_build_design2_grid_content( $args );
	} elseif ( 'design_3' === $args['design'] ) {
		$html = pmt_post_grid_build_design3_grid_content( $args );
	} else {
		$html = pmt_post_grid_build_design1_grid_content( $args );
	}

	return rest_ensure_response( array( 'html' => $html ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'pmt-post-grid/v1',
			'/filter',
			array(
				'methods'             => 'POST',
				'callback'            => 'pmt_post_grid_rest_filter_callback',
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
 * outer .home-section wrapper and no title/dropdown header. This is
 * what the category-filter AJAX endpoint re-renders and swaps in,
 * without touching the header around it.
 */
function pmt_post_grid_build_design2_grid_content( $args ) {
	$allowed_title_tags = array( 'h1', 'h2', 'h3', 'h4' );
	$title_tag           = in_array( $args['titleTag'], $allowed_title_tags, true ) ? $args['titleTag'] : 'h3';
	$layout_order         = pmt_post_grid_sanitize_layout_order( $args['layoutOrder'] );

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
			'order'               => 'DESC', // Always newest/most-relevant first -- see Order removed in 2.29.0.
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['cat'] = (int) $args['category'];
		}

		$query_args = pmt_post_grid_apply_author_and_exclusions( $query_args, $args );
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
	$query_args = apply_filters( 'pmt_post_grid_query_args', $query_args, $args, 'design_2' );

	$query = new WP_Query( $query_args );

	ob_start();

	if ( ! $query->have_posts() ) {
		?>
		<div class="pmt-post-grid-block pmt-post-grid-block--empty">
			<p><?php esc_html_e( 'No posts found for this selection.', 'pmt-post-grid' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
	?>
	<div class="pmt-design2-grid">
		<?php
		$post_index = 0;
		$col_b_rows = array( array(), array() ); // 2 rows x up to 2 posts each.
		$structured_data_items = array();

		while ( $query->have_posts() && $post_index < 5 ) :
			$query->the_post();
			$structured_data_items[] = pmt_post_grid_get_structured_data_item( get_the_ID(), $post_index + 1 );

			if ( 0 === $post_index ) {
				?>
				<div class="pmt-design2-col-a">
					<?php echo pmt_post_grid_render_card( $args, $layout_order, $title_tag, 'full' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally. ?>
				</div>
				<div class="pmt-design2-col-b">
				<?php
			} else {
				$slot = $post_index - 1;      // 0..3
				$row  = (int) floor( $slot / 2 ); // 0 or 1
				ob_start();
				echo pmt_post_grid_render_card( $args, $layout_order, $title_tag, 'simple' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally.
				$col_b_rows[ $row ][] = ob_get_clean();
			}

			$post_index++;
		endwhile;

		foreach ( $col_b_rows as $row_cards ) {
			echo '<div class="pmt-design2-row">';
			for ( $cell_index = 0; $cell_index < 2; $cell_index++ ) {
				if ( isset( $row_cards[ $cell_index ] ) ) {
					echo '<div class="pmt-design2-cell">' . $row_cards[ $cell_index ] . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally.
				} else {
					echo '<div class="pmt-design2-cell pmt-design2-cell--empty" aria-hidden="true"></div>';
				}
			}
			echo '</div>';
		}
		?>
		</div>
	</div>
	<?php
	if ( empty( $post_ids ) && (int) $query->found_posts > 4 ) {
		echo pmt_post_grid_render_show_more_button( pmt_post_grid_get_more_link( $args['category'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally.
	}

	if ( ! empty( $args['showStructuredData'] ) ) {
		echo pmt_post_grid_render_structured_data( $structured_data_items ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally.
	}

	wp_reset_postdata();

	return ob_get_clean();
}

/**
 * Design 2: golden-ratio two-column layout, 5 posts total.
 * Column A (38.2% width): one post, full card (same as Design 1).
 * Column B (61.8% width): the next 4 posts, in a 2x2 grid of simplified
 * cards (image + title + date only).
 *
 * Uses the same query/selection rules as Design 1 (specific posts
 * override category/order), just capped to 5 posts total regardless of
 * postsToShow, since the layout itself requires exactly that many slots.
 */
function pmt_post_grid_build_design2_markup( $args ) {
	$style_vars = pmt_post_grid_compute_style_vars( $args );
	$align      = in_array( $args['contentAlign'], array( 'left', 'center', 'right' ), true ) ? $args['contentAlign'] : 'left';

	$has_specific_posts = ! empty( $args['postIds'] ) && is_array( $args['postIds'] ) && count( array_filter( $args['postIds'], function ( $v ) {
		return (int) $v > 0;
	} ) ) > 0;

	$instance_id = pmt_post_grid_store_instance( $args );

	ob_start();
	?>
	<div class="home-section pmt-thumb-blog pmt-post-grid-block pmt-design2 pmt-align-<?php echo esc_attr( $align ); ?><?php echo esc_attr( $args['align_class'] ); ?>" style="<?php echo esc_attr( $style_vars ); ?>">
		<?php echo pmt_post_grid_render_section_header( $args, $instance_id, $has_specific_posts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally. ?>
		<div class="pmt-grid-content" data-pmt-instance="<?php echo esc_attr( $instance_id ); ?>">
			<?php echo pmt_post_grid_build_design2_grid_content( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally. ?>
		</div>
	</div>
	<?php
	/**
	 * Filters the complete rendered output of a grid instance, right
	 * before it's returned -- the whole thing: wrapper, header,
	 * dropdown, every card, Show more button. Lets developers wrap the
	 * entire block/widget in something else, inject markup around it,
	 * or log/cache the final HTML, without touching this file.
	 *
	 * @param string $html   The complete rendered markup.
	 * @param array  $args   The full, already-filtered settings array.
	 * @param string $design Always 'design_2' here.
	 */
	return apply_filters( 'pmt_post_grid_output', ob_get_clean(), $args, 'design_2' );
}

function pmt_post_grid_build_markup( $args ) {
	$defaults = array(
		'design'            => 'design_1',
		'columns'           => 2,
		'postsToShow'       => 4,
		'category'          => '',
		'orderBy'           => 'date',
		'postIds'           => array(),
		'author'            => '',
		'excludePostIds'    => array(),
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
	 * Filters the fully-merged args array before it's used for anything --
	 * runs before the design is even dispatched, so a filter here can
	 * change $args['design'] itself, override any individual setting, or
	 * inject entirely new keys for a filter added elsewhere (e.g. on
	 * 'pmt_post_grid_query_args') to read back out. This is the one hook
	 * that reaches every code path: Design 1, 2, and 3, both builders.
	 *
	 * @param array $args The fully-merged settings array (every default
	 *                     already applied).
	 */
	$args = apply_filters( 'pmt_post_grid_args', $args );

	if ( 'design_2' === $args['design'] ) {
		return pmt_post_grid_build_design2_markup( $args );
	}

	if ( 'design_3' === $args['design'] ) {
		return pmt_post_grid_build_design3_markup( $args );
	}

	$style_vars = pmt_post_grid_compute_style_vars( $args );
	$align      = in_array( $args['contentAlign'], array( 'left', 'center', 'right' ), true ) ? $args['contentAlign'] : 'left';

	$has_specific_posts = ! empty( $args['postIds'] ) && is_array( $args['postIds'] ) && count( array_filter( $args['postIds'], function ( $v ) {
		return (int) $v > 0;
	} ) ) > 0;

	$instance_id = pmt_post_grid_store_instance( $args );

	ob_start();
	?>
	<div class="home-section pmt-thumb-blog pmt-grid-column-block pmt-post-grid-block pmt-align-<?php echo esc_attr( $align ); ?><?php echo esc_attr( $args['align_class'] ); ?>" style="<?php echo esc_attr( $style_vars ); ?>">
		<?php echo pmt_post_grid_render_section_header( $args, $instance_id, $has_specific_posts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally. ?>
		<div class="pmt-grid-content" data-pmt-instance="<?php echo esc_attr( $instance_id ); ?>">
			<?php echo pmt_post_grid_build_design1_grid_content( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally. ?>
		</div>
	</div>
	<?php
	/** This filter is documented in pmt_post_grid_build_design2_markup(). */
	return apply_filters( 'pmt_post_grid_output', ob_get_clean(), $args, 'design_1' );
}

/**
 * Design 1's grid content only -- the responsive row of cards, no outer
 * .home-section wrapper and no title/dropdown header. This is what the
 * category-filter AJAX endpoint re-renders and swaps in, without
 * touching the header around it.
 */
function pmt_post_grid_build_design1_grid_content( $args ) {
	$allowed_title_tags = array( 'h1', 'h2', 'h3', 'h4' );
	$title_tag           = in_array( $args['titleTag'], $allowed_title_tags, true ) ? $args['titleTag'] : 'h3';
	$layout_order         = pmt_post_grid_sanitize_layout_order( $args['layoutOrder'] );

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
		// Specific posts chosen: show exactly those, in the order picked.
		// Category/orderBy/order/postsToShow are intentionally ignored.
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
			'order'               => 'DESC', // Always newest/most-relevant first -- see Order removed in 2.29.0.
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['cat'] = (int) $args['category'];
		}

		$query_args = pmt_post_grid_apply_author_and_exclusions( $query_args, $args );
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
	$query_args = apply_filters( 'pmt_post_grid_query_args', $query_args, $args, 'design_1' );

	$query = new WP_Query( $query_args );

	$columns   = max( 1, (int) $args['columns'] );
	$col_width = max( 1, (int) round( 12 / $columns ) );
	$col_class = 'pmt-col-lg-' . $col_width . ' pmt-col-md-6 pmt-col-sm-6';

	ob_start();

	if ( ! $query->have_posts() ) {
		?>
		<div class="pmt-post-grid-block pmt-post-grid-block--empty">
			<p><?php esc_html_e( 'No posts found for this selection.', 'pmt-post-grid' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
	?>
	<div class="pmt-row">
		<?php
		$structured_data_items = array();
		$position              = 0;
		while ( $query->have_posts() ) :
			$query->the_post();
			$position++;
			$structured_data_items[] = pmt_post_grid_get_structured_data_item( get_the_ID(), $position );
			?>
			<div class="<?php echo esc_attr( $col_class ); ?>">
				<?php echo pmt_post_grid_render_card( $args, $layout_order, $title_tag, 'full' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally. ?>
			</div>
		<?php endwhile; ?>
	</div>
	<?php
	if ( empty( $post_ids ) && (int) $query->found_posts > (int) $args['postsToShow'] ) {
		echo pmt_post_grid_render_show_more_button( pmt_post_grid_get_more_link( $args['category'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally.
	}

	if ( ! empty( $args['showStructuredData'] ) ) {
		echo pmt_post_grid_render_structured_data( $structured_data_items ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally.
	}

	wp_reset_postdata();

	return ob_get_clean();
}

/**
 * Design 3: stacked rows, each post split image + content at the golden
 * ratio (38.2% / 61.8%), image always its own column, content ordered
 * via the same shared layoutOrder as Design 1 (minus image itself,
 * which is structurally fixed to one side). No "Read more" button --
 * intentionally excluded from this design, not just toggled off.
 */
function pmt_post_grid_build_design3_markup( $args ) {
	$style_vars = pmt_post_grid_compute_style_vars( $args );
	$align      = in_array( $args['contentAlign'], array( 'left', 'center', 'right' ), true ) ? $args['contentAlign'] : 'left';

	$has_specific_posts = ! empty( $args['postIds'] ) && is_array( $args['postIds'] ) && count( array_filter( $args['postIds'], function ( $v ) {
		return (int) $v > 0;
	} ) ) > 0;

	$instance_id = pmt_post_grid_store_instance( $args );

	ob_start();
	?>
	<div class="home-section pmt-thumb-blog pmt-post-grid-block pmt-design3 pmt-align-<?php echo esc_attr( $align ); ?><?php echo esc_attr( $args['align_class'] ); ?>" style="<?php echo esc_attr( $style_vars ); ?>">
		<?php echo pmt_post_grid_render_section_header( $args, $instance_id, $has_specific_posts ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally. ?>
		<div class="pmt-grid-content" data-pmt-instance="<?php echo esc_attr( $instance_id ); ?>">
			<?php echo pmt_post_grid_build_design3_grid_content( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally. ?>
		</div>
	</div>
	<?php
	/** This filter is documented in pmt_post_grid_build_design2_markup(). */
	return apply_filters( 'pmt_post_grid_output', ob_get_clean(), $args, 'design_3' );
}

/**
 * Design 3's grid content only -- the stacked list of image+content
 * rows, no outer .home-section wrapper and no title/dropdown header.
 * This is what the category-filter AJAX endpoint re-renders and swaps
 * in, without touching the header around it.
 */
function pmt_post_grid_build_design3_grid_content( $args ) {
	$allowed_title_tags = array( 'h1', 'h2', 'h3', 'h4' );
	$title_tag           = in_array( $args['titleTag'], $allowed_title_tags, true ) ? $args['titleTag'] : 'h3';
	$layout_order         = pmt_post_grid_sanitize_layout_order_design3( $args['layoutOrderDesign3'] );
	$posts_to_show        = max( 1, (int) $args['design3PostsToShow'] );

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
			'order'               => 'DESC', // Always newest/most-relevant first -- see Order removed in 2.29.0.
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['cat'] = (int) $args['category'];
		}

		$query_args = pmt_post_grid_apply_author_and_exclusions( $query_args, $args );
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
	$query_args = apply_filters( 'pmt_post_grid_query_args', $query_args, $args, 'design_3' );

	$query = new WP_Query( $query_args );

	ob_start();

	if ( ! $query->have_posts() ) {
		?>
		<div class="pmt-post-grid-block pmt-post-grid-block--empty">
			<p><?php esc_html_e( 'No posts found for this selection.', 'pmt-post-grid' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
	?>
	<div class="pmt-design3-list">
		<?php
		$row_index = 0;
		$structured_data_items = array();
		while ( $query->have_posts() ) :
			$query->the_post();
			$row_index++;
			$structured_data_items[] = pmt_post_grid_get_structured_data_item( get_the_ID(), $row_index );
			?>
			<div class="pmt-design3-item">
				<?php echo pmt_post_grid_render_card( $args, $layout_order, $title_tag, 'row', $row_index - 1 ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally. ?>
			</div>
			<?php
		endwhile;
		?>
	</div>
	<?php
	if ( empty( $post_ids ) && (int) $query->found_posts > $posts_to_show ) {
		echo pmt_post_grid_render_show_more_button( pmt_post_grid_get_more_link( $args['category'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally.
	}

	if ( ! empty( $args['showStructuredData'] ) ) {
		echo pmt_post_grid_render_structured_data( $structured_data_items ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped internally.
	}

	wp_reset_postdata();

	return ob_get_clean();
}

/**
 * Server-side render for the pmt/post-grid Gutenberg block. Thin wrapper
 * around pmt_post_grid_build_markup() -- normalizes the block's align
 * attribute into a class string, then hands off to the shared builder.
 */
function pmt_post_grid_render_callback( $attributes ) {
	$align_class = '';
	if ( ! empty( $attributes['align'] ) ) {
		$align_class = ' align' . sanitize_html_class( $attributes['align'] );
	}
	$attributes['align_class'] = $align_class;

	return pmt_post_grid_build_markup( $attributes );
}

add_action(
	'init',
	function () {
		wp_register_script(
			'pmt-post-grid-editor',
			PMT_POST_GRID_URL . 'block/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-api-fetch', 'wp-i18n' ),
			PMT_POST_GRID_VER,
			true
		);

		wp_register_script(
			'pmt-post-grid-frontend-filter',
			PMT_POST_GRID_URL . 'block/frontend-filter.js',
			array(),
			PMT_POST_GRID_VER,
			true
		);
		wp_localize_script(
			'pmt-post-grid-frontend-filter',
			'pmtPostGridData',
			array(
				'restUrl' => esc_url_raw( rest_url( 'pmt-post-grid/v1/filter' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_register_style(
			'pmt-post-grid-editor-style',
			PMT_POST_GRID_URL . 'block/editor.css',
			array( 'wp-edit-blocks' ),
			PMT_POST_GRID_VER
		);

		wp_register_style(
			'pmt-post-grid-style',
			PMT_POST_GRID_URL . 'block/style.css',
			array( 'dashicons' ),
			PMT_POST_GRID_VER
		);

		wp_register_style(
			'pmt-post-grid-fontawsome-style',
			PMT_POST_GRID_URL . 'block/fontawesome.css',
			array(),
			PMT_POST_GRID_VER
		);
		wp_register_style(
			'pmt-post-grid-fontawsome-regular-style',
			PMT_POST_GRID_URL . 'block/regular.css',
			array(),
			PMT_POST_GRID_VER
		);
		// wp_register_style(
		// 	'pmt-post-grid-fontawsome-solid-style',
		// 	PMT_POST_GRID_URL . 'block/solid.css',
		// 	array(),
		// 	PMT_POST_GRID_VER
		// );
		// wp_register_style(
		// 	'pmt-post-grid-fontawsome-brands-style',
		// 	PMT_POST_GRID_URL . 'block/brands.css',
		// 	array(),
		// 	PMT_POST_GRID_VER
		// );

		register_block_type(
			'pmt/post-grid',
			array(
				'editor_script'   => 'pmt-post-grid-editor',
				'editor_style'    => array( 'pmt-post-grid-editor-style', 'pmt-post-grid-fontawsome-style', 'pmt-post-grid-fontawsome-regular-style' ),
				'style'           => array( 'pmt-post-grid-style', 'pmt-post-grid-fontawsome-style', 'pmt-post-grid-fontawsome-regular-style' ),
				'script'          => 'pmt-post-grid-frontend-filter',
				'render_callback' => 'pmt_post_grid_render_callback',
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
		wp_enqueue_style( 'pmt-post-grid-fontawsome-style' );
		wp_enqueue_style( 'pmt-post-grid-fontawsome-regular-style' );

	}
);

/**
 * Elementor support.
 *
 * Registers the same post grid as an Elementor widget, sharing the exact
 * markup builder (pmt_post_grid_build_markup()) used by the Gutenberg
 * block above -- so results are identical regardless of which builder a
 * page uses. Entirely inert if Elementor isn't installed/active.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, '3.5.0', '>=' ) ) {
			// Elementor 3.5+: current widget registration API. This hook
			// is fired by Elementor itself only after its own classes
			// (including Widget_Base) are fully loaded, so requiring the
			// widget file here -- rather than earlier -- is what actually
			// avoids the "Class not found" fatal.
			add_action(
				'elementor/widgets/register',
				function ( $widgets_manager ) {
					require_once PMT_POST_GRID_PATH . 'includes/class-pmt-post-grid-elementor-widget.php';
					if ( class_exists( 'PMT_Post_Grid_Elementor_Widget' ) ) {
						$widgets_manager->register( new PMT_Post_Grid_Elementor_Widget() );
					}
				}
			);
		} else {
			// Elementor < 3.5: legacy widget registration API.
			add_action(
				'elementor/widgets/widgets_registered',
				function ( $widgets_manager ) {
					require_once PMT_POST_GRID_PATH . 'includes/class-pmt-post-grid-elementor-widget.php';
					if ( class_exists( 'PMT_Post_Grid_Elementor_Widget' ) ) {
						$widgets_manager->register_widget_type( new PMT_Post_Grid_Elementor_Widget() );
					}
				}
			);
		}

		// Optional: group the widget under its own category in Elementor's panel.
		add_action(
			'elementor/elements/categories_registered',
			function ( $elements_manager ) {
				$elements_manager->add_category(
					'postmagthemes',
					array(
						'title' => __( 'Postmagthemes', 'pmt-post-grid' ),
						'icon'  => 'eicon-posts-grid',
					)
				);
			}
		);
	}
);