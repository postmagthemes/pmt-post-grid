=== Post Grid for Gutenberg and Elementor ===
Contributors: postmagthemes
Tags: gutenberg, elementor, post grid, blocks, widget
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 2.34.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A dynamic post grid available as both a Gutenberg block and an Elementor widget, sharing one PHP renderer so both stay visually identical.

== Description ==

Post Grid for Gutenberg and Elementor adds a live, configurable post grid to your site -- as a Gutenberg block, an Elementor widget, or both. Both builders share the same underlying render function, so a grid built in one looks identical to a grid built in the other, and every setting behaves the same way regardless of which editor you use.

**Three layouts**

* **Design 1** -- a standard responsive grid (2-6 columns, any number of posts).
* **Design 2** -- a golden-ratio featured layout: one large featured post alongside a 2x2 grid of four smaller posts.
* **Design 3** -- a stacked list, each post split into an image and content column at the golden ratio, with an optional alternating image side.

**Query controls**

* Filter by category, author, or hand-pick specific posts by title.
* Exclude specific posts from the results.
* Order by date or comment count.
* Set columns and post count (Design 1).
* A live, front-end category dropdown lets visitors instantly switch which category is shown, with no page reload.

**Content controls**

* Toggle featured image, excerpt (with adjustable word length), tags, and category badge independently.
* Auto-colored category badges -- each category is assigned a distinct color automatically, no configuration required.
* Optional "Read more" button and an optional main section title, both independently configurable.
* Reorder each card's elements (image, category, title, excerpt, meta, tags) via a simple drag-to-reorder list.

**Style controls**

* Column and row spacing, image corner radius, card corner radius.
* Full box-shadow control (offset, blur, spread, color, inset).
* Optional card border (width, style, color).
* Title font size, with an automatic scale for the smaller cards in Design 2.
* Left/center/right text alignment.

**"Show more" button**

Automatically appears when there are more matching posts than the grid currently displays, linking to the relevant category archive (or your site's main blog page) in a new tab.

**Built to stay in sync**

Every setting is wired through shared PHP functions rather than duplicated per builder or per layout, so a fix or feature added in one place applies everywhere -- Design 1, Design 2, Gutenberg, and Elementor can't drift out of sync with each other.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install directly through the Plugins screen in your WordPress admin.
2. Activate the plugin.
3. **Gutenberg**: in the block editor, add the "Post Grid" block and configure it in the sidebar.
4. **Elementor**: with Elementor active, drag the "Post Grid" widget into your layout and configure it in the left panel. The widget only registers itself if Elementor is installed and active -- no errors or dependency warnings if it isn't.

== Frequently Asked Questions ==

= Does this work with both Gutenberg and Elementor? =

Yes -- as two separate integrations (a block and a widget) built on the same shared rendering code, so results are visually identical either way. You can use either one, or both on different pages of the same site.

= Is the grid static or does it update automatically? =

It's fully dynamic. The grid queries your posts live on every page load -- publish a new post, or edit an existing one, and the grid reflects that automatically. Nothing needs to be re-saved.

= Does it work without Elementor installed? =

Yes. The Gutenberg block works entirely independently. The Elementor widget only registers itself if Elementor is active; with Elementor inactive or not installed, that part of the plugin is simply inert.

= How are category badge colors chosen? =

Automatically. Each category is deterministically assigned one of several preset colors based on its ID, so the same category always gets the same color across your site with no setup required.

= Can I show a specific set of posts instead of a category feed? =

Yes -- use the "Specific posts" picker to choose exact posts by title. When any are selected, they completely override the category/order settings and display in the order you picked them.

= Can I customize this plugin's behavior from my theme or another plugin, without editing its files? =

Yes -- see "Developer hooks" below.

== Developer hooks ==

This plugin fires several `apply_filters()` hooks, so themes and other plugins can extend or override its behavior without editing its files directly (changes to plugin files are lost on update; filters aren't).

**`pmt_post_grid_args`**

Filters the fully-merged settings array before anything is rendered -- runs before the design is even dispatched, so it can change `$args['design']` itself or override any individual setting. Fires once per grid instance, covering every design and both builders.

`apply_filters( 'pmt_post_grid_args', array $args )`

    add_filter( 'pmt_post_grid_args', function( $args ) {
        $args['showExcerpt'] = true; // Force excerpts on, site-wide.
        return $args;
    } );

**`pmt_post_grid_query_args`**

Filters the `WP_Query` arguments right before the query runs -- add a `meta_query`, a `tax_query`, restrict/expand post types, etc.

`apply_filters( 'pmt_post_grid_query_args', array $query_args, array $args, string $design )`

    add_filter( 'pmt_post_grid_query_args', function( $query_args, $args, $design ) {
        $query_args['meta_key']   = 'featured';
        $query_args['meta_value'] = '1';
        return $query_args;
    } );

**`pmt_post_grid_card_html`**

Filters a single rendered card's HTML before it's returned. Fires for every card, in every design.

`apply_filters( 'pmt_post_grid_card_html', string $html, int $post_id, string $variant, array $args )`

`$variant` is `'full'` (Design 1 / Design 2's Column A), `'simple'` (Design 2's Column B), or `'row'` (Design 3).

**`pmt_post_grid_category_color`**

Filters the color pair (`array( 'bg' => '#hex', 'fg' => '#hex' )`) used for a category's badge, meta text, tags, and Read more button. Override the auto-rotated palette with your own brand colors.

`apply_filters( 'pmt_post_grid_category_color', array $color, int $term_id )`

    add_filter( 'pmt_post_grid_category_color', function( $color, $term_id ) {
        if ( 5 === $term_id ) { // Your "News" category's term_id.
            return array( 'bg' => '#FFE0E0', 'fg' => '#8A1F1F' );
        }
        return $color;
    }, 10, 2 );

**`pmt_post_grid_output`**

Filters the complete rendered output of a grid instance right before it's returned -- the entire thing: wrapper, header, dropdown, every card, Show more button.

`apply_filters( 'pmt_post_grid_output', string $html, array $args, string $design )`

**`pmt_post_grid_structured_data`**

Filters the schema.org `ItemList` array before it's serialized to JSON-LD -- add/remove fields, or return an empty array to suppress it entirely for a specific instance.

`apply_filters( 'pmt_post_grid_structured_data', array $schema, array $items )`

== Screenshots ==

1. Design 1 -- standard responsive grid.
2. Design 2 -- golden-ratio featured layout.
3. Block editor settings panel (Gutenberg).
4. Widget settings panel (Elementor).

== Changelog ==

= 2.34.0 =
* Added two independent toggles (both builders), in the Layout panel/section right after the Design dropdown: "Show sort filter" and "Show category filter" -- lets you hide either (or both) of the live front-end dropdowns without disabling the other. Both still respect the existing rule of hiding automatically when Specific posts is active.

= 2.33.4 =
* Fixed height not shrinking when related posts are fewer than 4 (with the "Show related post image" toggle off). The empty placeholder slots always kept their `aspect-ratio: 1` square shape regardless of the toggle -- so even with images off, those empty slots stayed tall, and flex's `align-items: stretch` then forced the real title-only items back up to match them. Empty placeholders now only get the square `.pmt-related-post--empty` treatment when images are actually being shown; otherwise they stay unstyled/collapsed, so the whole row (real items and empty slots alike) shrinks together.

= 2.33.3 =
* Reverted to 2.33.0 behavior for related post images: the `.pmt-related-post__image` container renders whenever the "Show related post image" toggle is on, regardless of whether that specific post has a thumbnail (default behavior restored). The only condition that shrinks a related post's height now is the toggle itself being off entirely -- in that case the image container never renders at all, so there's no reserved image space.

= 2.33.2 =
* Reverted the `align-items: flex-start` added in 2.33.1 -- it stopped stretching entirely, which was wrong for mixed rows (a title-only item next to siblings with images should still stretch to match them). The actual fix was the other half of 2.33.1 (not rendering the phantom empty image div for posts with no thumbnail) -- with that in place, default `align-items: stretch` already does the right thing on its own: stretches to match a taller sibling when one exists, and naturally stays short when no sibling in the row has an image at all (nothing tall to stretch to).

= 2.33.1 =
* Fixed related posts without a featured image taking up more height than just their title. Two causes:
  1. `.pmt-related-post__image` rendered whenever the global "Show related post image" toggle was on, regardless of whether that specific post actually had a thumbnail -- a post with no image still got an empty `<div>` reserving a full square's worth of space via `aspect-ratio: 1`. Now only renders when the post actually has a featured image.
  2. Even after fixing that, a title-only item would still stretch to match taller sibling images in the same row (flex's default `align-items: stretch`). Added `align-items: flex-start` to `.pmt-related-posts__list` so each item's height is its own, not forced to match neighbors.

= 2.33.0 =
* Added `min-height: 40px` to the post title in Design 3 only (`.pmt-blog-snippet--row .pmt-title`), both files.
* Related post titles (Design 3) now trim to 6 words with an ellipsis, matching the existing 9-word trim already used for the main title.
* Fixed a double-escaping bug this surfaced: both title-trim functions passed the HTML entity string `'&hellip;'` to `wp_trim_words()`, but their output is wrapped in `esc_html()` at every call site -- which encodes the `&` in `&hellip;` into `&amp;hellip;`, displaying as literal visible text ("&hellip;") instead of an ellipsis, whenever a title actually got trimmed. Switched both to the actual UTF-8 ellipsis character (`'…'`), which `esc_html()` doesn't touch. Excerpt trimming (a separate code path using `wp_kses_post()`, not `esc_html()`) doesn't have this problem and was left unchanged.

= 2.32.1 =
* Fixed empty related-post placeholders (fewer than 4 related posts found) no longer showing blank space -- the `.pmt-related-post` selector had been changed to `a.pmt-related-post` (both files), restricting the flex/sizing rules to `<a>` elements only. Real related-post items are `<a>` tags, but the empty placeholders are `<div>` elements, so they'd stopped matching the rule entirely and lost the `flex: 1 1 calc(25% - 9px)` sizing that made them take up their share of the row. Reverted to the plain `.pmt-related-post` class selector so both match again.

= 2.32.0 =
* Related post cards now get box-shadow, border, and border-radius, using the exact same `--pmt-box-shadow`/`--pmt-box-border`/`--pmt-box-radius-top`/`--pmt-box-radius-bottom` CSS vars as `.pmt-blog-snippet` -- no new controls, the existing Style panel/section already governs this.
* Related post images now get `margin: 2px 2px 2px 2px`, matching `.pmt-img-holder`. Also removed `width: 100%` from the image while doing this -- keeping it would have reintroduced the exact width+margin overflow bug already found and fixed for `.pmt-img-holder` earlier in this plugin (a block element with `width: 100%` doesn't leave room for its own margins). The image still fills its container correctly via the parent's default `align-items: stretch`, same mechanism `.pmt-img-holder` already relies on.

= 2.31.2 =
* Related posts now pad up to 4 total with empty placeholder slots (dashed square boxes) when fewer than 4 are found -- same pattern already used for Design 2's Column B. Still renders nothing at all when zero related posts are found (e.g. an uncategorized post).

= 2.31.1 =
* Related posts now render after `.pmt-blog-snippet` closes (a sibling of it), not nested inside `.pmt-blog-content`.
* Fixed related post images not actually rendering square. Same CSS specificity trap hit before in this plugin: the general `img {}` rule is nested inside `.pmt-thumb-blog.pmt-post-grid-block`, giving it higher specificity (2 classes + element) than the standalone `.pmt-related-post__image img` override (1 class + element) -- so none of that override's properties, including `aspect-ratio: 1`, were actually taking effect. Moved the override inside the same nested block, matching the existing pattern already used for `.pmt-design2-col-a img`.

= 2.31.0 =
* Added **related posts** to Design 3: up to 4 other posts sharing that row's own category, shown below each row's content (square image + title, image always first). Not part of the reorderable Card element order -- fixed position, always last, image before title.
* New controls (both builders), positioned right after Card element order, visible only for Design 3: "Related post title" (a text field for the section label -- leave empty to hide just the label), "Show related post image", "Show related post title".
* Related post titles automatically use one heading level below the parent row's own title tag (h3 parent -> h4 related, etc.), capped at h6 -- not user-configurable, always automatic.
* Related post title size defaults to 80% of the parent title's size, now adjustable via a new "Related post title size (% of parent)" control in Typography -- visible only when Design 3 is selected.
* Fixed a Typography panel/section bug: "Column B title size (% of Column A)" was showing regardless of which design was active, even Design 1 where Column B doesn't exist. Now only shows for Design 2, matching the new Design-3-only control's same conditional pattern.

= 2.30.0 =
* Added a second live, front-end dropdown -- "Most recent" / "Most commented" -- sitting in the same row as the category dropdown, as its own separate `<select>` (not merged into one list). Same mechanism: an AJAX request re-renders just the grid content, no page reload. Hidden when Specific posts is active, same as the category dropdown.
* Both dropdowns now work together correctly: changing one always includes the other's current value in the request, so switching sort order doesn't reset an active category filter, and vice versa.
* REST endpoint (`pmt-post-grid/v1/filter`) extended to accept an `order_by` parameter alongside `category`.
* New shared `.pmt-filter-controls` wrapper holds both dropdowns as a group, right-aligned via `margin-left: auto` (previously that margin lived directly on the category dropdown's own wrapper).

= 2.29.0 =
* Removed the "Order" (ascending/descending) control from the Query panel/section entirely, in both builders -- results are now always descending (newest first, or most-commented first when "Order by: Comment count" is selected). "Order by" itself is unchanged and still configurable. Removed from the shared PHP defaults, Gutenberg's attribute schema, and Elementor's control + render() mapping; hardcoded to `DESC` in all three designs' queries.

= 2.28.2 =
* Removed another static inline style: `style="color:inherit;"` on the meta row's date/comment links (5 call sites) -- entirely redundant, since `.pmt-extra-info a` already sets `color: inherit;` in the stylesheet (both files). Same category of fix as 2.28.1.

= 2.28.1 =
* Removed a static inline style flagged as a WordPress.org compliance concern: `border-radius:var(--pmt-box-radius-top, 0px);` was repeated inline on every category badge/label across 6 call sites -- moved into the existing `.pmt-category-tag__prefix`/`.pmt-category-tag__label` CSS rules (both files) instead, since it's the same value every time and doesn't need to be computed per-post. Genuinely dynamic inline styles (e.g. `background`/`color`, which vary per category) are left as-is -- those can't be a static class since the actual color differs per post. Fixed once in the shared `pmt_post_grid_render_card()` function, so both Gutenberg and Elementor are covered automatically (there was no separate copy to fix in the Elementor widget file).

= 2.28.0 =
* Replaced the opacity-fade loading state (during the live category filter's AJAX request) with real skeleton placeholders -- shimmering gray shapes matching whichever design is currently active (Design 1's grid/column layout, Design 2's golden-ratio 1+4, or Design 3's stacked rows), detected from the existing DOM so no extra data needs passing from PHP. The previous content is restored automatically if the request fails, rather than being left on a stuck skeleton or blank.

= 2.27.0 =
* Added JSON-LD structured data (schema.org `ItemList`, with a minimal `Article` per post: headline, url, image, datePublished, author) for real SEO value -- a rich, machine-readable summary of what each grid instance is actually showing. New "Add structured data (SEO)" toggle (both builders, default on) to disable per-instance if your SEO plugin already outputs its own listing schema for that page. Deliberately minimal per-post data -- the full/rich Article schema belongs on the post's own page, not duplicated on the listing page. New `pmt_post_grid_structured_data` filter for developers to customize or remove the schema per instance.

= 2.26.1 =
* Changed default Row margin from 30 to 15 (space between rows). Also fixed an inconsistency this surfaced: Elementor's own Row margin control already defaulted to 15 (with "Default: 15" in its own description), but three other places -- the shared PHP defaults, the Gutenberg attribute schema, and Elementor's `render()` fallback value -- all still said 30. All four now consistently say 15.

= 2.26.0 =
* Added five developer filter hooks so themes/other plugins can extend this plugin without editing its files: `pmt_post_grid_args` (the full settings array), `pmt_post_grid_query_args` (the WP_Query args, tagged by design), `pmt_post_grid_card_html` (a single card's output, tagged by variant), `pmt_post_grid_category_color` (the auto-rotated color pair, per category), and `pmt_post_grid_output` (the complete rendered instance). Documented in a new "Developer hooks" readme section with usage examples.

= 2.25.1 =
* Removed `aspect-ratio: auto` from `.pmt-blog-snippet--row .pmt-img-holder img` (both CSS files).
* Design 3's image is now genuinely non-reorderable, not just structurally ignored: it has its own separate "Card element order" setting (`layoutOrderDesign3`) with no "Featured image" option in the list at all, in both builders -- rather than reusing Design 1's control (where image was a selectable, if functionally inert, row). Elementor needed a second, independent REPEATER control for this (condition-gated to Design 3), since Elementor's repeater can't conditionally hide a single row within a shared control based on another setting's value.

= 2.25.0 =
* Added **Design 3**: a stacked list of posts, each split into an image and content column at the golden ratio (38.2% / 61.8%), image always its own column. Content order (category/title/excerpt/tags) uses the same shared Card element order as Design 1; meta info is fixed last, same as elsewhere. No "Read more" button in this design -- excluded by design, not a toggle.
* New "Number of posts" control specific to Design 3 (separate from Design 1's, default 3), with the same "Show more" behavior -- appears when more matching posts exist than this count, linking to the category/blog archive.
* New "Alternate image side" toggle (Layout panel/section, visible only for Design 3): when on, odd rows show image-left, even rows image-right; off (default) keeps every row image-left.

= 2.24.1 =
* Fixed a PHP deprecation warning ("`who` is deprecated since 5.9.0") in the Elementor widget's Author list -- `get_users( array( 'who' => 'authors' ) )` replaced with `get_users( array( 'capability' => array( 'edit_posts' ) ) )`, the officially recommended equivalent. Gutenberg's REST-based author fetch (`/wp/v2/users?who=authors`) was unaffected -- that's a separate, still-supported REST API parameter, not the deprecated `WP_User_Query` one.

= 2.24.0 =
* Added an **Author filter** dropdown (Query section, both builders) -- filters the grid to a specific author, alongside the existing Category filter. Ignored while Specific posts is active, same as Category.
* Added an **Exclude posts** picker (both builders) -- the inverse of the existing "Specific posts" picker: instead of showing only chosen posts, checked posts here are removed from whatever the Category/Author/Order query would otherwise return. Also ignored while Specific posts is active (there's nothing to exclude from an exact list).

= 2.23.1 =
* Changed `.pmt-tag-list` margin from `10px 0 10px` to `5px 0 10px` (both files).

= 2.23.0 =
* Changed the default card element order to: image, category badge, title, excerpt, tags -- with meta info and the "Read more" button always rendering last, in that order, after everything else.
* Meta info removed from the reorderable "Card element order" list entirely (both builders) -- it's no longer draggable/repositionable. It now always renders at a fixed position (right before Read more), regardless of how the remaining elements are arranged.

= 2.22.4 =
* Added `line-height: 1;` and `min-height: auto;` to the category filter dropdown, overriding WP admin's default select sizing.

= 2.22.3 =
* Fixed the category filter dropdown getting overridden by WordPress admin's own `.wp-core-ui select` styling in the Gutenberg editor -- that selector (class + element) is technically more specific than a lone `.pmt-category-filter` class, regardless of CSS load order. Bumped our selector to `.pmt-category-filter-wrap .pmt-category-filter` (two classes), which reliably wins.

= 2.22.2 =
* Fixed Design 1's column gap rendering at 2x the row gap value even when both were set the same. Cause: columns used `padding` on both sides of each card as the gutter, so two adjacent cards' padding summed together (columnMargin + columnMargin); the row gap was a single `margin-bottom`. Switched to the same gap-safe `calc((100% - (N-1)*gap) / N)` column-width technique already used in Design 2, plus the CSS `gap` property on the row -- so Column margin and Row margin now produce genuinely equal pixel gaps in both directions.

= 2.22.1 =
* Fixed the "Show more" button rendering inside `.pmt-design2-grid` instead of after it -- a missing closing `</div>` (for `.pmt-design2-col-b`) meant the grid container was never properly closed before the button markup.

= 2.22.0 =
* Added a live category filter dropdown -- appears on the same line as the main section title, defaults to the currently active category, and lets visitors instantly swap which category's posts are shown, without a page reload. Hidden automatically when "Specific posts" is in use.

= 2.21.0 =
* Added a "Show more" button that appears when more matching posts exist than are currently shown, linking to the category archive or main blog page.

= 2.20.0 =
* Equal-height cards within each row (Design 1), implemented with pure CSS.
* Design 2's Column B now keeps its 2x2 proportions even with fewer than 4 posts available, using placeholder slots.

= 2.19.0 =
* Adjusted default values (columns, post count, corner radius) and reorganized settings panels for clarity.

= 2.16.0 - 2.18.0 =
* Category badges can now show up to 2 categories per post, each auto-colored.
* Added an optional main section title, independent of the post title's heading level.
* Added post title length limiting with an ellipsis for long titles.
* Various card-layout refinements (bottom-aligned meta/button, image sizing fixes).

= 2.10.0 - 2.15.0 =
* Introduced Design 2 (golden-ratio featured layout).
* Added full Style controls: box shadow, border, corner radius, column/row spacing, title font size.
* Added the "Read more" button and card element reordering.

= 2.0.0 - 2.9.0 =
* Converted from a static block to a fully dynamic one (live queries, no frozen snapshots).
* Added the Elementor widget alongside the existing Gutenberg block.
* Added specific-post selection, excerpt support, and image/tag/category toggles.

= 1.0.0 =
* Initial release: Gutenberg post grid block with category filtering and basic meta display.

== Upgrade Notice ==

= 2.22.0 =
Adds a live category filter dropdown next to the section title. No breaking changes.
