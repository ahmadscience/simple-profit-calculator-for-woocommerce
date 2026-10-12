/**
 * Simple Profit Calculator – admin behaviour (no dependencies).
 */
( function () {
	'use strict';

	var cfg = window.spcwAdmin || { decimal: '.', decimals: 2, unsaved: '' };

	/** Parse a typed amount the same way the server does (simplified). */
	function parseAmount( raw ) {
		raw = String( raw || '' ).replace( /[\s  ']/g, '' ).replace( /[^0-9,.\-]/g, '' );
		if ( raw === '' || raw === '-' ) {
			return null;
		}
		var neg = raw.charAt( 0 ) === '-';
		raw = raw.replace( /-/g, '' );
		var c = raw.lastIndexOf( ',' ), d = raw.lastIndexOf( '.' ), dec = null;
		if ( c > -1 && d > -1 ) {
			dec = c > d ? ',' : '.';
		} else if ( c > -1 || d > -1 ) {
			var sep = c > -1 ? ',' : '.';
			var parts = raw.split( sep );
			if ( parts.length > 2 || ( sep !== cfg.decimal && parts[ parts.length - 1 ].length === 3 ) ) {
				dec = null;
			} else {
				dec = sep;
			}
		}
		if ( dec === null ) {
			raw = raw.replace( /[,.]/g, '' );
		} else {
			raw = raw.split( dec === ',' ? '.' : ',' ).join( '' ).replace( dec, '.' );
		}
		var v = parseFloat( raw );
		if ( isNaN( v ) ) {
			return null;
		}
		return neg ? -v : v;
	}

	function formatAmount( v ) {
		var s = v.toFixed( cfg.decimals );
		return cfg.decimal === '.' ? s : s.replace( '.', cfg.decimal );
	}

	/* Auto-submit period selectors. */
	document.querySelectorAll( '.spcw-autosubmit' ).forEach( function ( el ) {
		el.addEventListener( 'change', function () {
			el.form.submit();
		} );
	} );

	/* Warn before leaving with unsaved changes. */
	document.querySelectorAll( 'form.spcw-track-changes' ).forEach( function ( form ) {
		var dirty = false;
		var hint = form.querySelector( '.spcw-dirty-hint' );
		function markDirty() {
			dirty = true;
			if ( hint ) {
				hint.hidden = false;
			}
		}
		form.addEventListener( 'input', markDirty );
		form.addEventListener( 'change', markDirty );
		form.addEventListener( 'spcw:changed', markDirty );
		form.addEventListener( 'submit', function () {
			dirty = false;
		} );
		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = cfg.unsaved;
				return cfg.unsaved;
			}
		} );
	} );

	/* Monthly costs table: row totals + copy previous month. */
	var table = document.querySelector( '.spcw-costs' );
	if ( table ) {
		var updateRow = function ( row ) {
			var total = 0, any = false;
			row.querySelectorAll( '.spcw-amount' ).forEach( function ( input ) {
				var v = parseAmount( input.value );
				if ( v !== null ) {
					total += v;
					any = true;
				}
			} );
			row.querySelector( '.spcw-row-total' ).textContent = any ? formatAmount( total ) : '';
		};

		table.querySelectorAll( 'tbody tr' ).forEach( updateRow );
		table.addEventListener( 'input', function ( e ) {
			var row = e.target.closest( 'tr' );
			if ( row ) {
				updateRow( row );
			}
		} );

		table.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.spcw-copy-prev' );
			if ( ! btn ) {
				return;
			}
			var row = btn.closest( 'tr' );
			var prev = row.previousElementSibling;
			if ( ! prev ) {
				return;
			}
			var from = prev.querySelectorAll( '.spcw-amount' );
			row.querySelectorAll( '.spcw-amount' ).forEach( function ( input, i ) {
				if ( from[ i ] ) {
					input.value = from[ i ].value;
				}
			} );
			updateRow( row );
			row.closest( 'form' ).dispatchEvent( new Event( 'spcw:changed' ) );
		} );
	}

	/* Settings: show only the details for the selected cost method. */
	var settings = document.querySelector( '.spcw-settings' );
	if ( settings ) {
		var refresh = function () {
			var mode = settings.querySelector( 'input[name="spcw_cogs_mode"]:checked' );
			var source = settings.querySelector( '#spcw-cogs-source' );
			settings.querySelectorAll( '[data-show-for]' ).forEach( function ( el ) {
				el.hidden = ! mode || el.getAttribute( 'data-show-for' ) !== mode.value;
			} );
			settings.querySelectorAll( '[data-show-for-source]' ).forEach( function ( el ) {
				el.hidden = ! source || el.getAttribute( 'data-show-for-source' ) !== source.value;
			} );
		};
		settings.addEventListener( 'change', refresh );
		refresh();
	}
} )();
