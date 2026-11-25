/**
 * Tools > Tapio Clean Backend: the sidebar editor.
 *
 * The editor looks like the sidebar. Items are ordered by dragging, renamed by
 * clicking their names and hidden with the eye; separators can be added and
 * removed. Every change, the feature switches included, is saved at once, and
 * the real sidebar next to the editor is updated to match.
 */
( function ( $ ) {
	'use strict';

	var config = window.tapioCleanBackendSettings || {};
	var form = document.getElementById( 'tapio-clean-backend-form' );

	if ( ! form || ! window.fetch ) {
		return;
	}

	var editor = document.getElementById( 'tapio-clean-backend-editor' );
	var switches = Array.prototype.slice.call( form.querySelectorAll( '.tcb-switch' ) );
	var toast = document.getElementById( 'tapio-clean-backend-save-status' );
	var live = document.getElementById( 'tapio-clean-backend-status' );
	var sidebar = document.getElementById( 'adminmenu' );

	function all( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function say( text ) {
		if ( live ) {
			live.textContent = text;
		}
	}

	function format( template, values ) {
		return String( template || '' ).replace( /%(\d)\$[sd]|%s/g, function ( match, index ) {
			return String( values[ index ? Number( index ) - 1 : 0 ] );
		} );
	}

	/* The editor's content. */

	function list( section ) {
		return editor.querySelector( '.tcb-list[data-section="' + section + '"]' );
	}

	function rows( section ) {
		return all( ':scope > .tcb-item', list( section ) );
	}

	function moreButton() {
		return editor.querySelector( '.tcb-more-name' );
	}

	function rowName( row ) {
		var button = row.querySelector( '.tcb-name' );
		return button ? button.textContent : config.separator;
	}

	function collect() {
		var layout = { main: [], more: [], hidden: [], names: {}, more_label: moreButton().getAttribute( 'data-name' ) || '' };

		[ 'main', 'more' ].forEach( function ( section ) {
			rows( section ).forEach( function ( row ) {
				var slug = row.getAttribute( 'data-slug' );
				var button = row.querySelector( '.tcb-name' );
				var name = button ? button.getAttribute( 'data-name' ) : '';

				layout[ section ].push( slug );
				if ( row.classList.contains( 'tcb-is-hidden' ) ) {
					layout.hidden.push( slug );
				}
				if ( name ) {
					layout.names[ slug ] = name;
				}
			} );
		} );

		return layout;
	}

	function orderOf( layout ) {
		return JSON.stringify( [ layout.main, layout.more ] );
	}

	/* Saving. One request at a time; changes made meanwhile go in the next one. */

	var savedOrder = editor ? orderOf( collect() ) : '';
	var pending = null;
	var inFlight = false;
	var failed = false;
	var toastTimer = null;

	function showToast( text, state ) {
		window.clearTimeout( toastTimer );
		toast.hidden = false;
		toast.className = 'tcb-toast' + ( 'error' === state ? ' is-error' : '' );
		toast.textContent = text;

		if ( 'error' === state ) {
			var retry = document.createElement( 'button' );
			retry.type = 'button';
			retry.className = 'button-link';
			retry.textContent = config.retry;
			retry.addEventListener( 'click', flush );
			toast.appendChild( retry );
		} else if ( 'saved' === state ) {
			toastTimer = window.setTimeout( function () {
				toast.hidden = true;
			}, 2500 );
		}
	}

	function combine( first, second ) {
		return {
			reset: !! ( first && first.reset ) || !! ( second && second.reset ),
			reload: !! ( first && first.reload ) || !! ( second && second.reload )
		};
	}

	function save( job ) {
		pending = combine( pending, job );
		flush();
	}

	function flush() {
		if ( inFlight || ! pending ) {
			return;
		}

		var job = pending;
		var body = new window.URLSearchParams();
		var layout = null;
		var order = null;

		pending = null;
		inFlight = true;
		failed = false;

		body.set( 'action', config.action );
		body.set( 'nonce', config.nonce );
		switches.forEach( function ( input ) {
			body.set( input.getAttribute( 'data-setting' ), input.checked ? '1' : '' );
		} );

		if ( job.reset ) {
			body.set( 'reset_menu', '1' );
		} else if ( editor ) {
			layout = collect();
			order = orderOf( layout );
			// "moved" is false after renames and hiding only, so they leave the saved (or default) order as it is.
			layout.moved = order !== savedOrder;
			body.set( 'menu_json', JSON.stringify( layout ) );
		}

		showToast( config.saving, 'saving' );

		// keepalive lets the request finish if the user leaves the page right after a change.
		window.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', keepalive: true, body: body } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( 'save failed' );
				}
				if ( null !== order ) {
					savedOrder = order;
				}
				showToast( config.saved, 'saved' );
				if ( job.reload ) {
					window.location.reload();
				} else if ( editor ) {
					updateSidebar( result.data );
				}
			} )
			.catch( function () {
				failed = true;
				pending = combine( job, pending );
				showToast( config.failed, 'error' );
			} )
			.then( function () {
				inFlight = false;
				if ( ! failed ) {
					flush();
				}
			} );
	}

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( failed || pending ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();
	} );

	// The switches change how pages are built, so the page reloads once saved.
	switches.forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			save( { reload: true } );
		} );
	} );

	if ( ! editor || ! $.fn.sortable ) {
		return;
	}

	/* The real sidebar next to the editor. */

	function sidebarItem( key ) {
		return sidebar ? sidebar.querySelector( ':scope > li.tcb-key-' + key ) : null;
	}

	function setSidebarName( item, name ) {
		all( ':scope > a .wp-menu-name, :scope > .wp-submenu > .wp-submenu-head', item ).forEach( function ( el ) {
			// The name is the first text; count bubbles after it stay.
			var first = el.firstChild;
			var text = name + ( el.children.length ? ' ' : '' );

			if ( first && 3 === first.nodeType ) {
				first.nodeValue = text;
			} else {
				el.insertBefore( document.createTextNode( text ), first );
			}
		} );
	}

	function sidebarSeparator( row ) {
		var li = document.createElement( 'li' );
		var line = document.createElement( 'div' );

		li.className = 'wp-not-current-submenu wp-menu-separator tcb-key-' + row.getAttribute( 'data-key' ) +
			( row.querySelector( '.tcb-remove' ) ? ' tapio-custom-separator' : '' );
		li.setAttribute( 'aria-hidden', 'true' );
		line.className = 'separator';
		li.appendChild( line );

		return li;
	}

	function updateSidebar( data ) {
		if ( ! sidebar ) {
			return;
		}

		var toggle = document.getElementById( 'menu-tapio-clean-backend-more' );
		var collapse = document.getElementById( 'collapse-menu' );
		var mainRows = rows( 'main' );
		var moreRows = rows( 'more' );
		var kept = mainRows.concat( moreRows ).map( function ( row ) {
			return 'tcb-key-' + row.getAttribute( 'data-key' );
		} );

		all( ':scope > li.tapio-custom-separator', sidebar ).forEach( function ( li ) {
			var stays = kept.some( function ( name ) {
				return li.classList.contains( name );
			} );
			if ( ! stays ) {
				li.remove();
			}
		} );

		function place( row ) {
			var li = sidebarItem( row.getAttribute( 'data-key' ) );
			if ( ! li && 'separator' === row.getAttribute( 'data-type' ) ) {
				li = sidebarSeparator( row );
			}
			if ( li ) {
				sidebar.insertBefore( li, collapse );
			}
		}

		// Without More and without a saved order, WordPress keeps its own order.
		if ( toggle || ( data && data.saved ) ) {
			mainRows.forEach( place );
			if ( toggle ) {
				sidebar.insertBefore( toggle, collapse );
			}
			moreRows.forEach( place );
		}

		mainRows.concat( moreRows ).forEach( function ( row ) {
			var li = sidebarItem( row.getAttribute( 'data-key' ) );
			if ( ! li ) {
				return;
			}
			li.classList.toggle( 'tapio-more-item', !! toggle && 'more' === row.parentNode.getAttribute( 'data-section' ) );
			li.classList.toggle( 'tapio-hidden-item', row.classList.contains( 'tcb-is-hidden' ) );
			if ( row.querySelector( '.tcb-name' ) ) {
				setSidebarName( li, rowName( row ) );
			}
		} );

		if ( toggle ) {
			setSidebarName( toggle, moreButton().textContent );
			toggle.classList.toggle( 'tapio-more-empty', ! moreRows.some( function ( row ) {
				return 'menu' === row.getAttribute( 'data-type' ) && ! row.classList.contains( 'tcb-is-hidden' );
			} ) );

			if ( sidebar.querySelector( ':scope > li.tapio-more-item.wp-has-current-submenu:not(.tapio-hidden-item), :scope > li.tapio-more-item.current:not(.tapio-hidden-item)' ) ) {
				sidebar.classList.add( 'tapio-more-open' );
			}

			// Like the server, draw no separator right above the More link.
			all( ':scope > li.wp-menu-separator', sidebar ).forEach( function ( li ) {
				li.style.display = '';
			} );
			var above = toggle.previousElementSibling;
			while ( above && above.classList.contains( 'wp-menu-separator' ) ) {
				above.style.display = 'none';
				above = above.previousElementSibling;
			}
		}

		$( document ).trigger( 'wp-pin-menu' );
	}

	/* Make the editor look like the sidebar: its colors and the icons it shows. */

	function copyColors() {
		if ( ! sidebar ) {
			return;
		}

		var item = sidebar.querySelector( ':scope > li.menu-top:not(.current):not(.wp-has-current-submenu):not(.tapio-more-toggle-item) > a' );
		var sources = {
			'--tcb-bg': [ document.getElementById( 'adminmenuback' ) || sidebar, null, 'backgroundColor' ],
			'--tcb-text': [ item && item.querySelector( '.wp-menu-name' ), null, 'color' ],
			'--tcb-icon': [ item && item.querySelector( '.wp-menu-image' ), '::before', 'color' ],
			'--tcb-hover': [ sidebar.querySelector( '.wp-submenu' ), null, 'backgroundColor' ],
			'--tcb-bubble-bg': [ sidebar.querySelector( '.update-plugins, .awaiting-mod' ), null, 'backgroundColor' ]
		};

		Object.keys( sources ).forEach( function ( property ) {
			var source = sources[ property ];
			if ( source[ 0 ] ) {
				var value = window.getComputedStyle( source[ 0 ], source[ 1 ] )[ source[ 2 ] ];
				if ( value && 'rgba(0, 0, 0, 0)' !== value ) {
					editor.style.setProperty( property, value );
				}
			}
		} );
	}

	// Plugin icons can come from CSS rules that only match the sidebar, so the editor copies what the sidebar draws.
	function copyIcons() {
		all( '.tcb-item[data-type="menu"]', editor ).forEach( function ( row ) {
			var li = sidebarItem( row.getAttribute( 'data-key' ) );
			var real = li && li.querySelector( ':scope > a .wp-menu-image' );
			var icon = row.querySelector( ':scope > .tcb-icon' );

			if ( ! real || ! icon ) {
				return;
			}

			var style = window.getComputedStyle( real );
			var before = window.getComputedStyle( real, '::before' );
			var image = real.querySelector( 'img' );
			var copy = document.createElement( 'span' );

			copy.className = 'tcb-icon';
			copy.setAttribute( 'aria-hidden', 'true' );

			[ style.backgroundImage, before.backgroundImage ].forEach( function ( background ) {
				if ( background && 'none' !== background ) {
					copy.style.backgroundImage = background;
				}
			} );

			if ( image ) {
				var picture = document.createElement( 'img' );
				picture.src = image.src;
				picture.alt = '';
				copy.appendChild( picture );
			}

			var content = before.content;
			if ( content && ! /^(none|normal|""|'')$/.test( content ) ) {
				if ( 0 === content.indexOf( 'url(' ) ) {
					copy.style.backgroundImage = content;
				} else {
					var glyph = document.createElement( 'span' );
					glyph.textContent = content.slice( 1, -1 );
					glyph.style.fontFamily = before.fontFamily;
					copy.appendChild( glyph );
				}
			}

			if ( copy.childNodes.length || copy.style.backgroundImage ) {
				icon.replaceWith( copy );
			}
		} );
	}

	function matchSidebar() {
		copyColors();
		copyIcons();
	}

	matchSidebar();
	// WordPress paints SVG icons in the scheme colors after the page has loaded.
	$( window ).on( 'load', function () {
		window.setTimeout( matchSidebar, 400 );
	} );

	/* Dragging. */

	function announceMove( row ) {
		var section = row.parentNode.getAttribute( 'data-section' );
		var siblings = rows( section );
		var listName = 'more' === section ? moreButton().textContent : config.sidebar;

		say( format( config.moved, [ rowName( row ), listName, siblings.indexOf( row ) + 1, siblings.length ] ) );
	}

	// On touch screens only the grip drags, so swiping scrolls the page and tapping a name renames it.
	var touch = !! ( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches );
	var $lists = $( editor ).find( '.tcb-list' );

	$lists.sortable( {
		handle: touch ? '.tcb-grip' : false,
		items: '> .tcb-item',
		connectWith: $lists,
		placeholder: 'tcb-placeholder',
		tolerance: 'pointer',
		// A press without movement stays a click, so names can be clicked to rename them.
		distance: 5,
		cancel: 'input, .tcb-action',
		start: function ( event, ui ) {
			ui.placeholder.height( ui.item.outerHeight() );
		},
		stop: function ( event, ui ) {
			announceMove( ui.item[ 0 ] );
			save();
		}
	} );

	// Up and down arrows move the focused item, across the More link too.
	$( editor ).on( 'keydown', '.tcb-item', function ( event ) {
		if ( ( 'ArrowUp' !== event.key && 'ArrowDown' !== event.key ) || 'INPUT' === event.target.tagName ) {
			return;
		}

		var row = this;
		var current = row.parentNode;
		var section = current.getAttribute( 'data-section' );
		var previous = row.previousElementSibling;
		var next = row.nextElementSibling;

		event.preventDefault();

		if ( 'ArrowUp' === event.key && previous ) {
			current.insertBefore( row, previous );
		} else if ( 'ArrowUp' === event.key && 'more' === section ) {
			list( 'main' ).appendChild( row );
		} else if ( 'ArrowDown' === event.key && next ) {
			current.insertBefore( next, row );
		} else if ( 'ArrowDown' === event.key && 'main' === section ) {
			list( 'more' ).insertBefore( row, list( 'more' ).firstChild );
		} else {
			return;
		}

		event.target.focus();
		announceMove( row );
		save();
	} );

	/* Hiding. */

	$( editor ).on( 'click', '.tcb-eye', function () {
		var row = this.closest( '.tcb-item' );
		var hidden = ! row.classList.contains( 'tcb-is-hidden' );

		row.classList.toggle( 'tcb-is-hidden', hidden );
		this.classList.toggle( 'dashicons-hidden', hidden );
		this.classList.toggle( 'dashicons-visibility', ! hidden );
		this.setAttribute( 'aria-pressed', hidden ? 'true' : 'false' );
		this.title = format( hidden ? config.show : config.hide, [ rowName( row ) ] );

		say( format( hidden ? config.nowHidden : config.nowShown, [ rowName( row ) ] ) );
		save();
	} );

	/* Separators. */

	function newSeparator() {
		var random = new window.Uint8Array( 8 );
		var characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
		var id = '';

		window.crypto.getRandomValues( random );
		random.forEach( function ( byte ) {
			id += characters.charAt( byte % characters.length );
		} );

		var slug = 'separator-tapio-' + id;
		var row = document.createElement( 'li' );

		row.className = 'tcb-item tcb-separator';
		row.setAttribute( 'data-slug', slug );
		row.setAttribute( 'data-key', slug );
		row.setAttribute( 'data-type', 'separator' );
		row.setAttribute( 'tabindex', '0' );
		row.setAttribute( 'aria-label', config.separator );

		var line = document.createElement( 'span' );
		line.className = 'tcb-line';
		line.setAttribute( 'aria-hidden', 'true' );

		var remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'tcb-action tcb-remove dashicons dashicons-no-alt';
		remove.setAttribute( 'aria-label', config.removeSeparator );
		remove.title = config.removeSeparator;

		var grip = document.createElement( 'span' );
		grip.className = 'tcb-grip dashicons dashicons-menu';
		grip.setAttribute( 'aria-hidden', 'true' );

		row.appendChild( line );
		row.appendChild( remove );
		row.appendChild( grip );

		return row;
	}

	$( editor ).on( 'click', '.tcb-add-separator', function () {
		var row = newSeparator();

		list( 'main' ).appendChild( row );
		$lists.sortable( 'refresh' );
		row.classList.add( 'tcb-flash' );
		row.focus();
		say( config.separatorAdded );
		save();
	} );

	$( editor ).on( 'click', '.tcb-remove', function () {
		var row = this.closest( '.tcb-item' );
		var neighbour = row.nextElementSibling || row.previousElementSibling;

		row.remove();
		if ( neighbour ) {
			var target = neighbour.querySelector( '.tcb-name' ) || neighbour;
			target.focus();
		}
		save();
	} );

	/* Renaming. */

	function showName( button ) {
		var name = button.getAttribute( 'data-name' );

		button.textContent = name || button.getAttribute( 'data-original' );

		var row = button.closest( '.tcb-item' );
		var eye = row && row.querySelector( '.tcb-eye' );
		if ( eye ) {
			var hidden = row.classList.contains( 'tcb-is-hidden' );
			eye.setAttribute( 'aria-label', format( config.hide, [ button.textContent ] ) );
			eye.title = format( hidden ? config.show : config.hide, [ button.textContent ] );
		}
	}

	function startRename( button ) {
		if ( button.hidden ) {
			return;
		}

		var original = button.getAttribute( 'data-original' );
		var input = document.createElement( 'input' );
		var finished = false;

		input.type = 'text';
		input.className = 'tcb-input';
		input.maxLength = Number( config.maxNameLength ) || 60;
		input.value = button.getAttribute( 'data-name' ) || original;
		input.placeholder = original;
		input.setAttribute( 'aria-label', config.nameField + ': ' + original );

		function finish( keep, refocus ) {
			if ( finished ) {
				return;
			}
			finished = true;

			var value = input.value.replace( /\s+/g, ' ' ).trim();
			var name = value === original ? '' : value;
			var changedName = keep && name !== button.getAttribute( 'data-name' );

			input.remove();
			button.hidden = false;

			if ( changedName ) {
				button.setAttribute( 'data-name', name );
				showName( button );
				say( format( config.renamed, [ button.textContent ] ) );
				save();
			}
			if ( refocus ) {
				button.focus();
			}
		}

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || 'Escape' === event.key ) {
				event.preventDefault();
				finish( 'Enter' === event.key, true );
			}
		} );
		input.addEventListener( 'blur', function () {
			finish( true, false );
		} );

		button.hidden = true;
		button.parentNode.insertBefore( input, button );
		input.focus();
		input.select();
	}

	$( editor ).on( 'click', '.tcb-name', function ( event ) {
		event.preventDefault();
		startRename( this );
	} );

	/* Restoring the default needs a second click, so one stray click loses nothing. */

	var resetButton = document.getElementById( 'tapio-clean-backend-reset' );
	if ( resetButton ) {
		var resetLabel = resetButton.textContent;
		var resetTimer = null;

		resetButton.addEventListener( 'click', function () {
			if ( ! resetTimer ) {
				resetButton.textContent = config.confirmReset;
				resetTimer = window.setTimeout( function () {
					resetButton.textContent = resetLabel;
					resetTimer = null;
				}, 6000 );
				return;
			}

			window.clearTimeout( resetTimer );
			save( { reset: true, reload: true } );
		} );
	}
}( window.jQuery ) );
