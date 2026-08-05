/**
 * Live filter dropdowns -- listens for changes on either
 * .pmt-category-filter or .pmt-orderby-filter, sends BOTH dropdowns'
 * current values (not just whichever one changed) plus this grid's
 * instance_id to the REST endpoint, and replaces just that grid's
 * .pmt-grid-content with the response. No page reload.
 *
 * Sending both values together on every request matters: if a visitor
 * has already filtered to a category and then changes the sort order,
 * that request must still include the active category -- otherwise
 * changing one dropdown would silently reset the other.
 *
 * Multiple grids on the same page work independently: each dropdown is
 * tied to its own instance_id via a matching data-pmt-instance
 * attribute, shared by both dropdowns and the .pmt-grid-content
 * container within the same wrapper.
 *
 * While the request is in flight, the existing content is swapped for a
 * skeleton placeholder shaped to match whichever design is currently
 * showing (detected from the DOM itself, so it adapts automatically --
 * no need to know the design ahead of time). The original content is
 * restored if the request fails, rather than left blank or stuck on a
 * skeleton forever.
 */
( function () {
	function findByInstance( wrapper, selector, instanceId ) {
		return wrapper.querySelector( selector + '[data-pmt-instance="' + instanceId + '"]' );
	}

	function skeletonCard() {
		return (
			'<div class="pmt-skeleton-card">' +
				'<div class="pmt-skeleton-block pmt-skeleton-image"></div>' +
				'<div class="pmt-skeleton-block pmt-skeleton-line pmt-skeleton-line--title"></div>' +
				'<div class="pmt-skeleton-block pmt-skeleton-line"></div>' +
				'<div class="pmt-skeleton-block pmt-skeleton-line pmt-skeleton-line--short"></div>' +
			'</div>'
		);
	}

	function skeletonRowCard() {
		return (
			'<div class="pmt-skeleton-card pmt-skeleton-card--row">' +
				'<div class="pmt-skeleton-block pmt-skeleton-image"></div>' +
				'<div class="pmt-skeleton-card-content">' +
					'<div class="pmt-skeleton-block pmt-skeleton-line pmt-skeleton-line--title"></div>' +
					'<div class="pmt-skeleton-block pmt-skeleton-line"></div>' +
					'<div class="pmt-skeleton-block pmt-skeleton-line pmt-skeleton-line--short"></div>' +
				'</div>' +
			'</div>'
		);
	}

	function skeletonSimpleCard() {
		return (
			'<div class="pmt-skeleton-card pmt-skeleton-card--simple">' +
				'<div class="pmt-skeleton-block pmt-skeleton-image"></div>' +
				'<div class="pmt-skeleton-block pmt-skeleton-line pmt-skeleton-line--title"></div>' +
			'</div>'
		);
	}

	/**
	 * Builds skeleton markup shaped to match whichever design is
	 * currently in gridContent, using its own DOM as the source of
	 * truth for column layout and item count -- no need to pass the
	 * design/settings down from PHP separately.
	 */
	function buildSkeleton( gridContent, wrapper ) {
		if ( wrapper.classList.contains( 'pmt-design2' ) ) {
			// Fixed shape regardless of post count: 1 featured + 2x2.
			return (
				'<div class="pmt-design2-grid pmt-skeleton-wrap">' +
					'<div class="pmt-design2-col-a">' + skeletonCard() + '</div>' +
					'<div class="pmt-design2-col-b">' +
						'<div class="pmt-design2-row">' +
							'<div class="pmt-design2-cell">' + skeletonSimpleCard() + '</div>' +
							'<div class="pmt-design2-cell">' + skeletonSimpleCard() + '</div>' +
						'</div>' +
						'<div class="pmt-design2-row">' +
							'<div class="pmt-design2-cell">' + skeletonSimpleCard() + '</div>' +
							'<div class="pmt-design2-cell">' + skeletonSimpleCard() + '</div>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
		}

		if ( wrapper.classList.contains( 'pmt-design3' ) ) {
			var items = gridContent.querySelectorAll( '.pmt-design3-item' );
			var rowCount = items.length || 3;
			var rowsHtml = '';
			for ( var r = 0; r < rowCount; r++ ) {
				rowsHtml += '<div class="pmt-design3-item">' + skeletonRowCard() + '</div>';
			}
			return '<div class="pmt-design3-list pmt-skeleton-wrap">' + rowsHtml + '</div>';
		}

		// Design 1: match the existing column class and item count so
		// the skeleton lines up with whatever grid was just showing.
		var existingCol = gridContent.querySelector( '.pmt-row > [class*="pmt-col-"]' );
		var colClass = existingCol ? existingCol.className : 'pmt-col-lg-6 pmt-col-md-6 pmt-col-sm-6';
		var colCount = gridContent.querySelectorAll( '.pmt-row > [class*="pmt-col-"]' ).length || 4;
		var colsHtml = '';
		for ( var c = 0; c < colCount; c++ ) {
			colsHtml += '<div class="' + colClass + '">' + skeletonCard() + '</div>';
		}
		return '<div class="pmt-row pmt-skeleton-wrap">' + colsHtml + '</div>';
	}

	function onFilterChange( event ) {
		var changed = event.target;
		var wrapper = changed.closest( '.pmt-post-grid-block' );
		if ( ! wrapper ) {
			return;
		}

		var instanceId = changed.getAttribute( 'data-pmt-instance' );
		if ( ! instanceId ) {
			return;
		}

		var gridContent = findByInstance( wrapper, '.pmt-grid-content', instanceId );
		if ( ! gridContent ) {
			return;
		}

		// Read BOTH dropdowns' current values, regardless of which one
		// actually changed -- so switching sort order doesn't reset the
		// active category, and vice versa. Either one might not exist
		// (e.g. no categories on the site, or Specific posts is active),
		// in which case its value just falls back to "not filtered".
		var categorySelect = findByInstance( wrapper, '.pmt-category-filter', instanceId );
		var orderbySelect  = findByInstance( wrapper, '.pmt-orderby-filter', instanceId );
		var category = categorySelect ? categorySelect.value : '';
		var orderBy  = orderbySelect ? orderbySelect.value : 'date';

		var previousHtml = gridContent.innerHTML;

		if ( categorySelect ) {
			categorySelect.disabled = true;
		}
		if ( orderbySelect ) {
			orderbySelect.disabled = true;
		}
		gridContent.innerHTML = buildSkeleton( gridContent, wrapper );
		gridContent.classList.add( 'pmt-loading' );

		function reenable() {
			if ( categorySelect ) {
				categorySelect.disabled = false;
			}
			if ( orderbySelect ) {
				orderbySelect.disabled = false;
			}
			gridContent.classList.remove( 'pmt-loading' );
		}

		var settings = window.pmtPostGridData || {};
		if ( ! settings.restUrl ) {
			gridContent.innerHTML = previousHtml;
			reenable();
			return;
		}

		fetch( settings.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': settings.nonce || '',
			},
			body: JSON.stringify( {
				instance_id: instanceId,
				category: category,
				order_by: orderBy,
			} ),
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					return { ok: response.ok, data: data };
				} );
			} )
			.then( function ( result ) {
				if ( result.ok && result.data && typeof result.data.html === 'string' ) {
					gridContent.innerHTML = result.data.html;
				} else {
					gridContent.innerHTML = previousHtml;
				}
			} )
			.catch( function () {
				// Restore the previous content on failure -- a broken
				// dropdown is worse than a stale grid, and leaving the
				// skeleton up forever would be worse still.
				gridContent.innerHTML = previousHtml;
			} )
			.finally( reenable );
	}

	document.addEventListener( 'change', function ( event ) {
		if (
			event.target &&
			event.target.classList &&
			( event.target.classList.contains( 'pmt-category-filter' ) || event.target.classList.contains( 'pmt-orderby-filter' ) )
		) {
			onFilterChange( event );
		}
	} );
} )();
