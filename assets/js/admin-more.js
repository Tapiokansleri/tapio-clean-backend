/**
 * The More link in the admin sidebar.
 *
 * Loaded in the page head. WordPress prints the sidebar already in its final
 * order, with the menus under More hidden by CSS, so this script only opens and
 * closes the section. setup() runs while the sidebar is being parsed.
 */
( function () {
	'use strict';

	var config = window.tapioCleanBackendMoreConfig || {};
	var itemClass = config.itemClass || 'tapio-more-item';
	var storageKey = config.storageKey || 'tapioCleanBackendMoreOpen';
	var OPEN_CLASS = 'tapio-more-open';

	function wasOpen() {
		try {
			return window.localStorage.getItem( storageKey ) === '1';
		} catch ( e ) {
			return false;
		}
	}

	function remember( open ) {
		try {
			window.localStorage.setItem( storageKey, open ? '1' : '0' );
		} catch ( e ) {
			// Storage can be blocked; the section then starts closed.
		}
	}

	function setup() {
		var menu = document.getElementById( 'adminmenu' );
		var item = document.getElementById( config.toggleId || 'menu-tapio-clean-backend-more' );
		var link = item ? item.querySelector( 'a' ) : null;

		if ( ! menu || ! link ) {
			return;
		}

		var moreItems = Array.prototype.filter.call( menu.children, function ( li ) {
			return li.classList.contains( itemClass );
		} );

		function apply( open ) {
			menu.classList.toggle( OPEN_CLASS, open );
			link.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		function toggle() {
			var open = ! menu.classList.contains( OPEN_CLASS );
			menu.classList.add( 'tapio-more-animate' );
			apply( open );
			remember( open );
			// Lets WordPress recalculate whether the sidebar can stay pinned.
			if ( window.jQuery ) {
				window.jQuery( document ).trigger( 'wp-pin-menu' );
			}
		}

		link.setAttribute( 'role', 'button' );
		link.setAttribute(
			'aria-controls',
			moreItems
				.map( function ( li, index ) {
					if ( ! li.id ) {
						li.id = 'tapio-more-item-' + index;
					}
					return li.id;
				} )
				.join( ' ' )
		);

		// A screen inside More opens the section without changing the remembered state.
		apply(
			wasOpen() ||
				moreItems.some( function ( li ) {
					return ! li.classList.contains( 'tapio-hidden-item' ) &&
						( li.classList.contains( 'current' ) || li.classList.contains( 'wp-has-current-submenu' ) );
				} )
		);

		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			toggle();
		} );

		// Links react to Enter by themselves; a button also reacts to Space.
		link.addEventListener( 'keydown', function ( event ) {
			if ( ' ' === event.key ) {
				event.preventDefault();
				toggle();
			}
		} );
	}

	window.tapioCleanBackendMore = { setup: setup };
}() );
