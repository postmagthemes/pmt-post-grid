# Changelog

Detailed development history for PMT PostGrid for block editor with
elementor support. This is the full technical changelog (every fix,
its root cause, and the reasoning behind each change) -- kept for
maintainers/contributors.

For the condensed, user-facing version history, see `readme.txt`.

---

## 2.42.1 additions

* Renamed the plugin: "Post Grid Block and Addon for the Block Editor
  and Elementor" (was "...for Gutenberg and Elementor"). WordPress.org's
  plugin upload validator rejected the previous name -- "gutenberg" is
  a restricted term and cannot appear in a plugin's name/permalink at
  all, regardless of context; this is enforced by their own upload
  handler, not a style guideline. Updated consistently everywhere the
  name appears: the PHP `Plugin Name:` header, the readme title and
  description, the admin menu title, and the admin page heading --
  deliberately including the admin menu title this time, which a
  similar earlier rename (2.41.x) had left alone by request; with
  "gutenberg" now a confirmed, enforced problem word rather than a
  style preference, leaving it in internal UI text as well would have
  been inconsistent with the reason for the rename in the first place.

## 2.42.0 additions

* Removed the "Leave a review" admin notice entirely (added in 2.38.1)
  -- the activation hook, its self-healing `admin_init` fallback, the
  review-action handler (all three link actions), the
  `allowed_redirect_hosts` filter it needed, and the notice itself are
  all gone. Initially kept `uninstall.php`'s cleanup of the two options
  it used (`pmt_post_grid_activated_time`, `pmt_post_grid_review_dismissed`)
  as a safety net for any site that had already run the feature -- then
  removed that too, on confirmation that this plugin had never actually
  been distributed with the feature active, so there was no real
  installed base to account for and the legacy-cleanup code was
  unnecessary complexity for a scenario that never happened.

## 2.41.0 - 2.41.3 additions

* **Removed the last remaining static inline styles.** The "Post Grid"
  admin products page (Our Plugins / Our Themes) had been built quickly
  with `style="..."` attributes throughout -- the exact anti-pattern
  already fixed elsewhere in this plugin earlier (2.28.1/2.28.2): static
  values that never vary per-request should be a CSS class, not an
  inline style. Moved everything into a new dedicated stylesheet,
  `admin/products-page.css`, enqueued only on that one admin page (its
  exact hook suffix checked via `admin_enqueue_scripts`) -- never loaded
  anywhere else, including the front end. Audited the whole file
  afterward: every remaining inline style is confirmed genuinely
  dynamic (per-category colors, per-instance CSS custom properties) and
  can't be a static class.
* **Late escaping.** A WordPress.Security.EscapeOutput.OutputNotEscaped
  PHPCS finding at the date/time output turned out to be a real,
  fixable instance of "escape early" (a variable pre-escaped at
  assignment, then echoed later) rather than a false positive -- found
  in three places: the isolated date/time block, and the same pattern
  duplicated inside the `$meta_items[]`-array-building code for both
  the 'row' and 'full' card variants. Initially assumed the array-based
  occurrences couldn't be improved (the *composite* imploded string
  genuinely can't be wrapped in a single `esc_html()` without mangling
  its intentional markup) -- but that reasoning didn't actually apply
  to the *individual pieces* feeding into it. Moved `esc_attr()`/
  `esc_html()` inline, directly around `get_the_date('c')`/
  `get_the_date()`, in all three locations. Not a security fix (the
  data was already safe either way, just pre-escaped rather than
  late-escaped) -- but the correct, current WordPress coding-standards
  pattern, and easier to audit.
* **Removed all remote image references** from the admin products page
  -- fixed a PluginCheck.CodeAnalysis.Offloading.OffloadedContent
  finding. Plugin icons (`ps.w.org`) and theme screenshots
  (WordPress.org's own asset CDN, `i0.wp.com`) are gone entirely, along
  with the now-unused `icon` data key. "Offloading" is disallowed
  regardless of whose server it is, including WordPress.org's own, and
  there was no way to fetch and bundle real third-party image assets as
  part of this plugin's own build process -- so both sections (Our
  Plugins, Our Themes) became text-only: name, description, install
  count, link. "Our Themes" restructured from an image grid to a plain
  list matching "Our Plugins"; CSS simplified to match (removed every
  now-dead image-related class).
* **`wp_redirect()` -> `wp_safe_redirect()`** for the "Leave a review"
  notice's external redirect (the "Ok, you deserve it" action, sending
  the admin to the actual WordPress.org review page). Added
  `wordpress.org` to `allowed_redirect_hosts` at the same time --
  `wp_safe_redirect()` silently blocks any off-site redirect not on
  that list by default, which would have quietly broken the intended
  "go leave a review" flow rather than just being a cosmetic change.
* **Documented, not "fixed," a `uninstall.php` caching finding.**
  PluginCheck flagged both bulk-delete queries (`_pmt_views` postmeta,
  `pmt_grid_*` transients) for not using `wp_cache_get()`/
  `wp_cache_set()`/`wp_cache_delete()`. Deliberately did not add those
  calls -- they're built for single specific cache keys, not
  pattern/WHERE-based bulk deletes, and this file runs exactly once,
  ever, immediately before the data it touches ceases to exist
  entirely; there is no "next read" a cache could ever serve. Added the
  missing `NoCaching` sub-check to the existing `phpcs:ignore`
  annotations on both lines instead, with the reasoning inline as a
  comment.

## 2.40.0 - 2.40.2 additions

* **Added a "Post Grid by Postmagthemes" category to the Gutenberg
  block inserter** -- the block had no explicit category at all
  before this, so it fell back to the generic "Widgets" bucket.
  Renamed Elementor's existing "Postmagthemes" category to match (its
  slug, `postmagthemes`, was left unchanged, so nothing else needed
  updating on that side). Intent: both builders now share one category
  slug, so any future block/widget from this plugin can join the same
  category in both places just by referencing it.
* This took three attempts to actually land, and each one taught
  something real about how Gutenberg/Elementor handle categories:
  1. **First attempt (2.40.0)**: registered the category via
     `block_categories_all` and set `'category' => 'postmagthemes'` on
     `register_block_type()`. Looked correct, didn't work -- the block
     still showed under Widgets. Confirmed via user-reported console
     diagnostics that the block inserter's category UI wasn't even
     recognizing the new category as existing.
  2. **Second attempt (2.40.1)**: found (by reading a real, working
     reference plugin's source) that the filter needs a very high
     priority (999999999, so it runs after any other plugin's own
     category-filter callback that might rebuild the array without
     preserving earlier additions) and should hook *both* the current
     filter name (`block_categories_all`) and the older, still-
     supported one (`block_categories`). Still didn't work.
  3. **Third attempt (2.40.2), actual root cause**: Gutenberg blocks
     have two entirely separate registrations -- server-side PHP
     (`register_block_type()`, which was correctly updated in both
     prior attempts) and client-side JS
     (`wp.blocks.registerBlockType()` in `block/index.js`, which is
     what the block inserter UI actually reads for category placement).
     The JS side had its own hardcoded `category: 'widgets'`,
     completely overriding the PHP side regardless of how correctly
     that was configured. Fixed to `category: 'postmagthemes'`. Both
     halves -- the category being registered (PHP) and the block being
     assigned to it (JS) -- were necessary together; neither alone was
     sufficient, which is exactly why the first two attempts didn't
     resolve it despite being individually correct.

## 2.39.0 additions

* Added `uninstall.php` -- WordPress's standard, documented mechanism
  for plugin cleanup, only ever run when a user clicks "Delete" on an
  already-deactivated plugin (never on simple deactivation). Removes
  every piece of persistent data this plugin writes: the review-notice
  options (`pmt_post_grid_activated_time`, `pmt_post_grid_review_dismissed`),
  the `_pmt_views` view-count meta on every post, and the short-lived
  per-instance transients used by the live front-end filter. Handles
  multisite properly (loops every site on the network via
  `switch_to_blog()`/`restore_current_blog()`, rather than only
  cleaning the current site). Deliberately leaves actual block/widget
  content in posts/pages untouched -- that's the user's own content,
  not this plugin's internal data, and survives a later reinstall.

## 2.38.0 - 2.38.4 additions

* **Added a "Post Grid" top-level admin menu page** showing
  postmagthemes' other WordPress.org products -- "Our Plugins"
  (PostmagThemes Demo Import, WP Theme Statistic) and "Our Themes"
  (originally all 14 published themes with screenshots, later trimmed
  to the 8 most recently updated with a "More themes" link to the real
  author page -- see 2.38.2). Every name, link, and install count
  sourced directly from postmagthemes' actual WordPress.org profile at
  build time, not placeholder content.
* **Added a "Leave a review" admin notice** (2.38.1), shown on every
  wp-admin page -- hooked on `admin_notices`, which WordPress fires
  globally regardless of which menu item is open, not scoped to this
  plugin's own settings screen -- once an administrator has had the
  plugin active for more than 7 days. Three actions, all plain
  nonce-verified GET links handled on `admin_init`, no JS/AJAX: "Ok,
  you deserve it" (dismisses permanently, redirects to the WordPress.org
  review page), "Nope, maybe later" (pushes the 7-day threshold forward
  another 30 days), "I already did" (dismisses permanently, no external
  visit). Includes a self-healing `admin_init` fallback so existing
  installs (not just fresh activations via `register_activation_hook`)
  get the countdown started too, rather than never triggering at all.
* **2.38.2**: "Our Themes" reordered by actual recency (checked against
  postmagthemes' real Theme Trac update history, not just install
  count) and trimmed to the top 8, with a "More themes" button to the
  real `wordpress.org/themes/author/postmagthemes/` page for the rest.
* **2.38.3/2.38.4**: the live front-end category filter dropdown
  briefly gained post counts next to each name (e.g. "Business (12)"),
  then that was reverted and moved to the *correct* location -- the
  editor's own Category control in both builders, which is what was
  actually meant by "show the count in the dropdown" the whole time.
  Extensive back-and-forth debugging (OPcache suspected, ruled out;
  duplicate-plugin suspected, ruled out) before the actual
  miscommunication was found: two different dropdowns were being
  discussed without realizing it.

## 2.37.0 - 2.37.2 additions

* Added **author meta** (avatar + name, linked to the author's archive)
  as a new meta-info item, in both builders -- always the first item,
  ahead of date/comments/views/reading time. New "Show author" toggle,
  positioned first in the Meta info panel/section, default on. Uses
  WordPress's own `get_avatar()`, so it respects whatever Gravatar/
  avatar setup is already configured site-wide.
* **2.37.1**: fixed the author avatar sitting visibly lower than the
  other meta-info icons. The first alignment fix only handled the
  author item's *own* internal alignment (avatar vs. its own text) --
  missed that `.pmt-extra-info` (the outer row every meta item shares
  as a sibling) had no `align-items` set at all, defaulting to
  `stretch`. Every item stretched to match the tallest sibling (now the
  20px avatar), but each item's own content stayed wherever it
  naturally sat within that stretched box rather than centering against
  its siblings. Added `align-items: center` to `.pmt-extra-info`
  itself, in both CSS files.
* **2.37.2**: fixed "Show author" missing entirely from "Reset all
  settings" in both builders. The toggle was fully wired up when added,
  but the two hand-maintained reset-defaults maps (Gutenberg's
  `PMT_DEFAULT_ATTRIBUTES`, Elementor's `getElementorDefaults()`) were
  never updated to include it -- an easy category of gap to
  reintroduce every time a new setting is added. Audited both maps
  against every actual control/attribute afterward to confirm this was
  the only gap, rather than just patching the one reported case.

## 2.36.0 - 2.36.1 additions

* **Added genuine view-count tracking** -- previously "Show views" only
  *read* from common view-count meta keys, with nothing in the plugin
  actually incrementing any of them. Hooks into WordPress's own
  `template_redirect` action (no theme edits needed). Writes to its own
  `_pmt_views` meta key. Real safeguards: only counts genuine
  single-post visits (never pages/previews/admin), skips logged-in
  staff (`edit_posts` capability), filters common bot/crawler user
  agents, de-duplicates per visitor, and increments atomically.
* **2.36.1**: on request, rewritten to match the Pro version's
  implementation exactly, trading away some of the above for
  consistency between the two plugins: cookie-based de-duplication
  (once per visitor per post per day) instead of the original
  transient+IP/UA-fingerprint approach; read-then-write increment
  instead of an atomic SQL `UPDATE` (reintroducing a narrow race
  condition on high-traffic posts, accepted deliberately for parity);
  own tracking (`_pmt_views`) now checked *first* in
  `pmt_post_grid_get_views()`, with third-party plugin keys as the
  fallback (previously the reverse); and adopted Pro's fuller
  bot-signature list (19 patterns vs. 8).

## 2.34.2 - 2.35.2 additions: the Elementor "Reset all settings" saga

* **2.34.2**: added a "Reset all settings to default" button to both
  builders. Gutenberg: straightforward, `setAttributes()` with every
  attribute's registered default. Elementor: far more involved, since
  Elementor has no native "reset a whole widget" button -- built as a
  BUTTON control firing a Backbone event, handled by new editor-only JS
  (`block/elementor-editor.js`) using Elementor's Container API.
* **2.34.3**: the button didn't reliably work; added diagnostic console
  logging at every step plus try/catch around the actual reset logic,
  so failures would be clearly attributable to this script rather than
  indistinguishable from unrelated Elementor console noise.
* **2.34.4**: found the real root cause -- the reset was mutating
  `container.settings` (a raw Backbone Model) directly, which updates
  the underlying data (so the panel's own controls correctly showed the
  new values) but silently skips Elementor's live-preview refresh,
  undo/redo history, and the "document modified" flag that enables
  Publish/Update. Switched to Elementor's documented Commands API
  (`$e.run('document/elements/settings', {...})`), the same command
  real user edits go through. This also made the earlier manual
  repeater-collection reset workaround (added to handle `layoutOrder`/
  `layoutOrderDesign3` specifically) unnecessary -- the Commands API
  handles every control type, repeaters included, the same way the
  real UI does.
* **2.35.0**: relocated the Elementor button out of the bottom of the
  Typography section (easy to miss, buried inside an unrelated section)
  into its own dedicated "Reset" section, right after Typography.
* **2.35.2**: after the relocation, the button stopped firing at all --
  not even its confirm() dialog appeared. Root cause: a genuine race
  condition. The script only ever bound its event listener inside a
  `$(window).on('elementor:init', ...)` handler, which permanently
  misses the event if `elementor:init` had already fired before the
  script loaded (script load order isn't guaranteed relative to
  Elementor's own init timing, and can shift with any WordPress/
  Elementor update -- unrelated to the section move itself, which was
  presumably just what changed the timing enough to expose it). Fixed
  by checking synchronously whether Elementor has already initialized
  and binding immediately in that case, falling back to the event
  listener only if it genuinely hasn't initialized yet.
* (2.35.1, in between: Beaver Builder support was added then removed in
  the same session -- see below.)

## 2.35.0 - 2.35.1: Beaver Builder support (added, then removed)

* Added a full Beaver Builder module (Query/Content/Meta info/Layout/
  Style/Typography tabs), sharing the same `pmt_post_grid_build_markup()`
  function Gutenberg and Elementor already used, so output stayed
  identical across all three builders. Used Beaver Builder's native
  `suggest` field (`fl_as_posts`) for the Specific Posts/Exclude Posts
  pickers -- a genuine searchable post picker, no custom field needed.
  Two known simplifications versus Gutenberg/Elementor: "Card element
  order" wasn't reorderable (Beaver Builder has no repeater field
  suited to it without a substantial custom build), and no reset
  button (deferred rather than guessing at another unfamiliar internal
  API in the same pass as the Elementor reset-button work above).
* Removed again shortly after, on request -- back to Gutenberg and
  Elementor only. The module directory and its registration hook were
  deleted entirely, with a follow-up audit confirming zero lingering
  references anywhere in the codebase.

## 2.29.0 - 2.34.1 additions

* **2.29.0**: removed the "Order" (ascending/descending) control
  entirely, both builders -- results are now always descending
  (newest first, or most-commented first). "Order by" itself is
  unchanged. Hardcoded to `DESC` in all three designs' queries.
* **2.30.0**: added a second live front-end dropdown, "Most recent" /
  "Most commented", alongside the existing category dropdown -- same
  AJAX mechanism, no page reload. The two dropdowns work together:
  changing one always includes the other's current value in the
  request, so switching sort order doesn't reset an active category
  filter. REST endpoint extended to accept `order_by` alongside
  `category`.
* **2.31.0 - 2.32.0**: added **related posts** to Design 3 -- up to 4
  other posts sharing a row's own category, shown below each row's
  content. Not part of the reorderable Card element order; fixed
  position, always last, image before title. Related titles
  automatically use one heading level below the parent row's own title
  tag. Several follow-up fixes: related post images not rendering
  square (a CSS specificity trap -- the general `img {}` rule nested
  inside `.pmt-thumb-blog.pmt-post-grid-block` outranked the standalone
  override; moved the override inside the same nested block to win);
  empty placeholders losing their sizing when the selector was
  narrowed to `a.pmt-related-post` (reverted to the plain class
  selector, since empty placeholders are `<div>`s, not `<a>`s); related
  posts padding up to 4 with empty placeholder slots when fewer are
  found (matching Design 2's existing pattern for Column B); box-shadow/
  border/radius extended to related-post cards using the same CSS vars
  as regular cards, no new controls needed.
* **2.33.0 - 2.33.4**: a cluster of Design 3 refinements and their
  follow-up bugs. `min-height: 40px` added to Design 3's row title.
  Related titles trimmed to 6 words with an ellipsis -- which surfaced
  a double-escaping bug: `wp_trim_words()` was passed the HTML entity
  string `'&hellip;'`, but the output gets wrapped in `esc_html()` at
  every call site, which encodes the `&` and displays literal
  `&hellip;` text instead of an ellipsis whenever a title actually got
  trimmed. Fixed by switching to the actual UTF-8 ellipsis character
  (`'…'`), which `esc_html()` doesn't touch. Then several rounds
  chasing related-post height/alignment bugs when images are toggled
  off or a post has no thumbnail: an empty image container rendering
  regardless of whether that specific post had a thumbnail (fixed to
  only render when the post actually has one); `align-items: flex-start`
  added then reverted (wrong for mixed rows -- a title-only item next
  to siblings with images should still stretch to match them; the real
  fix was the toggle-off-still-reserving-square-space issue above,
  after which default `align-items: stretch` already did the right
  thing); and a final case where empty placeholder slots kept their
  square `aspect-ratio: 1` shape even with images toggled off entirely,
  forcing the whole row taller than it needed to be -- fixed by only
  applying the square treatment to empty placeholders when images are
  actually being shown.
* **2.34.0**: added independent "Show sort filter" / "Show category
  filter" toggles, letting either live dropdown be hidden without
  disabling the other.
* **2.34.1**: trimmed the bundled Font Awesome stylesheet down to only
  the 4 icons actually used (calendar, comment, eye, clock) --
  `fontawesome.css` from ~83KB to under 1KB -- and removed the unused
  solid/brands/v4-compatibility webfont files from the package
  entirely. Also fixed the `<time>` element's `datetime` attribute to
  use a machine-readable ISO 8601 date instead of the human-formatted
  display date.

## 2.22.0 - 2.28.2 additions

* **2.22.0 - 2.22.4**: added the live category filter dropdown itself
  (the first of the two live front-end filters, sort order came later
  in 2.30.0). Several immediate follow-up fixes: the "Show more" button
  rendering inside `.pmt-design2-grid` instead of after it (a missing
  closing `</div>` for `.pmt-design2-col-b`); Design 1's column gap
  rendering at 2x the row gap even when set equal (columns used
  padding on both sides as the gutter, so adjacent cards' padding
  summed together -- switched to the same `calc((100% - (N-1)*gap) / N)`
  technique already used in Design 2, plus CSS `gap` on the row, so
  both directions now produce genuinely equal pixel gaps); the category
  dropdown getting overridden by WP admin's own `.wp-core-ui select`
  styling in the Gutenberg editor (that selector is more specific than
  a lone class regardless of load order -- bumped to a two-class
  selector to reliably win); and default select sizing overrides.
* **2.23.0 - 2.23.1**: changed the default card element order to image,
  badge, title, excerpt, tags, with meta info and Read More removed
  from the reorderable list entirely and always rendering last, fixed
  position, regardless of how the rest is arranged.
* **2.24.0 - 2.24.1**: added the Author filter dropdown and the Exclude
  Posts picker (the inverse of Specific Posts). Fixed a PHP deprecation
  warning in Elementor's Author list (`'who' => 'authors'` replaced
  with the officially recommended `'capability' => array('edit_posts')`
  equivalent; Gutenberg's REST-based author fetch was unaffected, using
  a different, still-supported parameter).
* **2.25.0 - 2.25.1**: added **Design 3** itself -- stacked rows, image
  and content split at the golden ratio, image always its own column.
  Its own separate "Card element order" control with no "Featured
  image" option at all (Elementor needed a second, independent
  REPEATER control for this, since a single repeater can't
  conditionally hide one row based on another setting's value).
* **2.26.0 - 2.26.1**: added the five developer filter hooks
  (`pmt_post_grid_args`, `pmt_post_grid_query_args`,
  `pmt_post_grid_card_html`, `pmt_post_grid_category_color`,
  `pmt_post_grid_output`). Fixed the Row margin default inconsistency:
  Elementor's own control already defaulted to 15, but the shared PHP
  defaults, Gutenberg's schema, and Elementor's own `render()` fallback
  all still said 30 -- all four brought in line at 15.
* **2.27.0**: added JSON-LD structured data (schema.org `ItemList`,
  minimal `Article` per post), toggleable per instance, with a
  `pmt_post_grid_structured_data` filter for developers.
* **2.28.0 - 2.28.2**: replaced the opacity-fade AJAX loading state with
  real skeleton placeholders shaped per active design. Removed two
  categories of static inline style flagged as WordPress.org compliance
  concerns: a repeated `border-radius:var(--pmt-box-radius-top, 0px);`
  on category badges (moved into the existing CSS classes, fixed once
  in the shared render function so both builders were covered
  automatically), and a redundant `style="color:inherit;"` on meta-row
  links (removed outright -- `.pmt-extra-info a` already set this in
  the stylesheet).

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

