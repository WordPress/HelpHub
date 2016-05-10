jQuery(document).ready(function() {
	//alert(helphub_object.ajax_url);
	jQuery('#helphub_search_form').suggest( helphub_object.ajax_url + '?action=se_lookup' );
});