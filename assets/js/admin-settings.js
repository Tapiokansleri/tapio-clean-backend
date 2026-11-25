/**
 * Tools > Tapio Clean Backend: drag and keyboard sorting for the plugin menu list.
 *
 * The hidden menu_order inputs travel with their rows, so the form posts the
 * order the rows are in when Save is pressed.
 */
( function ( $ ) {
	'use strict';

	var config = window.tapioCleanBackendSettings || {};
	var $list = $( '#tapio-clean-backend-menus' );
	var status = document.getElementById( 'tapio-clean-backend-order-status' );

	if ( ! $list.length ) {
		return;
	}

	function say( text ) {
		if ( status ) {
			status.textContent = text;
		}
	}

	function announce( $row ) {
		var $rows = $list.children( 'li' );
		var text = String( config.moved || '%1$s: %2$d / %3$d' )
			.replace( '%1$s', $row.attr( 'data-title' ) || '' )
			.replace( '%2$d', String( $rows.index( $row ) + 1 ) )
			.replace( '%3$d', String( $rows.length ) );

		say( text );
	}

	if ( $.fn.sortable ) {
		$list.sortable( {
			handle: '.tapio-clean-backend-handle',
			// The handle is a button, which jQuery UI would otherwise refuse to drag from.
			cancel: 'input, textarea, select, option',
			axis: 'y',
			containment: 'parent',
			tolerance: 'pointer',
			placeholder: 'tapio-clean-backend-placeholder',
			forcePlaceholderSize: true,
			update: function ( event, ui ) {
				announce( ui.item );
			}
		} );
	}

	$list.on( 'keydown', '.tapio-clean-backend-handle', function ( event ) {
		var $row = $( this ).closest( 'li' );

		if ( 'ArrowUp' === event.key && $row.prev().length ) {
			$row.insertBefore( $row.prev() );
		} else if ( 'ArrowDown' === event.key && $row.next().length ) {
			$row.insertAfter( $row.next() );
		} else {
			return;
		}

		event.preventDefault();
		this.focus();
		announce( $row );
	} );

	$( '#tapio-clean-backend-reset-order' ).on( 'click', function () {
		var rows = $list.children( 'li' ).get();

		rows.sort( function ( a, b ) {
			return Number( a.getAttribute( 'data-default-index' ) ) - Number( b.getAttribute( 'data-default-index' ) );
		} );

		$.each( rows, function ( i, row ) {
			$list.append( row );
		} );

		say( config.reset || '' );
	} );
}( jQuery ) );
