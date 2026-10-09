/**
 * Live filter dropdowns -- listens for changes on either
 * .pmtpofob-category-filter or .pmtpofob-orderby-filter, sends BOTH dropdowns'
 * current values (not just whichever one changed) plus this grid's
 * instance_id to the REST endpoint, and replaces just that grid's
 * .pmtpofob-grid-content with the response. No page reload.
 *
 * Sending both values together on every request matters: if a visitor
 * has already filtered to a category and then changes the sort order,
 * that request must still include the active category -- otherwise
 * changing one dropdown would silently reset the other.
 *
 * Multiple grids on the same page work independently: each dropdown is
 * tied to its own instance_id via a matching data-pmtpofob-instance
 * attribute, shared by both dropdowns and the .pmtpofob-grid-content
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
		return wrapper.querySelector( selector + '[data-pmtpofob-instance="' + instanceId + '"]' );
	}

	function skeletonCard() {
		return (
			'<div class="pmtpofob-skeleton-card">' +
				'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-image"></div>' +
				'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-line pmtpofob-skeleton-line--title"></div>' +
				'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-line"></div>' +
				'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-line pmtpofob-skeleton-line--short"></div>' +
			'</div>'
		);
	}

	function skeletonRowCard() {
		return (
			'<div class="pmtpofob-skeleton-card pmtpofob-skeleton-card--row">' +
				'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-image"></div>' +
				'<div class="pmtpofob-skeleton-card-content">' +
					'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-line pmtpofob-skeleton-line--title"></div>' +
					'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-line"></div>' +
					'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-line pmtpofob-skeleton-line--short"></div>' +
				'</div>' +
			'</div>'
		);
	}

	function skeletonSimpleCard() {
		return (
			'<div class="pmtpofob-skeleton-card pmtpofob-skeleton-card--simple">' +
				'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-image"></div>' +
				'<div class="pmtpofob-skeleton-block pmtpofob-skeleton-line pmtpofob-skeleton-line--title"></div>' +
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
		if ( wrapper.classList.contains( 'pmtpofob-design2' ) ) {
			// Fixed shape regardless of post count: 1 featured + 2x2.
			return (
				'<div class="pmtpofob-design2-grid pmtpofob-skeleton-wrap">' +
					'<div class="pmtpofob-design2-col-a">' + skeletonCard() + '</div>' +
					'<div class="pmtpofob-design2-col-b">' +
						'<div class="pmtpofob-design2-row">' +
							'<div class="pmtpofob-design2-cell">' + skeletonSimpleCard() + '</div>' +
							'<div class="pmtpofob-design2-cell">' + skeletonSimpleCard() + '</div>' +
						'</div>' +
						'<div class="pmtpofob-design2-row">' +
							'<div class="pmtpofob-design2-cell">' + skeletonSimpleCard() + '</div>' +
							'<div class="pmtpofob-design2-cell">' + skeletonSimpleCard() + '</div>' +
						'</div>' +
					'</div>' +
				'</div>'
			);
		}

		if ( wrapper.classList.contains( 'pmtpofob-design3' ) ) {
			var items = gridContent.querySelectorAll( '.pmtpofob-design3-item' );
			var rowCount = items.length || 3;
			var rowsHtml = '';
			for ( var r = 0; r < rowCount; r++ ) {
				rowsHtml += '<div class="pmtpofob-design3-item">' + skeletonRowCard() + '</div>';
			}
			return '<div class="pmtpofob-design3-list pmtpofob-skeleton-wrap">' + rowsHtml + '</div>';
		}

		// Design 1: match the existing column class and item count so
		// the skeleton lines up with whatever grid was just showing.
		var existingCol = gridContent.querySelector( '.pmtpofob-row > [class*="pmtpofob-col-"]' );
		var colClass = existingCol ? existingCol.className : 'pmtpofob-col-lg-6 pmtpofob-col-md-6 pmtpofob-col-sm-6';
		var colCount = gridContent.querySelectorAll( '.pmtpofob-row > [class*="pmtpofob-col-"]' ).length || 4;
		var colsHtml = '';
		for ( var c = 0; c < colCount; c++ ) {
			colsHtml += '<div class="' + colClass + '">' + skeletonCard() + '</div>';
		}
		return '<div class="pmtpofob-row pmtpofob-skeleton-wrap">' + colsHtml + '</div>';
	}

	function onFilterChange( event ) {
		var changed = event.target;
		var wrapper = changed.closest( '.pmtpofob-post-grid-block' );
		if ( ! wrapper ) {
			return;
		}

		var instanceId = changed.getAttribute( 'data-pmtpofob-instance' );
		if ( ! instanceId ) {
			return;
		}

		var gridContent = findByInstance( wrapper, '.pmtpofob-grid-content', instanceId );
		if ( ! gridContent ) {
			return;
		}

		// Read BOTH dropdowns' current values, regardless of which one
		// actually changed -- so switching sort order doesn't reset the
		// active category, and vice versa. Either one might not exist
		// (e.g. no categories on the site, or Specific posts is active),
		// in which case its value just falls back to "not filtered".
		var categorySelect = findByInstance( wrapper, '.pmtpofob-category-filter', instanceId );
		var orderbySelect  = findByInstance( wrapper, '.pmtpofob-orderby-filter', instanceId );
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
		gridContent.classList.add( 'pmtpofob-loading' );

		function reenable() {
			if ( categorySelect ) {
				categorySelect.disabled = false;
			}
			if ( orderbySelect ) {
				orderbySelect.disabled = false;
			}
			gridContent.classList.remove( 'pmtpofob-loading' );
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
			( event.target.classList.contains( 'pmtpofob-category-filter' ) || event.target.classList.contains( 'pmtpofob-orderby-filter' ) )
		) {
			onFilterChange( event );
		}
	} );
} )();
