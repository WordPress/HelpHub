/*jslint node: true */
jQuery( document ).ready( function( $ ) {
	'use strict';

	var $ = window.jQuery;

	// ON DOCUMENT READY
	$(document).ready(function() {

		$( '#helphub-contributors' ).select2({
			tags: true
		});

	}); // end of document ready
});
