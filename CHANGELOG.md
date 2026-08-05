# Changelog

Detailed development history for Post Grid for Gutenberg and Elementor.
This is the full technical changelog (every fix, its root cause, and the
reasoning behind each change) -- kept for maintainers/contributors.

For the condensed, user-facing version history, see `readme.txt`.

---

## 2.21.0 additions

* New **"Show more" button**, bottom-right of `.home-section`, in both
  designs and both builders. Appears when there are genuinely more
  matching posts than what's currently shown:
  * Design 1: total matching posts > "Number of posts" setting.
  * Design 2: total matching posts > 4 (per explicit instruction --
    note this means the button can appear even when exactly 5 posts
    perfectly fill the layout with nothing actually left to see).
  * Hidden entirely whenever "Specific posts" is active (no natural
    "more" concept in that mode).
* Opens in a new tab (`target="_blank" rel="noopener noreferrer"`),
  linking to:
  * The selected category's archive, if Category is set to something
    specific.
  * Otherwise, the site's main blog listing -- the dedicated "Posts
    page" if one is configured in Settings > Reading, or the homepage
    itself if the front page already shows the latest posts.
* New shared functions: `pmt_post_grid_get_more_link()` (link target
  logic) and `pmt_post_grid_render_show_more_button()` (markup), used
  by both `pmt_post_grid_build_markup()` and
  `pmt_post_grid_build_design2_markup()`.
* Both designs' main queries switched from `no_found_rows: true` to
  `false`, needed to get an accurate total-matching-posts count for the
  comparisons above (small, one-time performance cost per page load --
  `no_found_rows: true` skips a `SQL_CALC_FOUND_ROWS`-equivalent count
  query for a minor speed gain, which this feature needs disabled).

## 2.20.2 change

* Added `flex-wrap: wrap;` to `.pmt-category-tag` (both files) -- so
  the "In" prefix + up to 2 category labels wrap to a second line
  instead of overflowing on narrower cards (e.g. Column B) or with
  longer category names.

## 2.20.1 fix

* Fixed Design 2's Column B collapsing to a single stretched row when
  fewer than 4 posts land there (e.g. only 3 posts total site-wide).
  Previously, an empty row was skipped entirely, so the one remaining
  row (flex: 1 in a flex-column) stretched to fill Column B's whole
  height -- reading visually as "3 columns in one row" rather than the
  intended 1-large + 2x2-grid layout. Both rows are now always
  rendered, and any row with fewer than 2 posts gets padded with a
  placeholder cell (`.pmt-design2-cell--empty`, a subtle dashed box) --
  so Column B always keeps its structural 2x2 proportions, with unused
  slots visibly reserved rather than invisibly collapsed.

## 2.20.0 change

* **Equal-height cards are now per-row, not global** (Design 1 only) --
  and implemented as pure CSS instead of JavaScript. `.pmt-row` already
  had default `align-items: stretch`, meaning its column divs already
  stretched to match the tallest column in their own flex line (row) --
  the missing piece was that `.pmt-blog-snippet` (the card) didn't fill
  that stretched column. Fixed by making the column div
  `display: flex; flex-direction: column` and the card
  `flex: 1 1 auto`, so it fills whatever height its row's tallest card
  determines. Native flexbox line-wrapping means this is inherently
  per-row -- no measurement, no JS, no resize listener needed, and no
  flash-of-unequal-heights while a script loads.
* **Removed `block/frontend.js` entirely** (and its registration in
  both builders) -- the JS-based global-max equalization from 2.18.0 is
  now fully superseded by the CSS approach above.

## 2.19.0 changes

* Default value changes (Design 1, both builders):
  * Columns: 4 -> 2
  * Number of posts: 8 -> 4
  * Upper corner radius: 0 -> 10
  * Lower corner radius: 0 -> 10
  Updated everywhere a default lives: shared PHP defaults, Gutenberg
  attribute schema, Elementor control defaults/descriptions, Elementor
  render() fallbacks, and the CSS var() fallback for border-radius.
* Moved "Show main title" (toggle, text, tag) from the Layout
  panel/section into Content, both builders -- now sits at the top of
  Content, above "Show featured image".

## 2.18.4 removal

* Removed the post-title min-height equalization added in 2.18.2
  (`equalizeDesign1TitleHeights()`). `block/frontend.js` is back to
  only equalizing whole-card height (2.18.0) -- title height is no
  longer separately measured or constrained.

## 2.18.3 fixes

* Fixed two bugs introduced by the bottom-pin change (2.18.1):
  1. **Read more button was stretching full-width** -- `.pmt-blog-content`
     being `flex-direction: column` defaults its children to
     `align-items: stretch`, which was stretching the button along with
     everything else. Added `align-self: flex-start` so it keeps its
     natural content-hugging width.
  2. **Meta row was landing mid-card instead of pinned to the bottom** --
     both meta and the button had `margin-top: auto`, and flexbox splits
     leftover space *between* all auto margins rather than pushing the
     whole trailing group down together. Moved `margin-top: auto` onto
     meta only (button now uses a small fixed `margin-top: 10px`), and
     added `order: 98` / `order: 99` to force meta then button to always
     render last, directly adjacent, regardless of where meta sits in
     your configured Card element order.

## 2.18.2 addition

* `block/frontend.js` now also equalizes **post title height**
  specifically (Design 1 only): finds the tallest `.pmt-title` within
  each grid instance, then applies that as `min-height` to every title
  in that instance -- so a 1-line title and a 3-line title both reserve
  the same vertical space, keeping excerpt/meta/button aligned across
  all cards regardless of individual title length. Runs *before* the
  existing card-height equalization (2.18.0), since a title growing
  taller changes the card's own natural height -- title alignment has
  to be settled first for the card-height measurement to be accurate.

## 2.18.1 addition

* Extra space from the new equal-height stretching now collects at the
  bottom of shorter cards instead of leaving a gap mid-card. Made
  `.pmt-blog-snippet` a flex column and `.pmt-blog-content` grow to
  fill it (`flex: 1 1 auto`); the meta row and "Read more" button now
  use `margin-top: auto` to anchor to the bottom of that extra space.
  Applies everywhere (Design 1, and Design 2's Column A/B, both of
  which were already flex-based) -- not just the newly-equalized
  Design 1 cards.

## 2.18.0 additions

* **Equal-height cards, Design 1 only**: new `block/frontend.js`
  finds the tallest `.pmt-blog-snippet` within each Design 1 grid
  instance on the page, then sets every card in that instance to that
  height -- so rows no longer look ragged from varying title/excerpt
  length. Explicitly excludes Design 2 (`.pmt-design2`), since Column
  A/B's asymmetric layout doesn't make sense to equalize the same way.
  Each grid instance is measured independently; multiple grids on one
  page don't affect each other. Recalculates on window resize
  (debounced), since the columns-per-row (and therefore what "tallest"
  means) changes at responsive breakpoints. Auto-enqueued via the
  block's `'script'` key (Gutenberg) and `get_script_depends()`
  (Elementor) -- no manual `wp_enqueue_script()` needed, and it only
  loads on pages where the block/widget is actually present.

## 2.17.1 change

* Column A's image aspect ratio changed from 1.4 to 1.2 (both
  builders). Also found and removed a duplicate `.pmt-design2-col-a img`
  rule in `editor.css` -- it had two copies (one nested with higher
  specificity, one standalone), which happened to agree in value so it
  was harmless, but was a latent trap for future edits to only one of
  the two. Now there's a single rule in each file.

## 2.17.0 additions

* Post titles longer than 9 words are now truncated, with a trailing
  ellipsis (&hellip;) marking the cut. New shared helper,
  `pmt_post_grid_get_trimmed_title()` (uses `wp_trim_words()`), used by
  both the full card variant (Design 1 / Column A) and the simplified
  variant (Column B) -- titles of 9 words or fewer are shown in full,
  unchanged.

## 2.16.1 fix

* "In" now appears only once even with 2 categories shown -- was
  incorrectly repeated per category ("In Business" "In Family").
  Now: one "In" prefix (colored using the first/priority category),
  followed by each category as its own colored label
  ("In" "Business" "Family").

## 2.16.0 additions

* Category badge now shows up to **2 categories per post**, as
  separate badges (each with its own "In" prefix and auto-rotated
  color), instead of always just the first one.
* New `pmt_post_grid_get_display_categories()` helper: if a category
  filter is active in Query settings and the post belongs to that
  category, it's guaranteed to appear (shown first) among the badges,
  rather than potentially being left out by WordPress's own default
  category ordering.
* Applies to both the full card variant (Design 1 / Column A) and the
  simplified variant (Column B) -- both use the same shared logic.

## 2.15.0 additions

* **Main title**: an optional section heading (`.pmt-main-title`),
  positioned directly inside `.home-section`, above the grid itself.
  Fixed choice of H1 or H2 (default H2) -- deliberately independent of
  the post title's own H1-H4 tag setting, always structurally higher.
  Hidden by default: a toggle turns it on, and it only actually renders
  once you've entered text (an empty field with the toggle on shows
  nothing, avoiding an empty `<h1></h1>`/`<h2></h2>` in the markup).
  Works in both Design 1 and Design 2, both builders, via a shared
  `pmt_post_grid_render_main_title()` function.

## 2.14.0 additions

* New **Typography** section, positioned after Style, in both builders.
* Moved "Title font size (Column A / Design 1)" and "Column B title
  size (% of Column A)" out of Style and into the new Typography
  section. No functional change -- same attributes, same behavior,
  just organized separately from spacing/shadow/border controls.

## 2.13.1 fixes

* Category colors boosted for actual differentiation -- was using the
  near-white 50-stop tint (all 7 looked essentially the same pale
  off-white), now uses the clearly-saturated 200-stop for backgrounds
  with the 900-stop (darkest) for text -- each category is now visibly
  distinct at a glance.
* "In" restored before the category name, as its own chip with
  *inverted* colors relative to the category chip: "In"'s background =
  the category chip's text color, and "In"'s text = the category chip's
  background color. Both chips share the same border-radius.
* Category badge border-radius is no longer a fixed 999px pill --
  it's tied to the same border-radius control that governs the card's
  own box (`--pmt-box-radius-top`), so the badge shape always matches
  whatever corner rounding you've set for the card, with no separate
  radius control to maintain.

## 2.13.0 additions

* **Auto-rotated category colors**: `pmt_post_grid_get_category_color()`
  deterministically maps each category's term_id to one of 7 fixed
  color pairs (light bg + dark, readable text) -- same category always
  gets the same color, zero configuration. Applied consistently to:
  * The category badge (now a clean colored pill, replacing the old
    "In Category1, Category2" underlined-link style).
  * The meta row (date/comments/views/reading-time icons + text).
  * Tag pills.
  * The new "Read more" button.
  All four use the *same* post's assigned color, so everything on a
  given card matches.
* **Category badge now shows in Column B too** (Design 2's simplified
  cards), controlled by the exact same "Show category badge" toggle
  used for Column A / Design 1 -- no separate control.
* **Meta row divider restored** for Column B -- the thin line
  above/below the meta row (already present in Design 1 / Column A)
  was being explicitly stripped for the simplified card variant; now
  matches.
* **New "Read more" button**, with toggle and customizable text (both
  builders). Shows on Design 1 and Column A of Design 2 (wherever the
  full card variant is used). Background color matches that post's
  category color.
* Category badge and tag-list colors are now set via inline styles per
  post (since color varies post-to-post based on category), with base
  CSS providing sensible fallbacks only.

## 2.12.0 additions

* **Title font size control** (Style section, both builders): one
  "Title font size" control drives Column A / Design 1's title (default
  20px), plus a "Column B title size (% of Column A)" scale factor
  (default 75%) that derives Column B's size automatically -- e.g.
  Column A at 20px makes Column B 15px with no separate size to
  maintain. Change Column A's size and Column B follows proportionally.
  Implemented via CSS custom properties (`--pmt-title-font-size`,
  `--pmt-title-scale-b`) and `calc()`, computed once in
  `pmt_post_grid_compute_style_vars()` so both designs and both
  builders stay in sync.

## 2.11.2 fix

* Column B title corrected to the true 25%-smaller-than-base value:
  15px (20px base x 0.75), replacing the earlier 11.25px which was
  mistakenly computed off an interim 15px value instead of the real
  20px base.

## 2.11.1 fix

* Fixed Column B's title still rendering at 20px despite the
  "reduce by 25%" override. Root cause: CSS specificity, not the value
  itself. The base rule compiled to `.pmt-thumb-blog.pmt-post-grid-block
  .pmt-title` (3 class selectors) while the override was a standalone
  `.pmt-blog-snippet--simple .pmt-title` (2 class selectors) -- fewer
  classes loses regardless of which rule appears later in the file, so
  the 20px base always won. Moved the override inside the same nested
  block so it now compiles with matching specificity and actually wins.
* Flagging a separate discrepancy for you to confirm: the 11.25px value
  was originally computed as 25% off an interim 15px value used while
  first building Design 2 -- not off the base title's actual 20px. True
  25%-smaller-than-20px would be 15px, not 11.25px. Left the value as
  11.25px for now since only the "still shows as 20px" bug was reported;
  let me know if you'd rather it be 15px (25% off the real base) instead.

## 2.11.0 fix

* Card element order (Layout section) now applies to Column B's
  simplified cards too, not just Column A. Previously the 'simple'
  card variant used a hardcoded image -> title -> date order and
  ignored the reorder control entirely; it now filters the same
  layoutOrder down to just the elements it supports (image/title/meta
  -- badge/excerpt/tags never apply there, regardless of position) and
  renders them in whatever relative order you've configured.

## 2.10.3 fixes

* Fixed the image "shifted right / outside the card" bug -- three
  causes, all fixed in both `block/style.css` and `block/editor.css`:
  1. `.pmt-img-holder` (an `<a>` tag) had no `display`/`width` set at
     all. Anchors default to `display: inline`, so it never filled the
     card and its position followed the parent's `text-align` instead
     of the card's actual layout. Added `display: block; width: 100%;`.
  2. `.pmt-blog-snippet` used the non-standard `width: -webkit-fill-available`
     (style.css) / `width: fit-content` (editor.css) -- unreliable
     cross-browser, and inconsistent between editor and front end.
     Both now use plain `width: 100%`.
  3. Added a scoped `box-sizing: border-box` reset
     (`.pmt-post-grid-block, .pmt-post-grid-block *`) and
     `overflow: hidden` on `.pmt-blog-snippet`, so no descendant can
     silently overflow the card's edge even if a width calculation is
     off elsewhere.
* Also found and fixed: `editor.css`'s `.pmt-img-holder` rule was an old,
  never-updated leftover (`margin: 0 0 30px`, no display/width) that had
  drifted out of sync with `style.css`'s current version
  (`margin: 2px 2px 20px`) -- editor preview and front end could
  genuinely look different as a result. Now identical in both files.

## 2.10.2 fixes

* Fixed two related "Show excerpt" bugs, both caused by the 2.10.1
  Column A hardcode (`$col_a_args['showExcerpt'] = true;`), which forced
  the excerpt on regardless of the toggle:
  1. The toggle appeared broken -- switching it off did nothing, since
     Column A ignored it entirely.
  2. The toggle's default position (Off/Hide) didn't match what
     actually rendered (excerpt showing anyway), since the override
     bypassed the real default value.
  Removed the hardcode entirely -- the toggle now genuinely controls
  Column A's excerpt, in both designs and both builders. Changed the
  attribute/control's *default* to On/true instead (Gutenberg attribute
  schema, Elementor SWITCHER default), so a fresh block/widget starts
  with the control correctly showing On, matching what renders.
  Column B's simplified cards are unaffected -- they never show
  excerpt/tags/category by design, independent of this toggle.

## 2.10.1 fixes

* Fixed Design 2 rendering wider than Design 1. Cause: `.pmt-design2-col-a`
  / `-col-b` used `flex: 0 0 38.2%` / `flex: 0 0 61.8%`, which already
  summed to 100% of the container -- adding `gap` on top of that
  overflowed the row by the gap amount, and `flex-shrink: 0` meant
  nothing compensated. Switched to gap-safe flex-grow ratios
  (`flex: 382 1 0%` / `flex: 618 1 0%`, basis 0) so the two columns
  always total exactly the container width regardless of the Column
  margin setting. Same fix applied to Column B's 2x2 cells.
* Column B (Design 2) title font size reduced 25%: 15px -> 11.25px.
* Column A (Design 2) now always shows the excerpt, regardless of the
  global "Show excerpt" toggle -- it has the room for it. Column B's
  simplified cards continue to never show excerpt, tags, or category
  (image + title + date only), matching the limited space there.

## 2.10.0 additions

* **Design 2 implemented**: golden-ratio two-column layout, always 5
  posts total.
  * Column A (38.2% width): 1 post, full card -- same elements, same
    layoutOrder, same Style-panel options as Design 1 (both designs
    share the exact same card-rendering function, so Column A can never
    visually drift from Design 1).
  * Column B (61.8% width): the next 4 posts, in a 2x2 grid, using a
    simplified card (image + title + date only -- no excerpt, tags,
    category badge, or comment/view/reading-time counts) for a lighter
    footprint at that smaller size.
  * Query works exactly like Design 1 (category/order, or specific
    posts override), just capped to 5 posts since the layout requires
    exactly that many slots. Columns and Number of posts are hidden/
    disabled when Design 2 is selected, since they don't apply.
  * Refactored the per-post card markup into a shared function,
    `pmt_post_grid_render_card()`, and the style-vars computation into
    `pmt_post_grid_compute_style_vars()` -- both designs now call the
    same code rather than maintaining two copies.

## 2.9.0 additions

* **Card element order** -- new control, both builders, lets you change
  the stacking order of the featured image, category badge, title,
  excerpt, meta row (date/comments/views/reading time), and tags within
  each card. For example, you can now place the title above the image,
  or the meta row above the excerpt.
  * **Gutenberg**: a new "Card element order" list under the Layout
    panel. Each row has Move up / Move down buttons.
  * **Elementor**: a new "Card element order" repeater control under the
    Layout section. Rows can be dragged to reorder, using Elementor's
    own built-in repeater drag handle.
  * Default order (matches every prior version's fixed layout, so
    nothing changes visually unless you deliberately reorder):
    featured image, category badge, title, excerpt, meta row, tags.
  * The existing Show/Hide toggles for each of these elements are
    unaffected and still control visibility independently -- this only
    controls order.
  * The featured image keeps its original full-width look at the top of
    the card. If moved to any other position, it renders inline with
    the same padding as the surrounding text content, since a
    full-bleed image can only sit flush against the card's own edges.
  * Saved as `layoutOrder`, an ordered list of up to six keys ('image',
    'badge', 'title', 'excerpt', 'meta', 'tags'). Any old block/widget
    without this attribute set falls back to the default order
    automatically; a partial or garbled value is completed with any
    missing elements appended at the end, so nothing is ever silently
    dropped from the card.

## 2.8.0 changes

* **Removed RTL (right-to-left) support**, added in 2.7.0. This
  removes the **Text direction** control (Auto / LTR / RTL) from both
  builders, the `textDirection` attribute/setting, the `dir` attribute
  that was written on the block's wrapper, and the associated
  `[dir="rtl"]` / `[dir="ltr"]` CSS rules in `block/style.css` and
  `block/editor.css`. The block's markup and layout now simply follow
  the surrounding page/site direction as normal HTML always has,
  with no per-block override. Existing blocks that had a non-default
  Text direction chosen will lose that override and fall back to the
  page's own direction.

## 2.7.2 fix

* True RTL, not just right-alignment. Added explicit
  `direction: rtl` / `direction: ltr` CSS rules keyed off the block's
  `dir` attribute, rather than relying on the browser's implicit
  default stylesheet behavior for `dir="rtl"` (which a theme reset or
  RTL/bidi plugin can silently override). This is what actually
  controls real text flow direction, list/icon mirroring, and
  punctuation/number handling -- `text-align` (fixed in 2.7.1) only
  controls which side content sits on. Also fixed a remaining physical
  `justify-content: left` in the editor CSS (now logical `flex-start`)
  so columns themselves reorder correctly under RTL in the editor too.
  Note: Latin-script (English) text will not reverse letter/word order
  under RTL -- that's correct, expected behavior; direction changes
  layout flow and mixed-script/number handling, not the internal
  reading order of same-script text runs.

## 2.7.1 fix

* RTL fix: the title, excerpt, category badge, and meta row weren't
  flipping with the Text direction control -- only the outer wrapper
  was. Root cause: `.pmt-blog-snippet` had a hardcoded, physical
  `text-align: left`, which everything else inherits and which never
  respects a `dir="rtl"` attribute. Switched to logical `text-align:
  start`/`end` throughout (and added the missing `.pmt-align-left` rule,
  which never existed -- the default case was silently falling through
  to that same hardcoded `left`). Now the whole card -- title, excerpt,
  category, tags, meta -- follows Text direction correctly, in both the
  editor and front end.

## 2.7.0 additions

* **Box border corner radius** -- new sub-controls under Box border,
  both builders:
  * **Upper corner radius** (px, 0-100) -- rounds the card's top-left
    + top-right corners together.
  * **Lower corner radius** (px, 0-100) -- rounds the bottom-left +
    bottom-right corners together.
  * Default: `0px` for both (square corners, matching the existing
    border default). Applied via two new CSS custom properties,
    `--pmt-box-radius-top` / `--pmt-box-radius-bottom`, independent of
    the featured image's own corner radius.
* **RTL (right-to-left) support**:
  * New **Text direction** control (Auto / LTR / RTL), both builders.
    Default: `Auto`, which follows the site's own `is_rtl()` -- no
    behavior change on existing LTR or RTL sites unless you explicitly
    pick LTR or RTL to force a direction on this specific block,
    regardless of site language.
  * The resolved direction is written out as a `dir` attribute on the
    block's own wrapper (not just inherited from `<html>`), so it's
    correct even if the block is placed inside content with a
    different direction than the site default.
  * The meta-info row (date/comments/views/reading time icons) and the
    category badge now use logical CSS properties (`margin-inline-*`,
    `justify-content: flex-start`) instead of hardcoded left/right
    values, so their spacing mirrors automatically in RTL -- no
    separate `-rtl.css` stylesheet needed. The explicit Left/Center/
    Right text-alignment control is unchanged and stays literal in
    both directions (matching its icons), same as WordPress core's own
    alignment control.

## 2.6.0 additions

* **Box shadow Opacity** -- new dedicated control (0-1, step 0.01)
  applied independently of the Color control, so you can adjust one
  without touching the other. Default: `0.09` (a softer shadow than the
  2.5.0 default of 0.18 -- this now applies to the shadow color's own
  alpha regardless of which color is picked). The Color control itself
  now stores a plain, alpha-less color (default `#000000`); opacity and
  base color are combined into the final `rgba()` shadow at render time.
* **Box border** -- new sub-section under Box shadow, both builders:
  * **Border width** (px, 0-20). Default: `0`.
  * **Border style** -- Solid / Dashed / Dotted / Double / None.
    Default: `Solid`.
  * **Border color** -- full color-picker (same swatch + picker UI as
    the shadow color). Default: `grey`.
  * Combined into one `border` shorthand, applied via a new
    `--pmt-box-border` CSS custom property alongside the existing
    `--pmt-box-shadow` one -- so, as with the rest of Style, Gutenberg
    and Elementor stay pixel-identical.
* Colors (box-shadow and border) now also accept a small fixed allow-list
  of named CSS colors (e.g. `grey`, `navy`, `crimson`) in addition to hex
  and rgb()/rgba() -- still validated server-side before being echoed
  into markup, so untrusted input still can't inject arbitrary CSS.

## 2.5.0 additions

* **More visible default box shadow** -- was `0px 0px 6px rgba(0,0,0,0.09)`
  (very subtle, invisible on some monitors/lighting), now
  `0px 2px 12px rgba(0,0,0,0.18)`. Existing pages/widgets with an
  explicitly-set shadow are unaffected -- this only changes what you get
  before touching the Style controls.
* **Default-value hints on every numeric control** in Layout and Style
  (both builders) -- e.g. "Default: 15px" shown under Column margin, so
  it's always clear what the original value was even after changing it.
* **Image corner radius** control (Style section) -- rounds the featured
  image's corners, 0-100px. Default: 10px (matches the previous
  hardcoded value, now adjustable). Set to 0 for square corners.

## 2.4.1 fix

* Gutenberg's box-shadow color field now uses a real color-swatch +
  picker (WordPress core `ColorPicker`, with alpha channel), matching
  Elementor's native color control -- previously it was a plain text
  input in Gutenberg only. Click the swatch button to open the picker.

## 2.4.0 additions

* New **Style** section, positioned after Layout (both builders):
  * **Column margin** -- horizontal gutter between cards in the same
    row (px). Also drives the row wrapper's compensating negative
    margin automatically, so the grid still reaches the container's
    true edges at any value (no more "gap at the end of the row" --
    see the earlier fix history in this readme's git log / chat).
  * **Row margin** -- vertical space between rows of cards (px).
  * **Box shadow** -- full control: horizontal offset, vertical
    offset, blur, spread, color (hex or rgba), and an inset toggle.
* All Style values are applied via CSS custom properties
  (`--pmt-column-margin`, `--pmt-row-margin`, `--pmt-box-shadow`) set
  as an inline `style` attribute on the block wrapper, computed once in
  `pmt_post_grid_build_markup()` -- so both builders stay pixel-identical.
* Box-shadow color goes through `pmt_post_grid_sanitize_css_color()`,
  a strict allow-list regex (hex or rgb/rgba only) before it's ever
  echoed into markup -- untrusted input can't inject arbitrary CSS.

## 2.3.0 additions

* "Show category" moved from Meta info into the Content section (both
  builders).
* New Layout section, now positioned after Meta info: Columns, Number
  of posts, Title tag, and Text alignment all live here.
* **Title tag choice** -- pick H1/H2/H3/H4 for each post title
  (`<h3 class="pmt-title">` becomes whichever tag you choose). Styling
  is class-based (`.pmt-title`), so appearance is unaffected by the tag
  change -- only the semantic heading level changes.

## 2.2.0 additions

* **Specific posts**: pick exact posts by title (checkbox list in
  Gutenberg, multi-select in Elementor). When any are selected, this
  completely overrides Category/Order/Number of posts -- exactly those
  posts show, in the order picked. Uses `post__in` + `orderby: post__in`
  under the hood.
* **Order by "Title" removed** -- only Date and Comment count remain, to
  avoid overlap/confusion with the new specific-posts picker.
* **Show/hide featured image** -- when off, no `.pmt-img-holder` markup
  is output at all (not just hidden via CSS).
* **Show/hide excerpt**, with a configurable length in words
  (`wp_trim_words()`), shown as `.pmt-excerpt`.
* **Show/hide tags** -- post tags (not categories) rendered as a
  distinct pill-style list, `.pmt-tag-list`, separate from the existing
  `.pmt-category-tag` badge styling.
* **Text alignment** (left/center/right) for title/excerpt/meta content,
  via `.pmt-align-left|center|right` on the wrapper.

