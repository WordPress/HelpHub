/**
 * Force the 3.7+ zxcvbn password strength check
 */
jQuery( document ).ready( function($) {
	var psr = $( '#pass-strength-result' );

	// Check for password strength meter
	if ( psr.length ) {

		// Attach submit event to form
		psr.parents( 'form' ).on( 'submit', function() {

			// Store check results in hidden field
			$( this ).append( '<input type="hidden" name="slt-fsp-pass-strength-result" value="' + psr.attr('class') + '">' );

		});

	}

});