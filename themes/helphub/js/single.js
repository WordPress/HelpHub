jQuery( function ( $ ) {

	const sidebar_expandebles = $( document.querySelectorAll( '.expandable .dashicons' ) );

	sidebar_expandebles.on( 'click', function () {
		jQuery( this ).parent().siblings( '.children' ).slideToggle();
		jQuery( this ).closest( '.menu-item-has-children' ).toggleClass( 'open' );
	} );

} );