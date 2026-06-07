( function( $ ) {
	'use strict';

	var frame;

	function setVideo( url ) {
		var $input = $( '#_pimou_product_video_url' );
		var $preview = $( '.pimou-product-video-preview' );
		var $status = $( '.pimou-product-video-status' );

		$input.val( url ).trigger( 'change' );
		$preview.attr( 'src', url );
		$preview.prop( 'hidden', ! url );
		$status.text( url ? $status.data( 'selected' ) : $status.data( 'empty' ) );
	}

	$( document ).on( 'click', '.pimou-select-product-video', function( event ) {
		event.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: pimouProductVideo.frameTitle,
			button: {
				text: pimouProductVideo.buttonText
			},
			library: {
				type: 'video'
			},
			multiple: false
		} );

		frame.on( 'select', function() {
			var attachment = frame.state().get( 'selection' ).first().toJSON();

			if ( attachment && attachment.url ) {
				setVideo( attachment.url );
			}
		} );

		frame.open();
	} );

	$( document ).on( 'click', '.pimou-remove-product-video', function( event ) {
		event.preventDefault();
		setVideo( '' );
	} );
}( jQuery ) );
