( function ( $, mw ) {
	$( document ).ready( function () {
		var $tooltips = $( '.note-tooltip' );
		if ( $tooltips.length ) {
			mw.loader.using( 'jquery.tipsy', function () {
				$tooltips.tipsy( { gravity: 'n', html: true } );
			} );
		}
	} );
} ) ( window.jQuery, mediaWiki );
