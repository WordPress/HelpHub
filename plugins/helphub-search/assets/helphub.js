jQuery(document).ready(function(){
	
	jQuery('#helphub_search_input').autocomplete({
        source: function(req, response){
            jQuery.getJSON( helphub_object.ajax_url + '?action=se_lookup', req, response );
        },
        select: function(event, ui) {
        	jQuery('<input>').attr({
			    type: 'hidden',
			    name: 'cat',
			    value: ui.item.cat
			}).appendTo('#hh_search_form');

			jQuery('#hh_search_form').submit();
        },
        minLength: 3,
        html: true
    }).data("ui-autocomplete")._renderItemData = function( ul, item) {
		return jQuery( "<li></li>" ) 
		  .data( "ui-autocomplete-item", item )
		  .append( jQuery( "<a></a>" ).html( item.label ) )
		  .appendTo( ul );
	};

	});