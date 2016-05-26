jQuery(document).ready(function() {
	//alert(helphub_object.ajax_url);
	jQuery('#helphub_search_form').suggest( helphub_object.ajax_url + '?action=se_lookup', {
		multiple: false,
		selectClass: 'ac_over',
		matchClass: 'ac_match',
		resultsClass: 'helphub-suggest-results',
		minChars: 2,
		onSelect: function(){
			jQuery('<input />').attr('type', 'hidden')
          	.attr('name', "category_name")
          	.attr('value', jQuery('.ac_over').children('.category').data('id') )
          	.appendTo( jQuery(this) );
		}
	} );
});