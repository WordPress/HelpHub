'use strict';

var $ = window.jQuery;

//ON DOCUMENT READY
$(document).ready(function() {
	$("#track-contributors").select2({
		tags: true
	});
}); //end of document ready
