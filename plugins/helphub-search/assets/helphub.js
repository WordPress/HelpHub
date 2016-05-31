(function($) {
	$(document).ready(function(){
		
		jQuery('#helphub_search_input').autocomplete({
	        source: function(req, response){
	            jQuery.getJSON( helphub_object.ajax_url + '?action=se_lookup', req, response );
	        },
	        focus: function( event, ui ) {
        		//console.log( ui.data('ui-autocomplete-item') );
        		return false;
      		},
	        select: function(event, ui) {
	        	console.log( ui );
	        },
	        minLength: 0,
	    }).autocomplete( "instance" )._renderItem = function( ul, item ) {
	      	return $( "<li>" )
	        	.append( item.value + " in <strong>" + item.cat + "</strong>" )
	        	.data("ui-autocomplete-item", item)
	        	.appendTo( ul );
	    };

   	});
})(jQuery);