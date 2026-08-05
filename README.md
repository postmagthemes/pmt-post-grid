# pmt-post-grid
## Overview

**Post Grid for Gutenberg and Elementor** dynamically displays your latest posts without requiring manual updates. Every time a visitor loads the page, the plugin performs a live query, ensuring newly published or updated posts appear automatically.

Beyond a flexible post grid, the plugin includes powerful features such as AJAX-powered front-end filtering, automatic category color coding, built-in JSON-LD structured data for improved SEO, and developer-friendly hooks for extending functionality without modifying the plugin's core files.

---

# Features

## Three Beautiful Grid Layouts

### Design 1 – Responsive Grid

Display posts in a clean, responsive card layout with **2–6 configurable columns**. Choose how many posts to display, and automatically show a **"Show More"** link whenever additional matching posts are available.
<img width="675" height="615" alt="scrnli_Va8KTkNk3Yg0A5" src="https://github.com/user-attachments/assets/2fc1e632-4596-4fa0-8b4d-dcff67c90003" />

### Design 2 – Featured Golden Ratio Layout

Highlight your most important post using a balanced **golden-ratio layout** featuring:

* One large featured post (38.2% width)
* Four supporting posts in a 2×2 grid (61.8% width)
* Always displays up to five posts
* Gracefully handles situations where fewer supporting posts are available

<img width="675" height="661" alt="scrnli_8M8geEtQyyh42i" src="https://github.com/user-attachments/assets/d28d62bf-b713-41c7-9ba6-09c2295bb6e4" />


### Design 3 – Magazine Style List

Present posts in elegant horizontal rows using a golden-ratio image/content layout.

Features include:

* Optional alternating image alignment (left/right)
* Fixed image column for visual consistency
* Automatic related posts section showing up to four posts from the same category
* Square thumbnails for related posts

<img width="677" height="593" alt="scrnli_j3DkOAL9lYHXoo" src="https://github.com/user-attachments/assets/97257034-0085-472f-ab4a-58fc35369783" />

---

# Powerful Query Controls

Build exactly the grid you need.

Choose posts by:

* Category
* Author
* Specific posts
* Excluding selected posts

Control sorting by:

* Newest posts
* Most commented posts

The plugin also includes optional **AJAX-powered front-end filters**, allowing visitors to:

* Filter posts by category
* Sort by newest or most commented

Both controls update instantly without reloading the page and are automatically hidden when displaying manually selected posts.

---

# Flexible Content Controls

Show only the content you want.

Independently enable or disable:

* Featured image
* Excerpt
* Category badge
* Tags
* Read More button
* Section title

Excerpt length is fully configurable.

Category badges automatically receive unique, consistent colors without requiring manual configuration.

---

# Drag & Drop Element Ordering

Easily rearrange card elements using drag-and-drop.

Supported elements include:

* Image
* Category
* Title
* Excerpt
* Tags

Meta information and the Read More button intentionally remain fixed at the bottom to preserve visual consistency.

Design 3 includes its own dedicated ordering system, excluding the featured image since it always occupies a fixed column.

---

# Complete Design Controls

Customize the appearance without writing CSS.

Adjust:

* Grid column spacing
* Row spacing
* Image border radius
* Card border radius

Advanced styling includes:

* Box shadow (offset, blur, spread, color, inset)
* Optional card borders
* Border width
* Border style
* Border color

Typography controls include:

* Title font size
* Independent title sizing for Design 2 secondary posts
* Independent title sizing for Design 3 related posts

Text alignment supports:

* Left
* Center
* Right

---

# SEO Ready

Improve search engine visibility with built-in structured data.

Each grid can optionally generate a **schema.org ItemList** containing lightweight **Article** markup, including:

* Headline
* URL
* Featured image
* Publication date
* Author

Structured data can be enabled or disabled independently for each grid instance.

---

# Developer Friendly

Extend the plugin without modifying its source code.

Five built-in filter hooks allow developers to customize queries, output, and styling:

* `pmt_post_grid_args`
* `pmt_post_grid_query_args`
* `pmt_post_grid_card_html`
* `pmt_post_grid_category_color`
* `pmt_post_grid_output`

These hooks make it easy for themes and other plugins to modify behavior while remaining update-safe.

---

# One Rendering Engine. Every Builder.

Unlike many plugins that duplicate rendering logic for each page builder, **Post Grid for Gutenberg and Elementor** uses a single shared PHP rendering engine.

Both the Gutenberg block and the Elementor widget generate their output using the same `pmt_post_grid_build_markup()` rendering functions.

This unified architecture ensures that:

* Every bug fix applies everywhere
* New features automatically work in both builders
* All layouts remain visually identical
* Design 1, Design 2, Design 3, Gutenberg, and Elementor always stay perfectly synchronized

One codebase. One rendering engine. Consistent results across every layout and builder.

