(function($) {
	$(document).ready(function(){

		jQuery('#helphub_search_input').autocomplete({
	        source: function(req, response){
	            jQuery.getJSON(helphub_object.ajax_url + '?action=se_lookup', req, response);
	        },
	        focus: function( event, ui ) {
        		//$( "#project" ).val( ui.item.label );
        		return false;
      		},
	        select: function(event, ui) {
	        	console.log( ui.item );
	        },
	        minLength: 0,
	    }).autocomplete( "instance" )._renderItem = function( ul, item ) {
	      return $( "<li>" )
	        .append( item.title + " in <strong>" + item.cat + "</strong>" )
	        .appendTo( ul );
	    };

   	});
})(jQuery);