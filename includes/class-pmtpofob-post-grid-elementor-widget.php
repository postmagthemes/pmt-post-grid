<?php
/**
 * Elementor widget for the postmagthemes post grid.
 *
 * Mirrors the Gutenberg block's settings (columns, post count, category,
 * order/orderby, meta toggles) and renders through the exact same
 * pmtpofob_post_grid_build_markup() function the block uses -- so the two
 * builders can never visually drift apart.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Defense in depth: only declare this class if Elementor's Widget_Base is
// actually available right now. If this file somehow gets required before
// Elementor has finished loading (a plugin load-order edge case), this
// silently no-ops instead of a fatal "Class not found" error.
if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

if ( ! class_exists( 'PMTPOFOB_Post_Grid_Elementor_Widget' ) ) {

	class PMTPOFOB_Post_Grid_Elementor_Widget extends \Elementor\Widget_Base {

		public function get_name() {
			return 'pmt-postgrid-block-editor-elementor-support';
		}

		public function get_title() {
			return __( 'Post Grid (Postmagthemes)', 'pmt-postgrid-block-editor-elementor-support' );
		}

		public function get_icon() {
			return 'eicon-posts-grid';
		}

		public function get_categories() {
			return array( 'postmagthemes' );
		}

		public function get_keywords() {
			return array( 'post', 'grid', 'blog', 'posts', 'archive' );
		}

		/**
		 * Same handles registered in pmtpofob-post-grid.php. Elementor
		 * automatically enqueues these (editor preview + front end)
		 * whenever this widget is present on a page -- no manual
		 * wp_enqueue_style() calls needed.
		 */
		public function get_style_depends() {
			return array(
				'pmtpofob-post-grid-style',
				'pmtpofob-post-grid-fontawsome-style',
				'pmtpofob-post-grid-fontawsome-regular-style',
			);
		}

		/**
		 * Same handle registered in pmtpofob-post-grid.php (the live category
		 * filter dropdown). Elementor auto-enqueues this whenever the
		 * widget is present, same mechanism as styles above.
		 */
		public function get_script_depends() {
			return array(
				'pmtpofob-post-grid-frontend-filter',
			);
		}

		protected function register_controls() {

			// -----------------------------------------------------------
			// Query section
			// -----------------------------------------------------------
			$this->start_controls_section(
				'pmtpofob_section_query',
				array(
					'label' => __( 'Query', 'pmt-postgrid-block-editor-elementor-support' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$categories_control = array(
				'' => __( 'All categories', 'pmt-postgrid-block-editor-elementor-support' ),
			);
			$wp_categories      = get_categories( array( 'hide_empty' => false ) );
			foreach ( $wp_categories as $wp_category ) {
				/* translators: 1: category name, 2: number of posts in that category. */
				$categories_control[ (string) $wp_category->term_id ] = sprintf( __( '%1$s (%2$s)', 'pmt-postgrid-block-editor-elementor-support' ), $wp_category->name, number_format_i18n( $wp_category->count ) );
			}

			$this->add_control(
				'category',
				array(
					'label'   => __( 'Category', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => '',
					'options' => $categories_control,
				)
			);

			$authors_control = array(
				'' => __( 'All authors', 'pmt-postgrid-block-editor-elementor-support' ),
			);
			$wp_authors       = get_users(
				array(
					'capability' => array( 'edit_posts' ),
					'fields'     => array( 'ID', 'display_name' ),
					'number'     => 100,
					'orderby'    => 'display_name',
				)
			);
			foreach ( $wp_authors as $wp_author ) {
				$authors_control[ (string) $wp_author->ID ] = $wp_author->display_name;
			}

			$this->add_control(
				'author',
				array(
					'label'       => __( 'Author', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '',
					'options'     => $authors_control,
					'description' => __( 'Default: All authors', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$posts_control = array();
			$wp_posts      = get_posts(
				array(
					'numberposts' => 100,
					'post_status' => 'publish',
					'orderby'     => 'title',
					'order'       => 'ASC',
				)
			);
			foreach ( $wp_posts as $wp_post ) {
				$posts_control[ (string) $wp_post->ID ] = $wp_post->post_title;
			}

			$this->add_control(
				'postIds',
				array(
					'label'       => __( 'Specific posts', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'multiple'    => true,
					'label_block' => true,
					'options'     => $posts_control,
					'default'     => array(),
					'description' => __( 'Pick exact posts to show by title. When any are checked, they completely override Category/Order by/Number of posts of above.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'excludePostIds',
				array(
					'label'       => __( 'Exclude posts', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SELECT2,
					'multiple'    => true,
					'label_block' => true,
					'options'     => $posts_control,
					'default'     => array(),
					'description' => __( 'Posts selected here are removed from the results, on top of Category/Author/Order above. Ignored while Specific posts (above) is in use.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'orderBy',
				array(
					'label'       => __( 'Order by', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'date',
					'options'     => array(
						'date'          => __( 'Date', 'pmt-postgrid-block-editor-elementor-support' ),
						'comment_count' => __( 'Comment count', 'pmt-postgrid-block-editor-elementor-support' ),
					),
					'description' => __( 'Always newest/most-commented first.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->end_controls_section();

			// -----------------------------------------------------------
			// Content section
			// -----------------------------------------------------------
			$this->start_controls_section(
				'pmtpofob_section_content',
				array(
					'label' => __( 'Content', 'pmt-postgrid-block-editor-elementor-support' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'showMainTitle',
				array(
					'label'        => __( 'Show main title', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => '',
					'description'  => __( 'A section heading above the grid, higher-level than the post title.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'mainTitleText',
				array(
					'label'       => __( 'Main title text', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => '',
					'placeholder' => __( 'e.g. Latest posts', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'   => array(
						'showMainTitle' => 'yes',
					),
				)
			);

			$this->add_control(
				'mainTitleTag',
				array(
					'label'       => __( 'Main title tag', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'h2',
					'options'     => array(
						'h1' => 'H1',
						'h2' => 'H2',
					),
					'description' => __( 'Default: H2', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'   => array(
						'showMainTitle' => 'yes',
					),
				)
			);

			$this->add_control(
				'showImage',
				array(
					'label'        => __( 'Show featured image', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'showExcerpt',
				array(
					'label'        => __( 'Show excerpt', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'excerptLength',
				array(
					'label'       => __( 'Excerpt length (words)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 5,
					'max'         => 100,
					'step'        => 1,
					'default'     => 20,
					'description' => __( 'Default: 20', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'   => array(
						'showExcerpt' => 'yes',
					),
				)
			);

			$this->add_control(
				'showTags',
				array(
					'label'        => __( 'Show tags', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => '',
				)
			);

			$this->add_control(
				'showCategoryBadge',
				array(
					'label'        => __( 'Show category', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'showReadMore',
				array(
					'label'        => __( 'Show "Read more" button', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'readMoreText',
				array(
					'label'     => __( 'Button text', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'      => \Elementor\Controls_Manager::TEXT,
					'default'   => __( 'Read More', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition' => array(
						'showReadMore' => 'yes',
					),
				)
			);

			$this->add_control(
				'showStructuredData',
				array(
					'label'        => __( 'Add structured data (SEO)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Yes', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'No', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
					'description'  => __( 'Outputs a schema.org ItemList block listing these posts, for richer search results. Turn off if your SEO plugin already outputs its own listing schema for this page.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->end_controls_section();

			// -----------------------------------------------------------
			// Meta info section
			// -----------------------------------------------------------
			$this->start_controls_section(
				'pmtpofob_section_meta',
				array(
					'label' => __( 'Meta info', 'pmt-postgrid-block-editor-elementor-support' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'showAuthor',
				array(
					'label'        => __( 'Show author (avatar + name)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
					'description'  => __( 'Always the first item in meta info.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'showDate',
				array(
					'label'        => __( 'Show date', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'showComments',
				array(
					'label'        => __( 'Show comment count', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'showViews',
				array(
					'label'        => __( 'Show views', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'showReadingTime',
				array(
					'label'        => __( 'Show reading time', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->end_controls_section();

			// -----------------------------------------------------------
			// Layout section
			// -----------------------------------------------------------
			$this->start_controls_section(
				'pmtpofob_section_layout',
				array(
					'label' => __( 'Layout', 'pmt-postgrid-block-editor-elementor-support' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'design',
				array(
					'label'       => __( 'Design', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'design_1',
					'options'     => array(
						'design_1' => __( 'Design 1', 'pmt-postgrid-block-editor-elementor-support' ),
						'design_2' => __( 'Design 2', 'pmt-postgrid-block-editor-elementor-support' ),
						'design_3' => __( 'Design 3', 'pmt-postgrid-block-editor-elementor-support' ),
					),
				)
			);

			$this->add_control(
				'showOrderByDropdown',
				array(
					'label'        => __( 'Show sort filter', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'showCategoryDropdown',
				array(
					'label'        => __( 'Show category filter', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
				)
			);

			$this->add_control(
				'columns',
				array(
					'label'       => __( 'Columns', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => '2',
					'options'     => array(
						'2' => '2',
						'3' => '3',
						'4' => '4',
						'5' => '5',
						'6' => '6',
					),
					'description' => __( 'Default: 2', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'   => array(
						'design' => 'design_1',
					),
				)
			);

			$this->add_control(
				'postsToShow',
				array(
					'label'       => __( 'Number of posts', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 1,
					'max'         => 24,
					'step'        => 1,
					'default'     => 4,
					'description' => __( 'Default: 4', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'   => array(
						'design' => 'design_1',
					),
				)
			);

			$this->add_control(
				'design3PostsToShow',
				array(
					'label'       => __( 'Number of posts', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 1,
					'max'         => 20,
					'step'        => 1,
					'default'     => 3,
					'description' => __( 'Default: 3', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'   => array(
						'design' => 'design_3',
					),
				)
			);

			$this->add_control(
				'design3AlternateImage',
				array(
					'label'        => __( 'Alternate image side', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Yes', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'No', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => '',
					'description'  => __( 'Odd rows image-left, even rows image-right. Default: off (always left).', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'    => array(
						'design' => 'design_3',
					),
				)
			);

			$this->add_control(
				'titleTag',
				array(
					'label'       => __( 'Post title tag', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'h3',
					'options'     => array(
						'h1' => 'H1',
						'h2' => 'H2',
						'h3' => 'H3',
						'h4' => 'H4',
					),
					'description' => __( 'The HTML heading level used for each post title.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'contentAlign',
				array(
					'label'   => __( 'Text alignment', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'    => \Elementor\Controls_Manager::CHOOSE,
					'options' => array(
						'left'   => array(
							'title' => __( 'Left', 'pmt-postgrid-block-editor-elementor-support' ),
							'icon'  => 'eicon-text-align-left',
						),
						'center' => array(
							'title' => __( 'Center', 'pmt-postgrid-block-editor-elementor-support' ),
							'icon'  => 'eicon-text-align-center',
						),
						'right'  => array(
							'title' => __( 'Right', 'pmt-postgrid-block-editor-elementor-support' ),
							'icon'  => 'eicon-text-align-right',
						),
					),
					'default' => 'left',
					'toggle'  => true,
				)
			);

			$repeater = new \Elementor\Repeater();

			$repeater->add_control(
				'element_type',
				array(
					'label'   => __( 'Element', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'title',
					'options' => array(
						'image'   => __( 'Featured image', 'pmt-postgrid-block-editor-elementor-support' ),
						'badge'   => __( 'Category badge', 'pmt-postgrid-block-editor-elementor-support' ),
						'title'   => __( 'Title', 'pmt-postgrid-block-editor-elementor-support' ),
						'excerpt' => __( 'Excerpt', 'pmt-postgrid-block-editor-elementor-support' ),
						'tags'    => __( 'Tags', 'pmt-postgrid-block-editor-elementor-support' ),
					),
				)
			);

			$this->add_control(
				'layoutOrder',
				array(
					'label'       => __( 'Card element order', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::REPEATER,
					'fields'      => $repeater->get_controls(),
					'default'     => array(
						array( 'element_type' => 'image' ),
						array( 'element_type' => 'badge' ),
						array( 'element_type' => 'title' ),
						array( 'element_type' => 'excerpt' ),
						array( 'element_type' => 'tags' ),
					),
					'title_field' => '{{{ element_type }}}',
					'description' => __( 'Controls the stacking order of these elements within each card. Toggles elsewhere still control whether a hidden element shows at all. Meta info and the "Read more" button are not reorderable.', 'pmt-postgrid-block-editor-elementor-support' ),
					'item_actions' => array(
						'add'       => false,
						'duplicate' => false,
						'remove'    => false,
						'sort'      => true,
					),
					'condition'   => array(
						'design' => array( 'design_1', 'design_2' ),
					),
				)
			);

			// Design 3's own repeater: no "Featured image" option at all --
			// image is always its own fixed column there (see Alternate
			// image side above), never a position within the reorderable
			// content stack, so it doesn't appear as a choice here in the
			// first place, not merely ignored if picked.
			$repeater_design3 = new \Elementor\Repeater();

			$repeater_design3->add_control(
				'element_type',
				array(
					'label'   => __( 'Element', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'title',
					'options' => array(
						'badge'   => __( 'Category badge', 'pmt-postgrid-block-editor-elementor-support' ),
						'title'   => __( 'Title', 'pmt-postgrid-block-editor-elementor-support' ),
						'excerpt' => __( 'Excerpt', 'pmt-postgrid-block-editor-elementor-support' ),
						'tags'    => __( 'Tags', 'pmt-postgrid-block-editor-elementor-support' ),
					),
				)
			);

			$this->add_control(
				'layoutOrderDesign3',
				array(
					'label'       => __( 'Card element order', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::REPEATER,
					'fields'      => $repeater_design3->get_controls(),
					'default'     => array(
						array( 'element_type' => 'badge' ),
						array( 'element_type' => 'title' ),
						array( 'element_type' => 'excerpt' ),
						array( 'element_type' => 'tags' ),
					),
					'title_field' => '{{{ element_type }}}',
					'description' => __( 'Controls the stacking order of these elements. Toggles elsewhere still control whether a hidden element shows at all. The image is not reorderable in Design 3.', 'pmt-postgrid-block-editor-elementor-support' ),
					'item_actions' => array(
						'add'       => false,
						'duplicate' => false,
						'remove'    => false,
						'sort'      => true,
					),
					'condition'   => array(
						'design' => 'design_3',
					),
				)
			);

			$this->add_control(
				'pmtpofob_related_posts_heading',
				array(
					'label'     => __( 'Related posts', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
					'condition' => array(
						'design' => 'design_3',
					),
				)
			);

			$this->add_control(
				'relatedPostsSectionTitle',
				array(
					'label'       => __( 'Title', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'default'     => __( 'Related posts', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'   => array(
						'design' => 'design_3',
					),
				)
			);

			$this->add_control(
				'showRelatedImage',
				array(
					'label'        => __( 'Show related post image', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
					'condition'    => array(
						'design' => 'design_3',
					),
				)
			);

			$this->add_control(
				'showRelatedTitle',
				array(
					'label'        => __( 'Show related post title', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'Hide', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => 'yes',
					'description'  => __( 'Up to 4 posts sharing that row\'s category. Not reorderable -- image always first, then title.', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'    => array(
						'design' => 'design_3',
					),
				)
			);

			$this->end_controls_section();

			// -----------------------------------------------------------
			// Style section
			// -----------------------------------------------------------
			$this->start_controls_section(
				'pmtpofob_section_style',
				array(
					'label' => __( 'Style', 'pmt-postgrid-block-editor-elementor-support' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'columnMargin',
				array(
					'label'       => __( 'Column margin (gutter between cards, px)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'max'         => 60,
					'step'        => 1,
					'default'     => 15,
					'description' => __( 'Default: 15', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'rowMargin',
				array(
					'label'       => __( 'Row margin (space between rows, px)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'max'         => 100,
					'step'        => 1,
					'default'     => 15,
					'description' => __( 'Default: 15', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'imageBorderRadius',
				array(
					'label'       => __( 'Image corner radius (px)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'max'         => 100,
					'step'        => 1,
					'default'     => 10,
					'description' => __( 'Default: 10. 0 = square corners.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'pmtpofob_box_shadow_heading',
				array(
					'label'     => __( 'Box shadow', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_control(
				'boxShadowHOffset',
				array(
					'label'       => __( 'Horizontal offset', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => -50,
					'max'         => 50,
					'step'        => 1,
					'default'     => 0,
					'description' => __( 'Default: 0', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'boxShadowVOffset',
				array(
					'label'       => __( 'Vertical offset', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => -50,
					'max'         => 50,
					'step'        => 1,
					'default'     => 2,
					'description' => __( 'Default: 2', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'boxShadowBlur',
				array(
					'label'       => __( 'Blur', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'max'         => 100,
					'step'        => 1,
					'default'     => 12,
					'description' => __( 'Default: 12', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'boxShadowSpread',
				array(
					'label'       => __( 'Spread', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => -50,
					'max'         => 50,
					'step'        => 1,
					'default'     => 0,
					'description' => __( 'Default: 0', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'boxShadowOpacity',
				array(
					'label'       => __( 'Opacity', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SLIDER,
					'range'       => array(
						'px' => array(
							'min'  => 0,
							'max'  => 1,
							'step' => 0.01,
						),
					),
					'default'     => array(
						'size' => 0.09,
					),
					'description' => __( 'Default: 0.09', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'boxShadowColor',
				array(
					'label'       => __( 'Color', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'default'     => '#000000',
					'description' => __( 'Default: #000000. Combined with the Opacity control above.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'boxShadowInset',
				array(
					'label'        => __( 'Inset shadow', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'         => \Elementor\Controls_Manager::SWITCHER,
					'label_on'     => __( 'Yes', 'pmt-postgrid-block-editor-elementor-support' ),
					'label_off'    => __( 'No', 'pmt-postgrid-block-editor-elementor-support' ),
					'return_value' => 'yes',
					'default'      => '',
				)
			);

			$this->add_control(
				'pmtpofob_box_border_heading',
				array(
					'label'     => __( 'Box border', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'      => \Elementor\Controls_Manager::HEADING,
					'separator' => 'before',
				)
			);

			$this->add_control(
				'borderWidth',
				array(
					'label'       => __( 'Border width', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'max'         => 20,
					'step'        => 1,
					'default'     => 0,
					'description' => __( 'Default: 0', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'borderStyle',
				array(
					'label'       => __( 'Border style', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::SELECT,
					'default'     => 'solid',
					'options'     => array(
						'solid'  => __( 'Solid', 'pmt-postgrid-block-editor-elementor-support' ),
						'dashed' => __( 'Dashed', 'pmt-postgrid-block-editor-elementor-support' ),
						'dotted' => __( 'Dotted', 'pmt-postgrid-block-editor-elementor-support' ),
						'double' => __( 'Double', 'pmt-postgrid-block-editor-elementor-support' ),
						'none'   => __( 'None', 'pmt-postgrid-block-editor-elementor-support' ),
					),
					'description' => __( 'Default: Solid', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'borderColor',
				array(
					'label'       => __( 'Border color', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::COLOR,
					'default'     => 'grey',
					'description' => __( 'Default: grey', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'borderRadiusTop',
				array(
					'label'       => __( 'Upper corner radius (px)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'max'         => 100,
					'step'        => 1,
					'default'     => 10,
					'description' => __( 'Rounds the top-left and top-right corners. Default: 10', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'borderRadiusBottom',
				array(
					'label'       => __( 'Lower corner radius (px)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 0,
					'max'         => 100,
					'step'        => 1,
					'default'     => 10,
					'description' => __( 'Rounds the bottom-left and bottom-right corners. Default: 10', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->end_controls_section();

			// -----------------------------------------------------------
			// Typography section
			// -----------------------------------------------------------
			$this->start_controls_section(
				'pmtpofob_section_typography',
				array(
					'label' => __( 'Typography', 'pmt-postgrid-block-editor-elementor-support' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'titleFontSize',
				array(
					'label'       => __( 'Title font size (px)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 10,
					'max'         => 60,
					'step'        => 1,
					'default'     => 20,
					'description' => __( 'Default: 20', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->add_control(
				'titleFontSizeScaleB',
				array(
					'label'       => __( 'Column B title size (% of Column A)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 10,
					'max'         => 150,
					'step'        => 1,
					'default'     => 75,
					'description' => __( 'Default: 75% -- e.g. Column A 20px makes Column B 15px automatically.', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'   => array(
						'design' => 'design_2',
					),
				)
			);

			$this->add_control(
				'relatedTitleScale',
				array(
					'label'       => __( 'Related post title size (% of parent)', 'pmt-postgrid-block-editor-elementor-support' ),
					'type'        => \Elementor\Controls_Manager::NUMBER,
					'min'         => 10,
					'max'         => 150,
					'step'        => 1,
					'default'     => 80,
					'description' => __( 'Default: 80% -- e.g. a 20px row title makes related post titles 16px automatically.', 'pmt-postgrid-block-editor-elementor-support' ),
					'condition'   => array(
						'design' => 'design_3',
					),
				)
			);

			$this->end_controls_section();

			// A dedicated section of its own, right after Typography --
			// not tucked away at the bottom of another section, so it's
			// actually discoverable rather than something you'd only
			// find by accident.
			$this->start_controls_section(
				'pmtpofob_section_reset',
				array(
					'label' => __( 'Reset', 'pmt-postgrid-block-editor-elementor-support' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			// A BUTTON control just fires a named Backbone event on
			// Elementor's editor channel when clicked -- it doesn't touch
			// any setting by itself. block/elementor-editor.js (enqueued
			// only in the Elementor editor, see the
			// elementor/editor/after_enqueue_scripts hook below) listens
			// for that event and resets every control on this widget back
			// to its default value.
			$this->add_control(
				'pmtpofob_reset_settings',
				array(
					'type'        => \Elementor\Controls_Manager::BUTTON,
					'label'       => __( 'Reset', 'pmt-postgrid-block-editor-elementor-support' ),
					'text'        => __( 'Reset to default', 'pmt-postgrid-block-editor-elementor-support' ),
					'button_type' => 'default',
					'event'       => 'pmt:post_grid:reset_settings',
					'description' => __( 'Resets every control on this plugin -- Query, Content, Meta info, Layout, Style, and Typography -- back to its default value. This cannot be undone.', 'pmt-postgrid-block-editor-elementor-support' ),
				)
			);

			$this->end_controls_section();

			$this->start_controls_section(
				'pmtpofob_section_document',
				array(
					'label' => __( 'Document', 'pmt-postgrid-block-editor-elementor-support' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'pmtpofob_document_link',
				array(
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw'  => sprintf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( 'https://www.postmagthemes.com/docs/documentation-for-post-grid-plugins/' ),
						esc_html__( 'Document', 'pmt-postgrid-block-editor-elementor-support' )
					),
				)
			);

			$this->end_controls_section();

			$this->start_controls_section(
				'pmtpofob_section_buy_premium',
				array(
					'label' => __( 'Buy Premium', 'pmt-postgrid-block-editor-elementor-support' ),
					'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
				)
			);

			$this->add_control(
				'pmtpofob_buy_premium_link',
				array(
					'type' => \Elementor\Controls_Manager::RAW_HTML,
					'raw'  => sprintf(
						'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
						esc_url( 'https://www.postmagthemes.com/downloads/pmt-pro-postgrid-block-editor-with-elementor-support/' ),
						esc_html__( 'Buy Premium', 'pmt-postgrid-block-editor-elementor-support' )
					),
				)
			);

			$this->end_controls_section();
		}

		protected function render() {
			$settings = $this->get_settings_for_display();

			$post_ids = array();
			if ( ! empty( $settings['postIds'] ) && is_array( $settings['postIds'] ) ) {
				$post_ids = array_map( 'intval', $settings['postIds'] );
			}

			$exclude_post_ids = array();
			if ( ! empty( $settings['excludePostIds'] ) && is_array( $settings['excludePostIds'] ) ) {
				$exclude_post_ids = array_map( 'intval', $settings['excludePostIds'] );
			}

			// The SLIDER control type stores its value as an array (e.g.
			// array( 'size' => 0.09, 'unit' => 'px' )) rather than a plain
			// scalar, so it needs unwrapping before being handed to the
			// shared markup builder.
			$box_shadow_opacity = 0.09;
			if ( isset( $settings['boxShadowOpacity'] ) ) {
				if ( is_array( $settings['boxShadowOpacity'] ) && isset( $settings['boxShadowOpacity']['size'] ) && '' !== $settings['boxShadowOpacity']['size'] ) {
					$box_shadow_opacity = (float) $settings['boxShadowOpacity']['size'];
				} elseif ( is_numeric( $settings['boxShadowOpacity'] ) ) {
					$box_shadow_opacity = (float) $settings['boxShadowOpacity'];
				}
			}

			// The REPEATER control stores rows as an array of associative
			// arrays; pull out just the element_type from each row, in the
			// order the user arranged them (Elementor handles the actual
			// drag-to-reorder -- this just reads the result).
			$raw_layout_order = array();
			if ( ! empty( $settings['layoutOrder'] ) && is_array( $settings['layoutOrder'] ) ) {
				foreach ( $settings['layoutOrder'] as $row ) {
					if ( isset( $row['element_type'] ) ) {
						$raw_layout_order[] = $row['element_type'];
					}
				}
			}
			$layout_order = pmtpofob_post_grid_sanitize_layout_order( $raw_layout_order );

			$raw_layout_order_design3 = array();
			if ( ! empty( $settings['layoutOrderDesign3'] ) && is_array( $settings['layoutOrderDesign3'] ) ) {
				foreach ( $settings['layoutOrderDesign3'] as $row ) {
					if ( isset( $row['element_type'] ) ) {
						$raw_layout_order_design3[] = $row['element_type'];
					}
				}
			}
			$layout_order_design3 = pmtpofob_post_grid_sanitize_layout_order_design3( $raw_layout_order_design3 );

			$args = array(
				'design'            => isset( $settings['design'] ) ? $settings['design'] : 'design_1',
				'showOrderByDropdown'  => isset( $settings['showOrderByDropdown'] ) && 'yes' === $settings['showOrderByDropdown'],
				'showCategoryDropdown' => isset( $settings['showCategoryDropdown'] ) && 'yes' === $settings['showCategoryDropdown'],
				'showMainTitle'     => isset( $settings['showMainTitle'] ) && 'yes' === $settings['showMainTitle'],
				'mainTitleText'     => isset( $settings['mainTitleText'] ) ? $settings['mainTitleText'] : '',
				'mainTitleTag'      => isset( $settings['mainTitleTag'] ) ? $settings['mainTitleTag'] : 'h2',
				'columns'           => isset( $settings['columns'] ) ? (int) $settings['columns'] : 2,
				'postsToShow'       => isset( $settings['postsToShow'] ) ? (int) $settings['postsToShow'] : 4,
				'design3PostsToShow'    => isset( $settings['design3PostsToShow'] ) ? (int) $settings['design3PostsToShow'] : 3,
				'design3AlternateImage' => isset( $settings['design3AlternateImage'] ) && 'yes' === $settings['design3AlternateImage'],
				'relatedPostsSectionTitle' => isset( $settings['relatedPostsSectionTitle'] ) ? $settings['relatedPostsSectionTitle'] : __( 'Related posts', 'pmt-postgrid-block-editor-elementor-support' ),
				'showRelatedImage'  => isset( $settings['showRelatedImage'] ) && 'yes' === $settings['showRelatedImage'],
				'showRelatedTitle'  => isset( $settings['showRelatedTitle'] ) && 'yes' === $settings['showRelatedTitle'],
				'relatedTitleScale' => isset( $settings['relatedTitleScale'] ) ? (int) $settings['relatedTitleScale'] : 80,
				'category'          => isset( $settings['category'] ) ? $settings['category'] : '',
				'author'            => isset( $settings['author'] ) ? $settings['author'] : '',
				'postIds'           => $post_ids,
				'excludePostIds'    => $exclude_post_ids,
				'orderBy'           => isset( $settings['orderBy'] ) ? $settings['orderBy'] : 'date',
				'showAuthor'        => isset( $settings['showAuthor'] ) && 'yes' === $settings['showAuthor'],
				'showDate'          => isset( $settings['showDate'] ) && 'yes' === $settings['showDate'],
				'showComments'      => isset( $settings['showComments'] ) && 'yes' === $settings['showComments'],
				'showViews'         => isset( $settings['showViews'] ) && 'yes' === $settings['showViews'],
				'showReadingTime'   => isset( $settings['showReadingTime'] ) && 'yes' === $settings['showReadingTime'],
				'showCategoryBadge' => isset( $settings['showCategoryBadge'] ) && 'yes' === $settings['showCategoryBadge'],
				'showReadMore'      => isset( $settings['showReadMore'] ) && 'yes' === $settings['showReadMore'],
				'readMoreText'      => isset( $settings['readMoreText'] ) && '' !== $settings['readMoreText'] ? $settings['readMoreText'] : __( 'Read More', 'pmt-postgrid-block-editor-elementor-support' ),
				'showStructuredData' => isset( $settings['showStructuredData'] ) && 'yes' === $settings['showStructuredData'],
				'showImage'         => isset( $settings['showImage'] ) && 'yes' === $settings['showImage'],
				'showExcerpt'       => isset( $settings['showExcerpt'] ) && 'yes' === $settings['showExcerpt'],
				'excerptLength'     => isset( $settings['excerptLength'] ) ? (int) $settings['excerptLength'] : 20,
				'showTags'          => isset( $settings['showTags'] ) && 'yes' === $settings['showTags'],
				'contentAlign'      => isset( $settings['contentAlign'] ) ? $settings['contentAlign'] : 'left',
				'titleTag'          => isset( $settings['titleTag'] ) ? $settings['titleTag'] : 'h3',
				'columnMargin'      => isset( $settings['columnMargin'] ) ? (int) $settings['columnMargin'] : 15,
				'rowMargin'         => isset( $settings['rowMargin'] ) ? (int) $settings['rowMargin'] : 15,
				'imageBorderRadius' => isset( $settings['imageBorderRadius'] ) ? (int) $settings['imageBorderRadius'] : 10,
				'titleFontSize'     => isset( $settings['titleFontSize'] ) ? (int) $settings['titleFontSize'] : 20,
				'titleFontSizeScaleB' => isset( $settings['titleFontSizeScaleB'] ) ? (int) $settings['titleFontSizeScaleB'] : 75,
				'boxShadowHOffset'  => isset( $settings['boxShadowHOffset'] ) ? (int) $settings['boxShadowHOffset'] : 0,
				'boxShadowVOffset'  => isset( $settings['boxShadowVOffset'] ) ? (int) $settings['boxShadowVOffset'] : 2,
				'boxShadowBlur'     => isset( $settings['boxShadowBlur'] ) ? (int) $settings['boxShadowBlur'] : 12,
				'boxShadowSpread'   => isset( $settings['boxShadowSpread'] ) ? (int) $settings['boxShadowSpread'] : 0,
				'boxShadowColor'    => isset( $settings['boxShadowColor'] ) && '' !== $settings['boxShadowColor'] ? $settings['boxShadowColor'] : '#000000',
				'boxShadowOpacity'  => $box_shadow_opacity,
				'boxShadowInset'    => isset( $settings['boxShadowInset'] ) && 'yes' === $settings['boxShadowInset'],
				'borderWidth'       => isset( $settings['borderWidth'] ) ? (int) $settings['borderWidth'] : 0,
				'borderStyle'       => isset( $settings['borderStyle'] ) && '' !== $settings['borderStyle'] ? $settings['borderStyle'] : 'solid',
				'borderColor'       => isset( $settings['borderColor'] ) && '' !== $settings['borderColor'] ? $settings['borderColor'] : 'grey',
				'borderRadiusTop'    => isset( $settings['borderRadiusTop'] ) ? (int) $settings['borderRadiusTop'] : 10,
				'borderRadiusBottom' => isset( $settings['borderRadiusBottom'] ) ? (int) $settings['borderRadiusBottom'] : 10,
				'layoutOrder'       => $layout_order,
				'layoutOrderDesign3'    => $layout_order_design3,
				'align_class'       => '',
			);

			$structured_data_items = array();
			$html                   = pmtpofob_post_grid_build_markup( $args, $structured_data_items );
			echo wp_kses( $html, pmtpofob_post_grid_allowed_output_html() );
			pmtpofob_post_grid_print_structured_data( $args, $structured_data_items );
		}
	}
}
