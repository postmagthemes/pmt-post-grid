/**
 * Elementor editor only -- never loaded on the front end. Listens for the
 * 'pmt:post_grid:reset_settings' event fired by the BUTTON control at the
 * bottom of the widget's Typography section (see register_controls() in
 * class-pmt-post-grid-elementor-widget.php) and resets every one of this
 * widget's own settings back to its registered PHP default in one go.
 *
 * Applies the reset via Elementor's documented Commands API
 * ($e.run('document/elements/settings', {...})) -- the same command real
 * user edits (typing in a field, dragging a slider) go through. This
 * matters: directly mutating container.settings (a raw Backbone Model)
 * only updates the model itself, which is enough for the panel's own
 * controls to visually update (they listen to the model directly), but
 * silently skips Elementor's live-preview refresh, undo/redo history, and
 * the "document modified" flag that enables the Publish/Update button --
 * so a reset done that way looks like it worked in the panel, but can't
 * actually be saved. Going through the Commands API instead triggers all
 * of that correctly, the same as any other settings change would.
 *
 * See https://developers.elementor.com/docs/js/hooks/ and
 * https://developers.elementor.com/docs/containers/
 *
 * This default map is hand-kept in sync with the 'default' values in
 * register_controls() -- if a control's default ever changes there, this
 * list needs updating too.
 */
( function ( $ ) {
	'use strict';

	function getElementorDefaults() {
		return {
			category: '',
			author: '',
			postIds: [],
			excludePostIds: [],
			orderBy: 'date',
			showMainTitle: '',
			mainTitleText: '',
			mainTitleTag: 'h2',
			showImage: 'yes',
			showExcerpt: 'yes',
			excerptLength: 20,
			showTags: '',
			showCategoryBadge: 'yes',
			showReadMore: 'yes',
			readMoreText: 'Read More',
			showStructuredData: 'yes',
			showAuthor: 'yes',
			showDate: 'yes',
			showComments: 'yes',
			showViews: 'yes',
			showReadingTime: 'yes',
			design: 'design_1',
			showOrderByDropdown: 'yes',
			showCategoryDropdown: 'yes',
			columns: '2',
			postsToShow: 4,
			design3PostsToShow: 3,
			design3AlternateImage: '',
			titleTag: 'h3',
			contentAlign: 'left',
			layoutOrder: [
				{ element_type: 'image' },
				{ element_type: 'badge' },
				{ element_type: 'title' },
				{ element_type: 'excerpt' },
				{ element_type: 'tags' }
			],
			layoutOrderDesign3: [
				{ element_type: 'badge' },
				{ element_type: 'title' },
				{ element_type: 'excerpt' },
				{ element_type: 'tags' }
			],
			relatedPostsSectionTitle: 'Related Posts',
			showRelatedImage: 'yes',
			showRelatedTitle: 'yes',
			columnMargin: 15,
			rowMargin: 15,
			imageBorderRadius: 10,
			boxShadowHOffset: 0,
			boxShadowVOffset: 2,
			boxShadowBlur: 12,
			boxShadowSpread: 0,
			boxShadowOpacity: { unit: 'px', size: 0.09 },
			boxShadowColor: '#000000',
			boxShadowInset: '',
			borderWidth: 0,
			borderStyle: 'solid',
			borderColor: 'grey',
			borderRadiusTop: 10,
			borderRadiusBottom: 10,
			titleFontSize: 20,
			titleFontSizeScaleB: 75,
			relatedTitleScale: 80
		};
	}

	function getEditedContainer() {
		var panelView = window.elementor && window.elementor.getPanelView ? window.elementor.getPanelView() : null;
		if ( ! panelView ) {
			console.warn( '[pmt-post-grid reset] window.elementor.getPanelView() returned nothing.' );
			return null;
		}

		var pageView = panelView.getCurrentPageView ? panelView.getCurrentPageView() : null;
		if ( ! pageView ) {
			console.warn( '[pmt-post-grid reset] panelView.getCurrentPageView() returned nothing.' );
			return null;
		}

		var editedView = pageView.getOption ? pageView.getOption( 'editedElementView' ) : null;
		if ( ! editedView ) {
			console.warn( '[pmt-post-grid reset] pageView.getOption( "editedElementView" ) returned nothing. pageView was:', pageView );
			return null;
		}

		if ( typeof editedView.getContainer !== 'function' ) {
			console.warn( '[pmt-post-grid reset] editedElementView has no getContainer() method. editedView was:', editedView );
			return null;
		}

		return editedView.getContainer();
	}

	function onResetSettings() {
		console.log( '[pmt-post-grid reset] Reset button event received.' );

		var container = getEditedContainer();

		if ( ! container ) {
			console.warn( '[pmt-post-grid reset] Aborting: no container found (see warning above for which step failed).' );
			return;
		}

		var widgetType = container.model ? container.model.get( 'widgetType' ) : undefined;
		console.log( '[pmt-post-grid reset] Found container for widgetType:', widgetType );

		if ( 'pmt-post-grid' !== widgetType ) {
			console.warn( '[pmt-post-grid reset] Aborting: widgetType was "' + widgetType + '", expected "pmt-post-grid". The found container is probably the wrong element -- wrong panel page, or a parent/child container instead of this widget\'s own.' );
			return;
		}

		var confirmText = ( window.pmtPostGridElementorL10n && window.pmtPostGridElementorL10n.confirmText )
			? window.pmtPostGridElementorL10n.confirmText
			: 'Reset all settings to default? This cannot be undone.';

		if ( ! window.confirm( confirmText ) ) {
			console.log( '[pmt-post-grid reset] User cancelled the confirm dialog.' );
			return;
		}

		try {
			var defaults = getElementorDefaults();

			if ( window.$e && typeof window.$e.run === 'function' ) {
				// The documented, correct way to change an element's
				// settings from outside its own control views -- the same
				// command real user edits go through. Unlike directly
				// mutating container.settings (which only updates the
				// raw Backbone model -- fine for the panel's own controls,
				// since they listen to the model directly, but silently
				// skips the live-preview refresh, undo/redo history, and
				// the "document modified" flag that enables Publish),
				// this command triggers all of that correctly.
				window.$e.run( 'document/elements/settings', {
					container: container,
					settings: defaults,
				} );
				console.log( '[pmt-post-grid reset] Applied via $e.run( "document/elements/settings" ).' );
			} else {
				// Fallback for a very old Elementor without the Commands
				// API -- still updates the underlying data correctly, but
				// without the live-preview/history/save-button side
				// effects the command above provides.
				console.warn( '[pmt-post-grid reset] window.$e.run is not available -- falling back to a direct settings.set(). Live preview and the Publish button may not update.' );
				container.settings.set( defaults );
			}

			console.log( '[pmt-post-grid reset] New values:', container.settings.toJSON() );

			var panelView = window.elementor.getPanelView();
			if ( panelView && panelView.getCurrentPageView ) {
				panelView.getCurrentPageView().render();
				console.log( '[pmt-post-grid reset] Panel page re-rendered.' );
			}

			console.log( '[pmt-post-grid reset] Done.' );
		} catch ( err ) {
			console.error( '[pmt-post-grid reset] Threw an error while resetting:', err );
		}
	}

	function bindResetListener() {
		if ( window.elementor && window.elementor.channels && window.elementor.channels.editor ) {
			window.elementor.channels.editor.on( 'pmt:post_grid:reset_settings', onResetSettings );
			console.log( '[pmt-post-grid reset] Listener bound.' );
			return true;
		}
		return false;
	}

	// elementor:init may have already fired by the time this script
	// loads -- jQuery's .on() only catches *future* firings of an event,
	// so relying solely on $(window).on('elementor:init', ...) risks
	// permanently missing it if Elementor initialized first (a real,
	// order-dependent race condition, not a hypothetical one). Try
	// binding immediately; only fall back to waiting for the event if
	// Elementor genuinely isn't ready yet.
	if ( ! bindResetListener() ) {
		$( window ).on( 'elementor:init', function () {
			bindResetListener();
		} );
	}
} )( jQuery );
