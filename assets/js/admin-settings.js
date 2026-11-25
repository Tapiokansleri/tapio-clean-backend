/**
 * Tools > Tapio Clean Backend: the sidebar editor.
 *
 * Menus are ordered by dragging them within and between the two lists, and
 * renamed by clicking their names. The layout is written to a hidden field only
 * after a change, so saving the switches alone keeps the saved layout.
 */
( function ( $ ) {
	'use strict';

	var config = window.tapioCleanBackendSettings || {};
	var form = document.getElementById( 'tapio-clean-backend-form' );
	var editor = document.getElementById( 'tapio-clean-backend-editor' );

	if ( ! form || ! editor || ! $.fn.sortable ) {
		return;
	}

	var $editor = $( editor );
	var $lists = $editor.find( '.tcb-list' );
	var jsonField = document.getElementById( 'tapio-clean-backend-menu-json' );
	var resetField = document.getElementById( 'tapio-clean-backend-reset-menu' );
	var resetButton = document.getElementById( 'tapio-clean-backend-reset' );
	var status = document.getElementById( 'tapio-clean-backend-status' );
	var maxLength = Number( config.maxNameLength ) || 60;
	var dirty = false;
	var submitting = false;

	function say( text ) {
		if ( status ) {
			status.textContent = text;
		}
	}

	function format( template, values ) {
		return String( template || '' ).replace( /%(\d)\$[sd]|%s/g, function ( match, index ) {
			return String( values[ index ? Number( index ) - 1 : 0 ] );
		} );
	}

	function moreButton() {
		return editor.querySelector( '.tcb-more-name' );
	}

	function collect() {
		var layout = { main: [], more: [], names: {}, more_label: moreButton().getAttribute( 'data-name' ) || '' };

		$lists.each( function () {
			var section = this.getAttribute( 'data-section' );

			$( this ).children( '.tcb-item' ).each( function () {
				var slug = this.getAttribute( 'data-slug' );
				var button = this.querySelector( '.tcb-name' );
				var name = button ? button.getAttribute( 'data-name' ) : '';

				layout[ section ].push( slug );
				if ( name ) {
					layout.names[ slug ] = name;
				}
			} );
		} );

		return layout;
	}

	function orderSignature() {
		var layout = collect();
		return JSON.stringify( [ layout.main, layout.more ] );
	}

	var initialOrder = orderSignature();

	// "moved" is false after renames only, so they leave the saved (or default) order as it is.
	function changed() {
		var layout = collect();

		dirty = true;
		layout.moved = JSON.stringify( [ layout.main, layout.more ] ) !== initialOrder;
		jsonField.value = JSON.stringify( layout );
	}

	function rowName( row ) {
		var name = row.querySelector( '.tcb-name, .tcb-separator-label' );
		return name ? name.textContent : '';
	}

	function announceMove( row ) {
		var list = row.parentNode;
		var rows = Array.prototype.slice.call( list.querySelectorAll( ':scope > .tcb-item' ) );
		var listName = 'more' === list.getAttribute( 'data-section' ) ? moreButton().textContent : config.sidebar;

		say( format( config.moved, [ rowName( row ), listName, rows.indexOf( row ) + 1, rows.length ] ) );
	}

	// On touch screens only the handle drags, so swiping a row scrolls the page and tapping a name renames it.
	var touch = !! ( window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches );

	$lists.sortable( {
		handle: touch ? '.tcb-handle' : false,
		items: '> .tcb-item',
		connectWith: $lists,
		placeholder: 'tcb-placeholder',
		forcePlaceholderSize: true,
		tolerance: 'pointer',
		// A press without movement stays a click, so names can be clicked to rename them.
		distance: 5,
		cancel: 'input',
		receive: function ( event, ui ) {
			if ( 'separator' === ui.item.attr( 'data-type' ) && 'more' === this.getAttribute( 'data-section' ) ) {
				ui.sender.sortable( 'cancel' );
			}
		},
		stop: function ( event, ui ) {
			changed();
			announceMove( ui.item[ 0 ] );
		}
	} );

	function showName( button ) {
		var name = button.getAttribute( 'data-name' );
		var original = button.getAttribute( 'data-original' );
		var hint = button.parentNode.querySelector( ':scope > .tcb-original' );

		button.textContent = name || original;
		if ( hint ) {
			hint.hidden = ! name;
		}

		var handle = button.parentNode.querySelector( ':scope > .tcb-handle' );
		if ( handle ) {
			handle.setAttribute( 'aria-label', format( config.moveLabel, [ button.textContent ] ) );
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
		input.maxLength = maxLength;
		input.value = button.getAttribute( 'data-name' ) || original;
		input.placeholder = original;
		input.setAttribute( 'aria-label', config.nameField + ': ' + original );

		function finish( keep, refocus ) {
			if ( finished ) {
				return;
			}
			finished = true;

			if ( keep ) {
				var value = input.value.replace( /\s+/g, ' ' ).trim();
				var name = value === original ? '' : value;

				if ( name !== button.getAttribute( 'data-name' ) ) {
					button.setAttribute( 'data-name', name );
					showName( button );
					changed();
					say( format( config.renamed, [ button.textContent ] ) );
				}
			}

			input.remove();
			button.hidden = false;
			if ( refocus ) {
				button.focus();
			}
		}

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key || 'Escape' === event.key ) {
				// Enter must not submit the settings form.
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

	$editor.on( 'click', '.tcb-name', function ( event ) {
		event.preventDefault();
		startRename( this );
	} );

	// Keyboard moves: up and down within a list, right and left between the lists.
	$editor.on( 'keydown', '.tcb-handle', function ( event ) {
		var row = this.parentNode;
		var list = row.parentNode;
		var section = list.getAttribute( 'data-section' );
		var previous = row.previousElementSibling;
		var next = row.nextElementSibling;

		if ( 'ArrowUp' === event.key && previous ) {
			list.insertBefore( row, previous );
		} else if ( 'ArrowDown' === event.key && next ) {
			list.insertBefore( next, row );
		} else if ( 'ArrowRight' === event.key && 'main' === section && 'separator' !== row.getAttribute( 'data-type' ) ) {
			$lists.filter( '[data-section="more"]' ).append( row );
		} else if ( 'ArrowLeft' === event.key && 'more' === section ) {
			$lists.filter( '[data-section="main"]' ).append( row );
		} else {
			return;
		}

		event.preventDefault();
		this.focus();
		changed();
		announceMove( row );
	} );

	// Restoring the default needs a second click, so one stray click loses nothing.
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
			resetField.value = '1';
			submitting = true;
			// The form has a field named "submit", which hides form.submit().
			window.HTMLFormElement.prototype.submit.call( form );
		} );
	}

	form.addEventListener( 'submit', function () {
		submitting = true;
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( dirty && ! submitting ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );
}( window.jQuery ) );
