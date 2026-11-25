/**
 * Moves admin notices into the admin bar Notifications panel.
 *
 * Notices are moved, never copied or deleted, so their own buttons and scripts
 * keep working. Hidden notices (such as the editor's "Connection lost" warning)
 * and notices marked "inline" stay where their screen put them, and so does
 * anything else a plugin printed with its notices. Notices that appear after the
 * page has loaded, such as the result of an action, also stay in place.
 */
( function ( $ ) {
	'use strict';

	var NOTICE = '.notice, .update-nag, .updated, .error, .e-notice';
	var CONTENT_SELECTORS = [ '#wpbody-content > ', '#wpbody-content > .wrap > ' ]
		.map( function ( parent ) {
			return NOTICE.split( ', ' )
				.map( function ( notice ) {
					return parent + notice;
				} )
				.join( ', ' );
		} )
		.join( ', ' );

	var panel = null;
	var emptyLabel = 'No notifications';

	function all( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function isHidden( el ) {
		return el.hidden || el.classList.contains( 'hidden' ) || 'none' === el.style.display;
	}

	// A notice worth moving: visible by itself, not meant to stay in place, with something to read.
	function isMovable( el ) {
		if ( ! el.matches( NOTICE ) || isHidden( el ) || el.classList.contains( 'inline' ) ) {
			return false;
		}
		if ( el.closest( '#wpadminbar, #screen-meta, #screen-meta-links, .wp-editor-container, .block-editor, .interface-interface-skeleton' ) ) {
			return false;
		}

		var text = ( el.textContent || '' ).replace( /\s+/g, ' ' ).trim();

		return text.length > 1 && ! /^switch to the (list|grid) view$/i.test( text );
	}

	function outermostNotice( el, root ) {
		var parent = el.parentElement;
		while ( parent && parent !== root ) {
			if ( parent.matches( NOTICE ) ) {
				return false;
			}
			parent = parent.parentElement;
		}
		return true;
	}

	function visibleCards() {
		return all( ':scope > .tapio-clean-notice-card', panel ).filter( function ( card ) {
			return ! isHidden( card );
		} );
	}

	// Badge and empty text follow what is in the panel, also after a notice dismisses itself.
	function refresh() {
		var count = visibleCards().length;
		var empty = panel.querySelector( ':scope > .tapio-clean-notices-empty' );
		var badge = document.querySelector( '#wp-admin-bar-tapio-clean-notifications .tapio-clean-notice-badge' );

		if ( ! count && ! empty ) {
			empty = document.createElement( 'p' );
			empty.className = 'tapio-clean-notices-empty';
			empty.textContent = emptyLabel;
			panel.appendChild( empty );
		} else if ( count && empty ) {
			empty.remove();
		}

		if ( badge ) {
			badge.hidden = ! count;
			badge.textContent = count ? String( count ) : '';
		}
	}

	function move( el ) {
		// "inline" stops wp-admin/js/common.js from moving the notice back under the heading.
		el.classList.add( 'inline', 'tapio-clean-notice-card' );
		panel.appendChild( el );
	}

	// Takes the captured notices and puts everything else back where WordPress would have printed it.
	function unpackSource() {
		var source = document.getElementById( 'tapio-clean-notices-source' );
		if ( ! source ) {
			return;
		}

		emptyLabel = source.getAttribute( 'data-empty' ) || emptyLabel;

		all( NOTICE, source )
			.filter( function ( el ) {
				return outermostNotice( el, source ) && isMovable( el );
			} )
			.forEach( move );

		restore( source );
	}

	function restore( source ) {
		var content = document.getElementById( 'wpbody-content' );

		if ( content ) {
			var before = content.querySelector( ':scope > .wrap' );
			while ( source.firstChild ) {
				content.insertBefore( source.firstChild, before );
			}
		}

		source.remove();
	}

	function moveFromContent() {
		all( CONTENT_SELECTORS ).filter( isMovable ).forEach( move );
	}

	// From here on, notices show where they are printed.
	function stopHiding() {
		document.body.classList.remove( 'tapio-clean-hide-notices' );
	}

	// Whether the user can actually open the panel: the toolbar and its item must be shown.
	function panelReachable() {
		var item = document.getElementById( 'wp-admin-bar-tapio-clean-notifications' );
		var bar = document.getElementById( 'wpadminbar' );

		return !! ( panel && item && bar &&
			'none' !== window.getComputedStyle( item ).display &&
			'none' !== window.getComputedStyle( bar ).display );
	}

	function init() {
		panel = document.getElementById( 'tapio-clean-notices-panel' );

		if ( ! panelReachable() ) {
			// No panel to move to: show the captured notices on the page.
			var source = document.getElementById( 'tapio-clean-notices-source' );
			if ( source ) {
				restore( source );
			}
			stopHiding();
			return;
		}

		try {
			unpackSource();
			moveFromContent();
			refresh();
			new window.MutationObserver( refresh ).observe( panel, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: [ 'class', 'style', 'hidden' ]
			} );
		} finally {
			stopHiding();
		}

		// Notices that scripts add while the page is still loading.
		$( window ).on( 'load', function () {
			window.setTimeout( function () {
				moveFromContent();
				refresh();
			}, 300 );
		} );
	}

	// "common" is a dependency, so its own ready handler (notice buttons and placement) runs first.
	$( init );
}( window.jQuery ) );
