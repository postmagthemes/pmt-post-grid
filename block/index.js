(function (wp) {
	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var CheckboxControl = wp.components.CheckboxControl;
	var ButtonGroup = wp.components.ButtonGroup;
	var Button = wp.components.Button;
	var Spinner = wp.components.Spinner;
	var TextControl = wp.components.TextControl;
	var ColorPicker = wp.components.ColorPicker;
	var Dropdown = wp.components.Dropdown;
	var ColorIndicator = wp.components.ColorIndicator;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;

	var ALIGN_OPTIONS = [
		{ value: 'left', label: __('Left', 'pmt-postgrid-block-editor-elementor-support'), icon: 'editor-alignleft' },
		{ value: 'center', label: __('Center', 'pmt-postgrid-block-editor-elementor-support'), icon: 'editor-aligncenter' },
		{ value: 'right', label: __('Right', 'pmt-postgrid-block-editor-elementor-support'), icon: 'editor-alignright' },
	];

	var TITLE_TAG_OPTIONS = [
		{ label: 'H1', value: 'h1' },
		{ label: 'H2', value: 'h2' },
		{ label: 'H3', value: 'h3' },
		{ label: 'H4', value: 'h4' },
	];

	var DEFAULT_LAYOUT_ORDER = ['image', 'badge', 'title', 'excerpt', 'tags'];
	var DEFAULT_LAYOUT_ORDER_DESIGN3 = ['badge', 'title', 'excerpt', 'tags'];

	// Used by the "Reset all settings" button at the bottom of the
	// Typography panel. Hand-kept in sync with the 'default' values in
	// this block's attribute schema (register_block_type() in
	// pmtpofob-post-grid.php) -- if a default ever changes there, this needs
	// updating too. Deliberately excludes 'align' (block placement, not
	// a widget setting) -- everything else the panels expose is here.
	var PMTPOFOB_DEFAULT_ATTRIBUTES = {
		columns: 2,
		design: 'design_1',
		postsToShow: 4,
		category: '',
		categoryLabel: 'All categories',
		postIds: [],
		author: '',
		excludePostIds: [],
		orderBy: 'date',
		showAuthor: true,
		showDate: true,
		showComments: true,
		showViews: true,
		showReadingTime: true,
		showCategoryBadge: true,
		showImage: true,
		showExcerpt: true,
		excerptLength: 20,
		showTags: false,
		contentAlign: 'left',
		titleTag: 'h3',
		columnMargin: 15,
		rowMargin: 15,
		imageBorderRadius: 10,
		boxShadowHOffset: 0,
		boxShadowVOffset: 2,
		boxShadowBlur: 12,
		boxShadowSpread: 0,
		boxShadowColor: '#000000',
		boxShadowOpacity: 0.09,
		boxShadowInset: false,
		borderWidth: 0,
		borderStyle: 'solid',
		borderColor: 'grey',
		borderRadiusTop: 10,
		borderRadiusBottom: 10,
		titleFontSize: 20,
		titleFontSizeScaleB: 75,
		showMainTitle: false,
		mainTitleText: '',
		mainTitleTag: 'h2',
		showReadMore: true,
		readMoreText: 'Read More',
		layoutOrder: ['image', 'badge', 'title', 'excerpt', 'tags'],
		design3PostsToShow: 3,
		design3AlternateImage: false,
		layoutOrderDesign3: ['badge', 'title', 'excerpt', 'tags'],
		relatedPostsSectionTitle: 'Related Posts',
		showRelatedImage: true,
		showRelatedTitle: true,
		relatedTitleScale: 80,
		showOrderByDropdown: true,
		showCategoryDropdown: true,
		showStructuredData: true,
	};

	var LAYOUT_ORDER_LABELS = {
		image: __('Featured image', 'pmt-postgrid-block-editor-elementor-support'),
		badge: __('Category badge', 'pmt-postgrid-block-editor-elementor-support'),
		title: __('Title', 'pmt-postgrid-block-editor-elementor-support'),
		excerpt: __('Excerpt', 'pmt-postgrid-block-editor-elementor-support'),
		tags: __('Tags', 'pmt-postgrid-block-editor-elementor-support'),
	};

	// Swaps the item at `index` with its neighbor in the `delta` direction
	// (-1 = up, 1 = down). Returns the same array unchanged if the move
	// would go out of bounds, so callers can call this unconditionally.
	function moveLayoutOrderItem(order, index, delta) {
		var next = order.slice();
		var swapIndex = index + delta;
		if (swapIndex < 0 || swapIndex >= next.length) {
			return next;
		}
		var temp = next[index];
		next[index] = next[swapIndex];
		next[swapIndex] = temp;
		return next;
	}

	registerBlockType('pmt/post-grid', {
		title: __('Post Grid (Postmagthemes)', 'pmt-postgrid-block-editor-elementor-support'),
		icon: 'grid-view',
		category: 'postmagthemes',
		supports: {
			align: ['wide', 'full'],
		},

		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var categoriesState = useState([]);
			var categories = categoriesState[0];
			var setCategories = categoriesState[1];

			var authorsState = useState([]);
			var authors = authorsState[0];
			var setAuthors = authorsState[1];

			var allPostsState = useState([]);
			var allPosts = allPostsState[0];
			var setAllPosts = allPostsState[1];

			var postsLoadingState = useState(true);
			var postsLoading = postsLoadingState[0];
			var setPostsLoading = postsLoadingState[1];

			var postSearchState = useState('');
			var postSearch = postSearchState[0];
			var setPostSearch = postSearchState[1];

			var excludeSearchState = useState('');
			var excludeSearch = excludeSearchState[0];
			var setExcludeSearch = excludeSearchState[1];

			// Categories: populate the filter dropdown in the sidebar.
			useEffect(function () {
				apiFetch({ path: '/wp/v2/categories?per_page=100&orderby=name&order=asc' })
					.then(function (results) {
						setCategories(results);
					})
					.catch(function () {
						// Non-fatal: category filter just won't populate.
					});
			}, []);

			// Authors: populate the Author filter dropdown. `who=authors`
			// keeps this publicly readable (only lists users with
			// published posts) rather than requiring elevated permissions.
			useEffect(function () {
				apiFetch({ path: '/wp/v2/users?who=authors&per_page=100&orderby=name&order=asc' })
					.then(function (results) {
						setAuthors(results);
					})
					.catch(function () {
						// Non-fatal: author filter just won't populate.
					});
			}, []);

			// All posts (id + title only): populate the "specific posts"
			// checkbox list. The actual query still runs in PHP -- this is
			// just for building the picker UI.
			useEffect(function () {
				apiFetch({ path: '/wp/v2/posts?per_page=100&orderby=title&order=asc&_fields=id,title' })
					.then(function (results) {
						setAllPosts(results);
						setPostsLoading(false);
					})
					.catch(function () {
						setPostsLoading(false);
					});
			}, []);

			var categoryOptions = [{ label: __('All categories', 'pmt-postgrid-block-editor-elementor-support'), value: '' }].concat(
				categories.map(function (cat) {
					return { label: cat.name + ' (' + cat.count + ')', value: String(cat.id) };
				})
			);

			var authorOptions = [{ label: __('All authors', 'pmt-postgrid-block-editor-elementor-support'), value: '' }].concat(
				authors.map(function (author) {
					return { label: author.name, value: String(author.id) };
				})
			);

			var selectedPostIds = attributes.postIds || [];

			function togglePostId(id, checked) {
				var next;
				if (checked) {
					next = selectedPostIds.concat([id]);
				} else {
					next = selectedPostIds.filter(function (existingId) {
						return existingId !== id;
					});
				}
				setAttributes({ postIds: next });
			}

			var filteredPosts = allPosts.filter(function (post) {
				var title = post.title && post.title.rendered ? post.title.rendered : '';
				return title.toLowerCase().indexOf(postSearch.toLowerCase()) !== -1;
			});

			var selectedExcludeIds = attributes.excludePostIds || [];

			function toggleExcludeId(id, checked) {
				var next;
				if (checked) {
					next = selectedExcludeIds.concat([id]);
				} else {
					next = selectedExcludeIds.filter(function (existingId) {
						return existingId !== id;
					});
				}
				setAttributes({ excludePostIds: next });
			}

			var filteredExcludePosts = allPosts.filter(function (post) {
				var title = post.title && post.title.rendered ? post.title.rendered : '';
				return title.toLowerCase().indexOf(excludeSearch.toLowerCase()) !== -1;
			});

			return el(
				wp.element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Query', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: true },
						el(SelectControl, {
							label: __('Category', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.category,
							options: categoryOptions,
							disabled: selectedPostIds.length > 0,
							help: selectedPostIds.length
								? __('Ignored while specific posts are selected below.', 'pmt-postgrid-block-editor-elementor-support')
								: undefined,
							onChange: function (val) {
								var found = categoryOptions.filter(function (o) {
									return o.value === val;
								})[0];
								setAttributes({
									category: val,
									categoryLabel: found ? found.label : 'All categories',
								});
							},
						}),
						el(SelectControl, {
							label: __('Author', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.author,
							options: authorOptions,
							disabled: selectedPostIds.length > 0,
							help: selectedPostIds.length
								? __('Ignored while specific posts are selected below.', 'pmt-postgrid-block-editor-elementor-support')
								: __('Default: All authors', 'pmt-postgrid-block-editor-elementor-support'),
							onChange: function (val) {
								setAttributes({ author: val });
							},
						}),
						el(SelectControl, {
							label: __('Order by', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.orderBy,
							disabled: selectedPostIds.length > 0,
							options: [
								{ label: __('Date', 'pmt-postgrid-block-editor-elementor-support'), value: 'date' },
								{ label: __('Comment count', 'pmt-postgrid-block-editor-elementor-support'), value: 'comment_count' },
							],
							help: __('Always newest/most-commented first.', 'pmt-postgrid-block-editor-elementor-support'),
							onChange: function (val) {
								setAttributes({ orderBy: val });
							},
						})
					),
					el(
						PanelBody,
						{ title: __('Specific posts', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: false },
						el(
							'p',
							{ style: { fontSize: '12px', color: '#757575' } },
							__('Pick exact posts to show by title. When any are checked, they completely override Category/Order by/Number of posts of above.', 'pmt-postgrid-block-editor-elementor-support')
						),
						el(TextControl, {
							label: __('Search titles', 'pmt-postgrid-block-editor-elementor-support'),
							value: postSearch,
							onChange: setPostSearch,
						}),
						postsLoading
							? el(Spinner, null)
							: el(
								'div',
								{ style: { maxHeight: '260px', overflowY: 'auto', border: '1px solid #ddd', borderRadius: '4px', padding: '8px' } },
								filteredPosts.length
									? filteredPosts.map(function (post) {
										var title = post.title && post.title.rendered ? post.title.rendered : '(' + __('no title', 'pmt-postgrid-block-editor-elementor-support') + ')';
										return el(CheckboxControl, {
											key: post.id,
											label: title,
											checked: selectedPostIds.indexOf(post.id) !== -1,
											onChange: function (checked) {
												togglePostId(post.id, checked);
											},
										});
									})
									: el('p', { style: { fontSize: '12px', color: '#757575' } }, __('No posts match that search.', 'pmt-postgrid-block-editor-elementor-support'))
							),
						selectedPostIds.length
							? el(
								Button,
								{
									variant: 'link',
									isDestructive: true,
									style: { marginTop: '8px' },
									onClick: function () {
										setAttributes({ postIds: [] });
									},
								},
								__('Clear selection', 'pmt-postgrid-block-editor-elementor-support') + ' (' + selectedPostIds.length + ')'
							)
							: null
					),
					el(
						PanelBody,
						{ title: __('Exclude posts', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: false },
						el(
							'p',
							{ style: { fontSize: '12px', color: '#757575' } },
							selectedPostIds.length
								? __('Ignored while specific posts are selected above.', 'pmt-postgrid-block-editor-elementor-support')
								: __('Posts checked here are removed from the results, on top of Category/Author/Order above.', 'pmt-postgrid-block-editor-elementor-support')
						),
						el(TextControl, {
							label: __('Search titles', 'pmt-postgrid-block-editor-elementor-support'),
							value: excludeSearch,
							disabled: selectedPostIds.length > 0,
							onChange: setExcludeSearch,
						}),
						postsLoading
							? el(Spinner, null)
							: el(
								'div',
								{ style: { maxHeight: '260px', overflowY: 'auto', border: '1px solid #ddd', borderRadius: '4px', padding: '8px', opacity: selectedPostIds.length > 0 ? 0.5 : 1, pointerEvents: selectedPostIds.length > 0 ? 'none' : 'auto' } },
								filteredExcludePosts.length
									? filteredExcludePosts.map(function (post) {
										var title = post.title && post.title.rendered ? post.title.rendered : '(' + __('no title', 'pmt-postgrid-block-editor-elementor-support') + ')';
										return el(CheckboxControl, {
											key: post.id,
											label: title,
											checked: selectedExcludeIds.indexOf(post.id) !== -1,
											onChange: function (checked) {
												toggleExcludeId(post.id, checked);
											},
										});
									})
									: el('p', { style: { fontSize: '12px', color: '#757575' } }, __('No posts match that search.', 'pmt-postgrid-block-editor-elementor-support'))
							),
						selectedExcludeIds.length
							? el(
								Button,
								{
									variant: 'link',
									isDestructive: true,
									style: { marginTop: '8px' },
									onClick: function () {
										setAttributes({ excludePostIds: [] });
									},
								},
								__('Clear exclusions', 'pmt-postgrid-block-editor-elementor-support') + ' (' + selectedExcludeIds.length + ')'
							)
							: null
					),
					el(
						PanelBody,
						{ title: __('Content', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: false },
						el(ToggleControl, {
							label: __('Show main title', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showMainTitle,
							onChange: function (val) {
								setAttributes({ showMainTitle: val });
							},
							help: __('A section heading above the grid, higher-level than the post title.', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						attributes.showMainTitle
							? el(TextControl, {
								label: __('Main title text', 'pmt-postgrid-block-editor-elementor-support'),
								value: attributes.mainTitleText,
								onChange: function (val) {
									setAttributes({ mainTitleText: val });
								},
								placeholder: __('e.g. Latest posts', 'pmt-postgrid-block-editor-elementor-support'),
							})
							: null,
						attributes.showMainTitle
							? el(SelectControl, {
								label: __('Main title tag', 'pmt-postgrid-block-editor-elementor-support'),
								value: attributes.mainTitleTag,
								options: [
									{ label: 'H1', value: 'h1' },
									{ label: 'H2', value: 'h2' },
								],
								help: __('Default: H2', 'pmt-postgrid-block-editor-elementor-support'),
								onChange: function (val) {
									setAttributes({ mainTitleTag: val });
								},
							})
							: null,
						el(ToggleControl, {
							label: __('Show featured image', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showImage,
							onChange: function (val) {
								setAttributes({ showImage: val });
							},
						}),
						el(ToggleControl, {
							label: __('Show excerpt', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showExcerpt,
							onChange: function (val) {
								setAttributes({ showExcerpt: val });
							},
						}),
						attributes.showExcerpt
							? el(RangeControl, {
								label: __('Excerpt length (words)', 'pmt-postgrid-block-editor-elementor-support'),
								value: attributes.excerptLength,
								onChange: function (val) {
									setAttributes({ excerptLength: val });
								},
								min: 5,
								max: 100,
								help: __('Default: 20', 'pmt-postgrid-block-editor-elementor-support'),
							})
							: null,
						el(ToggleControl, {
							label: __('Show tags', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showTags,
							onChange: function (val) {
								setAttributes({ showTags: val });
							},
						}),
						el(ToggleControl, {
							label: __('Show category badge', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showCategoryBadge,
							onChange: function (val) {
								setAttributes({ showCategoryBadge: val });
							},
						}),
						el(ToggleControl, {
							label: __('Show "Read more" button', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showReadMore,
							onChange: function (val) {
								setAttributes({ showReadMore: val });
							},
						}),
						attributes.showReadMore
							? el(TextControl, {
								label: __('Button text', 'pmt-postgrid-block-editor-elementor-support'),
								value: attributes.readMoreText,
								onChange: function (val) {
									setAttributes({ readMoreText: val });
								},
							})
							: null,
						el(ToggleControl, {
							label: __('Add structured data (SEO)', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showStructuredData,
							onChange: function (val) {
								setAttributes({ showStructuredData: val });
							},
							help: __('Outputs a schema.org ItemList block listing these posts, for richer search results. Default: on. Turn off if your SEO plugin already outputs its own listing schema for this page.', 'pmt-postgrid-block-editor-elementor-support'),
						})
					),
					el(
						PanelBody,
						{ title: __('Meta info', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: false },
						el(ToggleControl, {
							label: __('Show author (avatar + name)', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showAuthor,
							onChange: function (val) {
								setAttributes({ showAuthor: val });
							},
							help: __('Always the first item in meta info.', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(ToggleControl, {
							label: __('Show date', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showDate,
							onChange: function (val) {
								setAttributes({ showDate: val });
							},
						}),
						el(ToggleControl, {
							label: __('Show comment count', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showComments,
							onChange: function (val) {
								setAttributes({ showComments: val });
							},
						}),
						el(ToggleControl, {
							label: __('Show views', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showViews,
							onChange: function (val) {
								setAttributes({ showViews: val });
							},
						}),
						el(ToggleControl, {
							label: __('Show reading time', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showReadingTime,
							onChange: function (val) {
								setAttributes({ showReadingTime: val });
							},
						})
					),
					el(
						PanelBody,
						{ title: __('Layout', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: false },
						el(SelectControl, {
							label: __('Design', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.design || 'design_1',
							options: [
								{ label: __('Design 1', 'pmt-postgrid-block-editor-elementor-support'), value: 'design_1' },
								{ label: __('Design 2', 'pmt-postgrid-block-editor-elementor-support'), value: 'design_2' },
								{ label: __('Design 3', 'pmt-postgrid-block-editor-elementor-support'), value: 'design_3' },
							],
							onChange: function (val) {
								setAttributes({ design: val });
							},
						}),
						el(ToggleControl, {
							label: __('Show sort filter (Most recent / Most commented)', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showOrderByDropdown,
							onChange: function (val) {
								setAttributes({ showOrderByDropdown: val });
							},
						}),
						el(ToggleControl, {
							label: __('Show category filter', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.showCategoryDropdown,
							onChange: function (val) {
								setAttributes({ showCategoryDropdown: val });
							},
						}),
						(attributes.design || 'design_1') === 'design_1'
							? el(RangeControl, {
								label: __('Columns', 'pmt-postgrid-block-editor-elementor-support'),
								value: attributes.columns,
								onChange: function (val) {
									setAttributes({ columns: val });
								},
								min: 2,
								max: 6,
								help: __('Default: 2', 'pmt-postgrid-block-editor-elementor-support'),
							})
							: null,
						(attributes.design || 'design_1') === 'design_1'
							? el(RangeControl, {
								label: __('Number of posts', 'pmt-postgrid-block-editor-elementor-support'),
								value: attributes.postsToShow,
								onChange: function (val) {
									setAttributes({ postsToShow: val });
								},
								min: 1,
								max: 24,
								help: selectedPostIds.length
									? __('Ignored while specific posts are selected below.', 'pmt-postgrid-block-editor-elementor-support')
									: __('Default: 4', 'pmt-postgrid-block-editor-elementor-support'),
							})
							: null,
						(attributes.design || 'design_1') === 'design_3'
							? el(RangeControl, {
								label: __('Number of posts', 'pmt-postgrid-block-editor-elementor-support'),
								value: attributes.design3PostsToShow,
								onChange: function (val) {
									setAttributes({ design3PostsToShow: val });
								},
								min: 1,
								max: 20,
								help: selectedPostIds.length
									? __('Ignored while specific posts are selected below.', 'pmt-postgrid-block-editor-elementor-support')
									: __('Default: 3', 'pmt-postgrid-block-editor-elementor-support'),
							})
							: null,
						(attributes.design || 'design_1') === 'design_3'
							? el(ToggleControl, {
								label: __('Alternate image side', 'pmt-postgrid-block-editor-elementor-support'),
								checked: attributes.design3AlternateImage,
								onChange: function (val) {
									setAttributes({ design3AlternateImage: val });
								},
								help: __('Odd rows image-left, even rows image-right. Default: off (always left).', 'pmt-postgrid-block-editor-elementor-support'),
							})
							: null,
						el(SelectControl, {
							label: __('Post title tag', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.titleTag,
							options: TITLE_TAG_OPTIONS,
							help: __('The HTML heading level used for each post title. Default: H3.', 'pmt-postgrid-block-editor-elementor-support'),
							onChange: function (val) {
								setAttributes({ titleTag: val });
							},
						}),
						el(
							'div',
							{ style: { marginTop: '16px' } },
							el('p', { style: { marginBottom: '8px', fontWeight: 500 } }, __('Text alignment', 'pmt-postgrid-block-editor-elementor-support')),
							el(
								ButtonGroup,
								null,
								ALIGN_OPTIONS.map(function (opt) {
									return el(Button, {
										key: opt.value,
										icon: opt.icon,
										label: opt.label,
										isPressed: attributes.contentAlign === opt.value,
										onClick: function () {
											setAttributes({ contentAlign: opt.value });
										},
									});
								})
							)
						),
						el(
							'div',
							{ style: { marginTop: '16px' } },
							el('p', { style: { marginBottom: '4px', fontWeight: 500 } }, __('Card element order', 'pmt-postgrid-block-editor-elementor-support')),
							el(
								'p',
								{ style: { fontSize: '12px', color: '#757575', marginTop: 0, marginBottom: '8px' } },
								(attributes.design || 'design_1') === 'design_3'
									? __('Controls the stacking order of these elements. Toggles elsewhere still control whether a hidden element shows at all. The image is not reorderable in Design 3.', 'pmt-postgrid-block-editor-elementor-support')
									: __('Controls the stacking order of these elements within each card. Toggles elsewhere still control whether a hidden element shows at all. Meta info and the "Read more" button are not reorderable.', 'pmt-postgrid-block-editor-elementor-support')
							),
							(function () {
								var isDesign3 = (attributes.design || 'design_1') === 'design_3';
								var orderKey = isDesign3 ? 'layoutOrderDesign3' : 'layoutOrder';
								var defaultOrder = isDesign3 ? DEFAULT_LAYOUT_ORDER_DESIGN3 : DEFAULT_LAYOUT_ORDER;
								var activeOrder = (attributes[orderKey] && attributes[orderKey].length) ? attributes[orderKey] : defaultOrder;

								return el(
									'div',
									{ style: { border: '1px solid #ddd', borderRadius: '4px', overflow: 'hidden' } },
									activeOrder.map(function (key, index, order) {
										return el(
											'div',
											{
												key: key,
												style: {
													display: 'flex',
													alignItems: 'center',
													justifyContent: 'space-between',
													padding: '6px 10px',
													borderTop: index === 0 ? 'none' : '1px solid #eee',
												},
											},
											el('span', { style: { fontSize: '13px' } }, LAYOUT_ORDER_LABELS[key] || key),
											el(
												'span',
												null,
												el(Button, {
													icon: 'arrow-up-alt2',
													isSmall: true,
													disabled: index === 0,
													onClick: function () {
														var update = {};
														update[orderKey] = moveLayoutOrderItem(order, index, -1);
														setAttributes(update);
													},
												}),
												el(Button, {
													icon: 'arrow-down-alt2',
													isSmall: true,
													disabled: index === order.length - 1,
													onClick: function () {
														var update = {};
														update[orderKey] = moveLayoutOrderItem(order, index, 1);
														setAttributes(update);
													},
												})
											)
										);
									})
								);
							})()
						),
						(attributes.design || 'design_1') === 'design_3'
							? el(
								'div',
								{ style: { marginTop: '16px' } },
								el('p', { style: { marginBottom: '4px', fontWeight: 500 } }, __('Related posts', 'pmt-postgrid-block-editor-elementor-support')),
								el(TextControl, {
									label: __('Title', 'pmt-postgrid-block-editor-elementor-support'),
									value: attributes.relatedPostsSectionTitle,
									onChange: function (val) {
										setAttributes({ relatedPostsSectionTitle: val });
									},
								}),
								el(ToggleControl, {
									label: __('Show related post image', 'pmt-postgrid-block-editor-elementor-support'),
									checked: attributes.showRelatedImage,
									onChange: function (val) {
										setAttributes({ showRelatedImage: val });
									},
								}),
								el(ToggleControl, {
									label: __('Show related post title', 'pmt-postgrid-block-editor-elementor-support'),
									checked: attributes.showRelatedTitle,
									onChange: function (val) {
										setAttributes({ showRelatedTitle: val });
									},
									help: __('Up to 4 posts sharing that row\'s category. Not reorderable -- image always first, then title.', 'pmt-postgrid-block-editor-elementor-support'),
								})
							)
							: null
					),
					el(
						PanelBody,
						{ title: __('Style', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: false },
						el(RangeControl, {
							label: __('Column margin (gutter between cards)', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.columnMargin,
							onChange: function (val) {
								setAttributes({ columnMargin: val });
							},
							min: 0,
							max: 60,
							help: __('Default: 15px', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(RangeControl, {
							label: __('Row margin (space between rows)', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.rowMargin,
							onChange: function (val) {
								setAttributes({ rowMargin: val });
							},
							min: 0,
							max: 100,
							help: __('Default: 15px', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(RangeControl, {
							label: __('Image corner radius', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.imageBorderRadius,
							onChange: function (val) {
								setAttributes({ imageBorderRadius: val });
							},
							min: 0,
							max: 100,
							help: __('Default: 10px. 0 = square corners.', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el('p', { style: { marginTop: '16px', marginBottom: '8px', fontWeight: 500 } }, __('Box shadow', 'pmt-postgrid-block-editor-elementor-support')),
						el(RangeControl, {
							label: __('Horizontal offset', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.boxShadowHOffset,
							onChange: function (val) {
								setAttributes({ boxShadowHOffset: val });
							},
							min: -50,
							max: 50,
							help: __('Default: 0px', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(RangeControl, {
							label: __('Vertical offset', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.boxShadowVOffset,
							onChange: function (val) {
								setAttributes({ boxShadowVOffset: val });
							},
							min: -50,
							max: 50,
							help: __('Default: 2px', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(RangeControl, {
							label: __('Blur', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.boxShadowBlur,
							onChange: function (val) {
								setAttributes({ boxShadowBlur: val });
							},
							min: 0,
							max: 100,
							help: __('Default: 12px', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(RangeControl, {
							label: __('Spread', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.boxShadowSpread,
							onChange: function (val) {
								setAttributes({ boxShadowSpread: val });
							},
							min: -50,
							max: 50,
							help: __('Default: 0px', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(RangeControl, {
							label: __('Opacity', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.boxShadowOpacity,
							onChange: function (val) {
								setAttributes({ boxShadowOpacity: val });
							},
							min: 0,
							max: 1,
							step: 0.01,
							help: __('Default: 0.09', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(
							'div',
							{ style: { marginBottom: '8px' } },
							el('p', { style: { marginBottom: '8px', fontWeight: 500 } }, __('Color', 'pmt-postgrid-block-editor-elementor-support') + ' ' + __('(Default: #000000)', 'pmt-postgrid-block-editor-elementor-support')),
							el(Dropdown, {
								className: 'pmtpofob-color-dropdown',
								contentClassName: 'pmtpofob-color-dropdown__content',
								renderToggle: function (toggleProps) {
									return el(
										Button,
										{
											onClick: toggleProps.onToggle,
											'aria-expanded': toggleProps.isOpen,
											variant: 'secondary',
											style: { display: 'flex', alignItems: 'center', gap: '8px' },
										},
										el(ColorIndicator, { colorValue: attributes.boxShadowColor }),
										attributes.boxShadowColor || __('Pick a color', 'pmt-postgrid-block-editor-elementor-support')
									);
								},
								renderContent: function () {
									return el(
										'div',
										{ style: { padding: '8px' } },
										el(ColorPicker, {
											color: attributes.boxShadowColor,
											onChange: function (val) {
												setAttributes({ boxShadowColor: val });
											},
											enableAlpha: false,
										})
									);
								},
							})
						),
						el(ToggleControl, {
							label: __('Inset shadow', 'pmt-postgrid-block-editor-elementor-support'),
							checked: attributes.boxShadowInset,
							onChange: function (val) {
								setAttributes({ boxShadowInset: val });
							},
						}),
						el('p', { style: { marginTop: '24px', marginBottom: '8px', fontWeight: 500 } }, __('Box border', 'pmt-postgrid-block-editor-elementor-support')),
						el(RangeControl, {
							label: __('Border width', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.borderWidth,
							onChange: function (val) {
								setAttributes({ borderWidth: val });
							},
							min: 0,
							max: 20,
							help: __('Default: 0px', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(SelectControl, {
							label: __('Border style', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.borderStyle,
							options: [
								{ label: __('Solid', 'pmt-postgrid-block-editor-elementor-support'), value: 'solid' },
								{ label: __('Dashed', 'pmt-postgrid-block-editor-elementor-support'), value: 'dashed' },
								{ label: __('Dotted', 'pmt-postgrid-block-editor-elementor-support'), value: 'dotted' },
								{ label: __('Double', 'pmt-postgrid-block-editor-elementor-support'), value: 'double' },
								{ label: __('None', 'pmt-postgrid-block-editor-elementor-support'), value: 'none' },
							],
							help: __('Default: Solid', 'pmt-postgrid-block-editor-elementor-support'),
							onChange: function (val) {
								setAttributes({ borderStyle: val });
							},
						}),
						el(
							'div',
							{ style: { marginBottom: '8px' } },
							el('p', { style: { marginBottom: '8px', fontWeight: 500 } }, __('Border color', 'pmt-postgrid-block-editor-elementor-support') + ' ' + __('(Default: grey)', 'pmt-postgrid-block-editor-elementor-support')),
							el(Dropdown, {
								className: 'pmtpofob-color-dropdown',
								contentClassName: 'pmtpofob-color-dropdown__content',
								renderToggle: function (toggleProps) {
									return el(
										Button,
										{
											onClick: toggleProps.onToggle,
											'aria-expanded': toggleProps.isOpen,
											variant: 'secondary',
											style: { display: 'flex', alignItems: 'center', gap: '8px' },
										},
										el(ColorIndicator, { colorValue: attributes.borderColor }),
										attributes.borderColor || __('Pick a color', 'pmt-postgrid-block-editor-elementor-support')
									);
								},
								renderContent: function () {
									return el(
										'div',
										{ style: { padding: '8px' } },
										el(ColorPicker, {
											color: attributes.borderColor,
											onChange: function (val) {
												setAttributes({ borderColor: val });
											},
											enableAlpha: false,
										})
									);
								},
							})
						),
						el(RangeControl, {
							label: __('Upper corner radius', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.borderRadiusTop,
							onChange: function (val) {
								setAttributes({ borderRadiusTop: val });
							},
							min: 0,
							max: 100,
							help: __('Rounds the top-left and top-right corners. Default: 10px', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						el(RangeControl, {
							label: __('Lower corner radius', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.borderRadiusBottom,
							onChange: function (val) {
								setAttributes({ borderRadiusBottom: val });
							},
							min: 0,
							max: 100,
							help: __('Rounds the bottom-left and bottom-right corners. Default: 10px', 'pmt-postgrid-block-editor-elementor-support'),
						})
					),
					el(
						PanelBody,
						{ title: __('Typography', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: false },
						el(RangeControl, {
							label: __('Title font size', 'pmt-postgrid-block-editor-elementor-support'),
							value: attributes.titleFontSize,
							onChange: function (val) {
								setAttributes({ titleFontSize: val });
							},
							min: 10,
							max: 60,
							help: __('Default: 20px', 'pmt-postgrid-block-editor-elementor-support'),
						}),
						(attributes.design || 'design_1') === 'design_2'
							? el(RangeControl, {
								label: __('Column B title size (% of Column A)', 'pmt-postgrid-block-editor-elementor-support'),
								value: attributes.titleFontSizeScaleB,
								onChange: function (val) {
									setAttributes({ titleFontSizeScaleB: val });
								},
								min: 10,
								max: 150,
								help: __('Default: 75% -- e.g. if Column A is 20px, Column B is 15px automatically.', 'pmt-postgrid-block-editor-elementor-support'),
							})
							: null,
						(attributes.design || 'design_1') === 'design_3'
							? el(RangeControl, {
								label: __('Related post title size (% of parent)', 'pmt-postgrid-block-editor-elementor-support'),
								value: attributes.relatedTitleScale,
								onChange: function (val) {
									setAttributes({ relatedTitleScale: val });
								},
								min: 10,
								max: 150,
								help: __('Default: 80% -- e.g. if the row\'s title is 20px, related post titles are 16px automatically.', 'pmt-postgrid-block-editor-elementor-support'),
							})
							: null,
						el(Button, {
							variant: 'secondary',
							isDestructive: true,
							style: { marginTop: '16px' },
							onClick: function () {
								if (window.confirm(__('Reset', 'pmt-postgrid-block-editor-elementor-support'))) {
									setAttributes(PMTPOFOB_DEFAULT_ATTRIBUTES);
								}
							},
						}, __('Reset to default', 'pmt-postgrid-block-editor-elementor-support')),
						el(
							'p',
							{ style: { fontSize: '12px', color: '#757575', marginTop: '6px' } },
							__('Resets every control on this plugin. This cannot be undone.', 'pmt-postgrid-block-editor-elementor-support')
						)
					),
					el(
						PanelBody,
						{ title: __('Document', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: false },
						el(
							'a',
							{
								href: 'https://www.postmagthemes.com/docs/documentation-for-post-grid-plugins/',
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							__('Document', 'pmt-postgrid-block-editor-elementor-support')
						)
					),
					el(
						PanelBody,
						{ title: __('Buy Premium', 'pmt-postgrid-block-editor-elementor-support'), initialOpen: false },
						el(
							'a',
							{
								href: 'https://www.postmagthemes.com/downloads/pmt-pro-postgrid-block-editor-with-elementor-support/',
								target: '_blank',
								rel: 'noopener noreferrer',
							},
							__('Buy Premium', 'pmt-postgrid-block-editor-elementor-support')
						)
					)
				),
				el(
					'div',
					blockProps,
					el(ServerSideRender, {
						block: 'pmt/post-grid',
						attributes: attributes,
					})
				)
			);
		},

		// Dynamic block: PHP's render_callback owns all markup. Nothing
		// to save except the attributes themselves.
		save: function () {
			return null;
		},
	});
})(window.wp);
